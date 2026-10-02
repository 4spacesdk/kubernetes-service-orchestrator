<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\Kubernetes\TrustedProxies;

/**
 * `${network.trustedProxies}` against a real cluster: the pod network read from the nodes'
 * `spec.podCIDRs`, and handed to the app in its environment.
 */
class TrustedProxiesClusterTest extends ClusterTestCase {

    public function setUp(): void {
        parent::setUp();
        TrustedProxies::$nodes = null;
        TrustedProxies::$gateway = null;
    }

    public function testTheAppGetsThePodNetworkItsProxiesAreOn(): void {
        // A new node is given its pod network a moment after it registers.
        $podCidrs = [];
        $this->eventuallyWithin(30, function () use (&$podCidrs) {
            $podCidrs = [];
            foreach ($this->cluster()->node()->all() as $node) {
                $podCidrs = [...$podCidrs, ...($node->getAttribute('spec.podCIDRs') ?? [])];
            }
            return $podCidrs !== [];
        }, 'the test cluster never said its pod network');

        $deployment = $this->deployed();
        $step = new DeploymentStep();
        $this->eventuallyWithin(60, function () use ($step, $deployment) {
            $pods = $step->getPods($deployment);
            return count($pods) === 1 && $pods[0]->isRunning() && !$step->hasNonReadyContainer($deployment);
        }, 'the app never became ready');

        $value = trim(implode('', $step->executeCommand($deployment, $deployment->name, ['printenv', 'TRUSTED_PROXIES'], false)));

        $this->assertSame(implode(',', array_values(array_unique($podCidrs))), $value);
    }

    // <editor-fold desc="Helpers">

    private function deployed(): Deployment {
        $deployment = $this->deploymentInTheTestNamespace(['status' => \DeploymentStatusTypes::Synced]);
        $spec = $deployment->findDeploymentSpecification();
        $spec->enable_internal_access = false;
        $spec->network_type = \NetworkTypes::Contour;
        $spec->save();
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $spec->id, 'name' => 'TRUSTED_PROXIES', 'value' => '${network.trustedProxies}']);

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
