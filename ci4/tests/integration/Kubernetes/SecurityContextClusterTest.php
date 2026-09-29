<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\Health\Diagnosis\Diagnoser;
use App\Libraries\Health\Diagnosis\EvidenceGatherer;
use App\Libraries\Health\Diagnosis\Finding;

/**
 * The security settings against a real kubelet. `SecurityContextTest` has what each workload's
 * manifest says; this is what the cluster makes of it - above all that an image made before kso
 * stamped images still starts as root, and that Run as non-root, stamped on the image or turned on
 * by the specification, is what refuses one.
 *
 * The image is the one the cluster suite already has, `nginx:1.29-alpine`, which runs as root.
 */
class SecurityContextClusterTest extends ClusterTestCase {

    public function testAnImageWithoutTheSettingsStillStartsAsRoot(): void {
        $deployment = $this->deployed();

        $this->eventuallyWithin(30, fn () => isset($this->containerStatus($deployment)['state']['running']), 'nginx never started');
    }

    /**
     * Refused by kubelet, not by kso - and the diagnosis says why, and where to change it.
     */
    public function testRunAsNonRootRefusesAnImageThatRunsAsRootAndSaysWhy(): void {
        $deployment = $this->deployed(['security_context_run_as_non_root' => \SecurityContextOverrides::On]);

        $this->eventuallyWithin(30, fn () => ($this->containerStatus($deployment)['state']['waiting']['reason'] ?? null) === 'CreateContainerConfigError', 'kubelet did not refuse it');

        $findings = array_values(array_filter(
            Diagnoser::Diagnose((new EvidenceGatherer($this->cluster()))->gather($deployment), time()),
            fn (Finding $finding) => $finding->rule === 'run_as_non_root',
        ));
        $this->assertCount(1, $findings);
        $this->assertStringContainsString('image runs as root', $findings[0]->cause);
        $this->assertSame('security-context', $findings[0]->action['section']);
    }

    /**
     * Every setting at once, stamped on an image run as a user that is not root: the api server
     * takes the manifest, and kubelet starts the container. That nginx cannot then bind port 80 as
     * that user is nginx's business - what matters here is that it was allowed to try.
     */
    public function testEverySettingIsAcceptedByTheCluster(): void {
        $deployment = $this->deployed(['security_context_fs_group' => '101'], [
            'security_context_run_as_user' => '101',
            'security_context_run_as_group' => '101',
            'security_context_run_as_non_root' => true,
            'security_context_drop_all_capabilities' => true,
            'security_context_seccomp_runtime_default' => true,
        ]);

        $this->eventuallyWithin(30, function () use ($deployment) {
            $status = $this->containerStatus($deployment);
            return isset($status['state']['running']) || isset($status['state']['terminated']) || isset($status['lastState']['terminated']);
        }, 'kubelet never started it');
    }

    // <editor-fold desc="Helpers">

    /**
     * @param array<string, mixed> $specification
     * @param array<string, mixed> $image
     */
    private function deployed(array $specification = [], array $image = []): Deployment {
        $deployment = $this->deploymentInTheTestNamespace(['status' => \DeploymentStatusTypes::Synced]);
        $spec = $deployment->findDeploymentSpecification();
        $spec->enable_internal_access = false;
        foreach ($specification as $field => $value) {
            $spec->{$field} = $value;
        }
        $spec->save();
        $containerImage = new \App\Entities\ContainerImage();
        $containerImage->find($spec->container_image_id);
        foreach ($image as $field => $value) {
            $containerImage->{$field} = $value;
        }
        $containerImage->save();

        (new NamespaceStep())->startDeployCommand($deployment);
        $this->eventually(function () {
            try {
                return $this->cluster()->getServiceAccountByName('default', $this->testNamespace)->exists();
            } catch (\Throwable) {
                return false;
            }
        }, 'The namespace never got its default service account.');

        $fresh = new Deployment();
        $fresh->find($deployment->id);
        $this->assertNull((new DeploymentStep())->tryExecuteDeployCommand($fresh), 'the api server refused the manifest');

        return $fresh;
    }

    private function containerStatus(Deployment $deployment): array {
        foreach ($this->cluster()->getAllPods($this->testNamespace, ['labelSelector' => "app={$deployment->name},role=app"]) as $pod) {
            return $pod->getAttribute('status.containerStatuses', [])[0] ?? [];
        }
        return [];
    }

    /**
     * @param callable(): bool $condition
     */
    private function eventuallyWithin(int $seconds, callable $condition, string $message): void {
        $until = time() + $seconds;
        while (!$condition()) {
            if (time() > $until) {
                $this->fail($message);
            }
            usleep(500000);
        }
        $this->assertTrue(true);
    }

    // </editor-fold>

}
