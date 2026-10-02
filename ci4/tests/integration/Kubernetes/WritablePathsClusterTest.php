<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\ContainerImage;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\Health\Diagnosis\Diagnoser;
use App\Libraries\Health\Diagnosis\EvidenceGatherer;
use App\Libraries\Health\Diagnosis\Finding;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Writable paths against a real kubelet, with the cluster suite's `nginx:1.29-alpine`: read-only
 * without them it crashes, and the diagnosis names the path; with them it runs, and a redeploy
 * shows no difference. And an `emptyDir` is writable by a user that is not root, with an `fsGroup`
 * and without.
 */
class WritablePathsClusterTest extends ClusterTestCase {

    /** Where nginx writes as it starts. */
    private const string NginxWrites = '/var/cache/nginx,/var/run,/tmp';

    public function testReadOnlyWithoutItsPathsCrashesAndTheDiagnosisNamesThePath(): void {
        $deployment = $this->deployed(['security_context_read_only_root_filesystem' => true]);

        $this->eventuallyWithin(90, function () use ($deployment) {
            $status = $this->containerStatus($deployment);
            return (int) ($status['restartCount'] ?? 0) >= 1;
        }, 'nginx did not crash on its read-only root filesystem');

        // The crashed container's log is there a moment after it restarts.
        $evidence = null;
        $this->eventuallyWithin(60, function () use ($deployment, &$evidence) {
            $evidence = (new EvidenceGatherer($this->cluster()))->gather($deployment);
            return str_contains(json_encode($evidence->previousLogs), 'Read-only file system');
        }, 'the crashed container\'s log never said why');
        $findings = array_values(array_filter(
            Diagnoser::Diagnose($evidence, time()),
            fn(Finding $finding) => $finding->rule === 'read_only_file_system',
        ));
        $this->assertCount(1, $findings);
        $this->assertStringContainsString('/var/cache/nginx/client_temp', $findings[0]->cause, 'where nginx failed as it started');
        $this->assertStringNotContainsString('/10/', $findings[0]->cause, 'not the date of a log line');
    }

    /**
     * With the paths it writes to, it runs - and deployed again, the cluster holds what kso sends.
     */
    public function testReadOnlyWithItsPathsRunsAndARedeployShowsNoDifference(): void {
        $deployment = $this->deployed(['security_context_read_only_root_filesystem' => true, 'writable_paths' => self::NginxWrites]);
        $step = new DeploymentStep();

        $this->eventuallyWithin(60, function () use ($step, $deployment) {
            $pods = $step->getPods($deployment);
            return count($pods) === 1 && $pods[0]->isRunning() && !$step->hasNonReadyContainer($deployment);
        }, 'nginx never ran with its writable paths');

        $step->startDeployCommand($deployment);
        $preview = json_decode($step->getPreview($deployment), true);
        $local = json_decode($preview['local'], true)['spec']['template']['spec'];
        $remote = json_decode($preview['remote'], true)['spec']['template']['spec'];
        $this->assertEquals($local['volumes'], $remote['volumes']);
        $this->assertEquals($local['containers'][0]['volumeMounts'], $remote['containers'][0]['volumeMounts']);
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function fsGroups(): array {
        return [
            'without an fsGroup' => [null],
            'with an fsGroup' => ['2000'],
        ];
    }

    /**
     * A sidecar running as uid 101 on a read-only root filesystem writes to its writable path.
     */
    #[DataProvider('fsGroups')]
    public function testAnEmptyDirIsWritableByAUserThatIsNotRoot(?string $fsGroup): void {
        $deployment = $this->deployed([], $fsGroup === null ? [] : ['security_context_fs_group' => $fsGroup]);
        $spec = $deployment->findDeploymentSpecification();
        $image = Fixtures::containerImage([
            'url' => 'nginx',
            'default_tag' => '1.29-alpine',
            'security_context_run_as_user' => '101',
            'security_context_run_as_group' => '101',
            'security_context_run_as_non_root' => true,
            'security_context_read_only_root_filesystem' => true,
            'writable_paths' => '/scratch',
        ]);
        $sidecar = Fixtures::initContainer([
            'name' => 'writer',
            'container_image_id' => $image->id,
            'container_image_tag_policy' => \ContainerImageTagPolicies::Default,
            'command' => 'sh',
            'args' => json_encode(['-c', 'echo written > /scratch/file && sleep 3600']),
            'is_sidecar' => true,
        ]);
        Fixtures::specificationInitContainer(['deployment_specification_id' => $spec->id, 'init_container_id' => $sidecar->id]);
        $step = new DeploymentStep();
        $this->assertNull($step->tryExecuteDeployCommand($deployment), 'the api server refused the manifest');

        $this->eventuallyWithin(60, function () use ($step, $deployment) {
            $pods = $step->getPods($deployment);
            return count($pods) === 1 && $pods[0]->isRunning() && !$step->hasNonReadyContainer($deployment);
        }, 'the writer never ran');

        $read = trim(implode('', $step->executeCommand($deployment, 'writer', ['cat', '/scratch/file'], false)));
        $this->assertSame('written', $read);
    }

    // <editor-fold desc="Helpers">

    /**
     * @param array<string, mixed> $image
     * @param array<string, mixed> $specification
     */
    private function deployed(array $image, array $specification = []): Deployment {
        $deployment = $this->deploymentInTheTestNamespace(['status' => \DeploymentStatusTypes::Synced]);
        $spec = $deployment->findDeploymentSpecification();
        $spec->enable_internal_access = false;
        foreach ($specification as $field => $value) {
            $spec->{$field} = $value;
        }
        $spec->save();
        $containerImage = new ContainerImage();
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
