<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\Kubernetes\GeneratedSecrets;

/**
 * A secret kso made, against a real kubelet: the app and its sidecar read the same value from the
 * workload's Secret, and the preview - what the cluster holds beside what kso would send - never
 * shows it. The images are the cluster suite's own, `nginx:1.29-alpine`.
 */
class GeneratedSecretsClusterTest extends ClusterTestCase {

    public function testTheAppAndItsSidecarReadTheSameValueAndThePreviewShowsNeither(): void {
        $deployment = $this->deployedWithASidecar();
        $step = new DeploymentStep();
        $value = GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'token');

        $this->eventuallyWithin(60, function () use ($step, $deployment) {
            $pods = $step->getPods($deployment);
            return count($pods) === 1 && $pods[0]->isRunning() && !$step->hasNonReadyContainer($deployment);
        }, 'the app and its sidecar never became ready');

        $this->assertSame($value, $this->printenv($step, $deployment, $deployment->name, 'APP_TOKEN'));
        $this->assertSame($value, $this->printenv($step, $deployment, 'push', 'PUSH_TOKEN'));

        $preview = $step->getPreview($deployment);
        $this->assertStringNotContainsString($value, $preview);
    }

    // <editor-fold desc="Helpers">

    private function deployedWithASidecar(): Deployment {
        $deployment = $this->deploymentInTheTestNamespace(['status' => \DeploymentStatusTypes::Synced]);
        $spec = $deployment->findDeploymentSpecification();
        $spec->enable_internal_access = false;
        $spec->save();
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $spec->id, 'name' => 'APP_TOKEN', 'value' => '${secret.token}']);

        $sidecar = Fixtures::initContainer([
            'name' => 'push',
            'container_image_id' => $spec->container_image_id,
            'container_image_tag_policy' => \ContainerImageTagPolicies::Default,
            'command' => 'sleep',
            'args' => json_encode(['3600']),
            'is_sidecar' => true,
        ]);
        Fixtures::initContainerEnvironmentVariable(['init_container_id' => $sidecar->id, 'name' => 'PUSH_TOKEN', 'value' => '${secret.token}']);
        Fixtures::specificationInitContainer(['deployment_specification_id' => $spec->id, 'init_container_id' => $sidecar->id]);

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

    private function printenv(DeploymentStep $step, Deployment $deployment, string $container, string $name): string {
        return trim(implode('', $step->executeCommand($deployment, $container, ['printenv', $name], false)));
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
