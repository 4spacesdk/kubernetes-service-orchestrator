<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\ServiceStep;
use App\ManifestTestCase;

/**
 * The Service that puts a deployment's pods on the network.
 *
 * Short manifest, but the selector and the ports are what decide whether traffic arrives
 * at all, and a wrong port number is invisible until something fails to connect.
 */
class ServiceStepTest extends ManifestTestCase {

    /**
     * Nothing re-runs this step on its own: it has no triggers, so a changed service port
     * reaches the cluster only when the whole deployment is deployed again.
     */
    public function testTheStepIsWiredInAtTheDeploymentLevelWithNoTriggers(): void {
        $step = new ServiceStep();
        $deployment = Fixtures::deployableDeployment();

        $this->assertSame(DeploymentSteps::Service, $step->getIdentifier());
        $this->assertSame(DeploymentStepLevels::Deployment, $step->getLevel());
        $this->assertSame('Service', $step->getName());
        $this->assertSame([], $step->getTriggers());
        $this->assertSame(DeploymentStepHelper::Service_Found, $step->getSuccessStatus($deployment));
        $this->assertTrue($step->hasPreviewCommand());
        $this->assertTrue($step->hasStatusCommand());
        $this->assertTrue($step->hasDeployCommand());
        $this->assertTrue($step->hasTerminateCommand());
    }

    /**
     * A Service has a status of its own in Kubernetes - a load balancer's addresses land in
     * it - but kso does not read it, and says so rather than returning an empty list the UI
     * would then render as "no events".
     */
    public function testTheStepReportsNoEventsOrStatusOfItsOwn(): void {
        $step = new ServiceStep();
        $deployment = Fixtures::deployableDeployment();

        $this->assertFalse($step->hasKubernetesEvents());
        $this->assertSame([], $step->getKubernetesEvents($deployment));

        $this->assertFalse($step->hasKubernetesStatus());
        $this->assertSame([], $step->getKubernetesStatus($deployment));
    }

    /**
     * The two checks that come before the step looks at the cluster at all. Both are on
     * columns the UI fills in, so an empty one means a row that was written another way.
     */
    public function testDeployIsRefusedWithoutANameOrANamespace(): void {
        $step = new ServiceStep();

        $this->assertSame(
            'Missing name',
            $step->validateDeployCommand(Fixtures::deployableDeployment(['name' => '']))
        );
        $this->assertSame(
            'Missing namespace',
            $step->validateDeployCommand(Fixtures::deployableDeployment(['namespace' => '']))
        );
    }

    public function testServiceIsNamedAndNamespacedAfterTheDeployment(): void {
        $deployment = Fixtures::deployableDeployment();

        $manifest = $this->build($deployment);

        $this->assertSame($deployment->name, $manifest['metadata']['name']);
        $this->assertSame($deployment->namespace, $manifest['metadata']['namespace']);
    }

    /**
     * The selector has to match the label the Deployment puts on its pods, or the Service
     * has no endpoints and every request fails with nothing obviously wrong.
     */
    public function testSelectorMatchesTheDeploymentsPodLabel(): void {
        $deployment = Fixtures::deployableDeployment();

        $this->assertSame(
            ['app' => $deployment->name],
            $this->build($deployment)['spec']['selector']
        );
    }

    public function testServiceIsANodePort(): void {
        $deployment = Fixtures::deployableDeployment();

        $this->assertSame('NodePort', $this->build($deployment)['spec']['type']);
    }

    public function testPortsComeFromTheSpecification(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'http',
            'protocol' => 'TCP',
            'port' => 80,
            'target_port' => 8080,
        ]);

        $ports = $this->build($deployment)['spec']['ports'];

        $this->assertCount(1, $ports);
        $this->assertSame('http', $ports[0]['name']);
        $this->assertSame('TCP', $ports[0]['protocol']);
        $this->assertSame(80, $ports[0]['port']);
        $this->assertSame(8080, $ports[0]['targetPort']);
    }

    /**
     * Ports arrive from the database as strings. Kubernetes rejects a port that is not a
     * number, so the conversion matters.
     */
    public function testPortsAreNumbersNotStrings(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'port' => 443,
            'target_port' => 8443,
        ]);

        $port = $this->build($deployment)['spec']['ports'][0];

        $this->assertIsInt($port['port']);
        $this->assertIsInt($port['targetPort']);
    }

    public function testSeveralPortsAreAllCarriedOver(): void {
        $deployment = Fixtures::deployableDeployment();
        foreach ([['http', 80], ['websocket', 8081]] as [$name, $port]) {
            Fixtures::servicePort([
                'deployment_specification_id' => $deployment->deployment_specification_id,
                'name' => $name,
                'port' => $port,
                'target_port' => $port,
            ]);
        }

        $ports = $this->build($deployment)['spec']['ports'];

        $this->assertCount(2, $ports);
        $this->assertSame(['http', 'websocket'], array_column($ports, 'name'));
    }

    public function testServiceAnnotationsFromTheSpecificationAreApplied(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::serviceAnnotation([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'cloud.google.com/neg',
            'value' => '{"ingress": true}',
        ]);

        $this->assertSame(
            '{"ingress": true}',
            $this->build($deployment)['metadata']['annotations']['cloud.google.com/neg']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Deployment $deployment): array {
        return $this->manifest(ServiceStep::class, $deployment);
    }

}
