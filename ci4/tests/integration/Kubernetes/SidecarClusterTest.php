<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\ServiceStep;
use App\Libraries\Health\ClusterSnapshot;
use App\Libraries\Health\HealthEvaluator;
use App\Libraries\Health\HealthResult;
use App\Libraries\Health\Workload;

/**
 * Native sidecars against a real kubelet: an init container marked Run as sidecar runs beside
 * the app for the life of the pod, answers on its own port - through the pod and through a
 * Service - and the pod is ready, and healthy, with it. One that crashes is not.
 *
 * Both containers are the image the cluster suite already has, `nginx:1.29-alpine`: the app on
 * port 80, and the sidecar told to listen on 8000 and to answer `sidecar`, so it is told apart.
 */
class SidecarClusterTest extends ClusterTestCase {

    private const string ListenOn8000 = "sed -i 's/80;/8000;/' /etc/nginx/conf.d/default.conf"
        . " && echo sidecar > /usr/share/nginx/html/index.html"
        . " && exec nginx -g 'daemon off;'";

    /**
     * The app and the sidecar side by side, the pod ready, the deployment healthy - and port
     * 8000 reaches the sidecar from the app, and through a Service port aimed at it.
     */
    public function testTheSidecarRunsBesideTheAppAndAnswersOnItsPort(): void {
        $deployment = $this->deployedWithASidecar(self::ListenOn8000, servicePort: 8000);
        $step = new DeploymentStep();

        $this->eventuallyWithin(60, function () use ($step, $deployment) {
            $pods = $step->getPods($deployment);
            return count($pods) === 1 && $pods[0]->isRunning() && !$step->hasNonReadyContainer($deployment)
                && ($this->initContainerStatus($deployment, 'push')['ready'] ?? false);
        }, 'the app and its sidecar never became ready together');

        $this->assertSame('sidecar', $this->fetchFromTheApp($step, $deployment, 'http://127.0.0.1:8000/'));
        $this->assertSame('sidecar', $this->fetchFromTheApp($step, $deployment, "http://{$deployment->name}:8000/"), 'through the Service');

        $this->eventuallyWithin(60, fn () => $this->health($deployment)->health === \HealthStatusTypes::Healthy, 'never healthy: ' . $this->health($deployment)->reason);
    }

    /**
     * Deployed twice, the preview shows the init containers as the cluster holds them and as kso
     * would send them - the same, sidecar and all.
     */
    public function testARedeployWithoutChangesShowsNoDifference(): void {
        $deployment = $this->deployedWithASidecar(self::ListenOn8000);
        $step = new DeploymentStep();
        $step->startDeployCommand($deployment);

        $preview = json_decode($step->getPreview($deployment), true);
        $local = json_decode($preview['local'], true)['spec']['template']['spec'];
        $remote = json_decode($preview['remote'], true)['spec']['template']['spec'];

        $this->assertSame('Always', $local['initContainers'][0]['restartPolicy']);
        $this->assertEquals($local['initContainers'], $remote['initContainers']);
    }

    /**
     * A sidecar that crashes keeps the app from starting, and the pod from being ready - and
     * health says so with the reason, rather than waiting for an init container to finish.
     */
    public function testACrashingSidecarIsDegraded(): void {
        $deployment = $this->deployedWithASidecar('exit 1');
        $step = new DeploymentStep();

        $this->eventuallyWithin(90, fn () => $this->health($deployment)->health === \HealthStatusTypes::Degraded, 'never degraded: ' . $this->health($deployment)->reason);

        $this->assertStringContainsString('CrashLoopBackOff', $this->health($deployment)->reason);
        $this->assertTrue($step->hasNonReadyContainer($deployment));
    }

    // <editor-fold desc="Helpers">

    private function deployedWithASidecar(string $script, ?int $servicePort = null): Deployment {
        $deployment = $this->deploymentInTheTestNamespace(['status' => \DeploymentStatusTypes::Synced]);
        $spec = $deployment->findDeploymentSpecification();
        $spec->enable_internal_access = $servicePort !== null;
        $spec->save();

        $sidecar = Fixtures::initContainer([
            'name' => 'push',
            'container_image_id' => $spec->container_image_id,
            'container_image_tag_policy' => \ContainerImageTagPolicies::Default,
            'command' => 'sh',
            'args' => json_encode(['-c', $script]),
            'is_sidecar' => true,
        ]);
        Fixtures::specificationInitContainer([
            'deployment_specification_id' => $spec->id,
            'init_container_id' => $sidecar->id,
        ]);
        if ($servicePort !== null) {
            Fixtures::servicePort([
                'deployment_specification_id' => $spec->id,
                'name' => 'push',
                'port' => $servicePort,
                'target_port' => $servicePort,
            ]);
        }

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
        if ($servicePort !== null) {
            (new ServiceStep())->startDeployCommand($fresh);
        }

        return $fresh;
    }

    private function fetchFromTheApp(DeploymentStep $step, Deployment $deployment, string $url): string {
        $lines = $step->executeCommand($deployment, $deployment->name, ['wget', '-qO-', '-T', '5', $url], false);

        return trim(implode('', $lines));
    }

    private function initContainerStatus(Deployment $deployment, string $name): array {
        foreach ($this->cluster()->getAllPods($this->testNamespace, ['labelSelector' => "app={$deployment->name},role=app"]) as $pod) {
            foreach ($pod->getAttribute('status.initContainerStatuses', []) as $status) {
                if (($status['name'] ?? '') === $name) {
                    return $status;
                }
            }
        }
        return [];
    }

    private function health(Deployment $deployment): HealthResult {
        return HealthEvaluator::Evaluate(
            Workload::Of($deployment, \WorkloadTypes::Deployment),
            ClusterSnapshot::Fetch($this->cluster(), false),
            time(),
        );
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
