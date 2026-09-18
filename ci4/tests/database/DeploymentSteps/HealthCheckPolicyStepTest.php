<?php namespace App\Tests\Database\DeploymentSteps;

use App\ManifestTestCase;
use App\Entities\Deployment;
use App\Entities\DeploymentSpecification;
use App\Fixtures;
use App\Libraries\DeploymentSteps\HealthCheckPolicyStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;

/**
 * A HealthCheckPolicy decides how GKE health checks a Service behind a GKE Gateway.
 *
 * The policy has no per port selector, so one config has to cover every port of the
 * Service. That resolution is the interesting part, and it is what these tests pin down.
 */
class HealthCheckPolicyStepTest extends ManifestTestCase {

    public function testHttpPortGivesAnHttpCheckOnItsPath(): void {
        $deployment = $this->deploymentWithPorts([
            [\HealthCheckTypes::Http, '/health'],
        ]);

        $config = $this->buildConfig($deployment);

        $this->assertSame('HTTP', $config['type']);
        $this->assertSame('/health', $config['httpHealthCheck']['requestPath']);
        $this->assertSame('USE_SERVING_PORT', $config['httpHealthCheck']['portSpecification']);
    }

    public function testHttpPortWithoutAPathFallsBackToRoot(): void {
        $deployment = $this->deploymentWithPorts([
            [\HealthCheckTypes::Http, null],
        ]);

        $this->assertSame('/', $this->buildConfig($deployment)['httpHealthCheck']['requestPath']);
    }

    /**
     * The path is the one of an **HTTP** port. A port that asks for no check can still
     * carry a path left over from when it did, and taking that one would point the check
     * at a url the serving port has never heard of.
     */
    public function testThePathComesFromAnHttpPortAndNotFromAnyPortThatHasOne(): void {
        $deployment = $this->deploymentWithPorts([
            [null, '/left-over'],
            [\HealthCheckTypes::Http, '/health'],
        ]);

        $this->assertSame('/health', $this->buildConfig($deployment)['httpHealthCheck']['requestPath']);
    }

    public function testTcpPortGivesATcpCheck(): void {
        $deployment = $this->deploymentWithPorts([
            [\HealthCheckTypes::Tcp, null],
        ]);

        $config = $this->buildConfig($deployment);

        $this->assertSame('TCP', $config['type']);
        $this->assertSame('USE_SERVING_PORT', $config['tcpHealthCheck']['portSpecification']);
    }

    /**
     * The reason the step exists. A port that does not speak HTTP can never answer an HTTP
     * check, while an HTTP port answers a TCP check fine, so one TCP port pulls the whole
     * policy to TCP. A websocket port next to an HTTP port is the case from the field.
     */
    public function testASingleTcpPortPullsTheWholePolicyToTcp(): void {
        $deployment = $this->deploymentWithPorts([
            [\HealthCheckTypes::Http, '/health'],
            [\HealthCheckTypes::Tcp, null],
        ]);

        $this->assertSame('TCP', $this->buildConfig($deployment)['type']);
    }

    public function testPortOrderDoesNotChangeTheOutcome(): void {
        $deployment = $this->deploymentWithPorts([
            [\HealthCheckTypes::Tcp, null],
            [\HealthCheckTypes::Http, '/health'],
        ]);

        $this->assertSame('TCP', $this->buildConfig($deployment)['type']);
    }

    /**
     * No port asks for a check, so the deployment keeps GKE's default rather than being
     * handed a policy that says nothing.
     */
    public function testNoPortAskingForACheckGeneratesNoSpec(): void {
        $deployment = $this->deploymentWithPorts([
            [null, null],
        ]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    public function testNoPortsAtAllGeneratesNoSpec(): void {
        $deployment = $this->deploymentWithPorts([]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    public function testNonGkeGatewayClassGeneratesNoSpec(): void {
        $workspace = Fixtures::workspaceOnGateway([], [], ['gateway_class_name' => 'envoy']);
        $specification = Fixtures::deploymentSpecification();
        Fixtures::servicePort([
            'deployment_specification_id' => $specification->id,
            'health_check_type' => \HealthCheckTypes::Http,
            'health_check_path' => '/health',
        ]);
        $deployment = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
        ]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    /**
     * `gke-l7-` and not `gke-`. Google's other gateway classes are on the same vendor
     * prefix and are not served by the load balancer that reads a HealthCheckPolicy, so
     * widening the prefix would attach a policy nothing acts on.
     */
    public function testAGkeGatewayClassThatIsNotAnL7OneGeneratesNoSpec(): void {
        $workspace = Fixtures::workspaceOnGateway([], [], ['gateway_class_name' => 'gke-td']);
        $specification = Fixtures::deploymentSpecification();
        Fixtures::servicePort([
            'deployment_specification_id' => $specification->id,
            'health_check_type' => \HealthCheckTypes::Http,
            'health_check_path' => '/health',
        ]);
        $deployment = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
        ]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    public function testTargetRefNamesTheServiceInTheCoreGroup(): void {
        $deployment = $this->deploymentWithPorts([
            [\HealthCheckTypes::Tcp, null],
        ]);

        $manifest = $this->buildManifest($deployment);

        $this->assertSame(
            ['group' => '', 'kind' => 'Service', 'name' => $deployment->name],
            $manifest['spec']['targetRef']
        );
    }

    /**
     * What the step tells the deployment engine about itself.
     *
     * The identifier keys it in `DeploymentStepHelper`, the level decides whether it runs
     * per deployment or once per workspace, and the four `has*Command()` flags decide which
     * buttons a deployment's page offers. A flipped flag is a command that quietly stops
     * being offered, or one offered for a step that cannot answer it.
     */
    public function testTheStepDeclaresItselfToTheDeploymentEngine(): void {
        $step = new HealthCheckPolicyStep();

        $this->assertSame(DeploymentSteps::HealthCheckPolicy, $step->getIdentifier());
        $this->assertSame(DeploymentStepLevels::Deployment, $step->getLevel());
        $this->assertSame('Health Check Policy', $step->getName());
        $this->assertSame([], $step->getTriggers(), 'nothing redeploys a policy on its own');

        $this->assertTrue($step->hasPreviewCommand());
        $this->assertTrue($step->hasStatusCommand());
        $this->assertTrue($step->hasDeployCommand());
        $this->assertTrue($step->hasTerminateCommand());

        // The policy is inert until GKE's controller reads it, and GKE reports what it did
        // on the backend service rather than on the policy. There is nothing to show.
        $this->assertFalse($step->hasKubernetesEvents());
        $this->assertFalse($step->hasKubernetesStatus());
        $this->assertSame([], $step->getKubernetesEvents($this->deploymentWithPorts([])));
        $this->assertSame([], $step->getKubernetesStatus($this->deploymentWithPorts([])));
    }

    /**
     * The status a finished deploy is held against. It is not one value: a deployment that
     * should have no policy is finished when there is none, and comparing it against
     * `found` would leave every workspace without a health checked port reporting failure.
     */
    public function testSuccessMeansFoundOnlyWhenAPolicyWasAskedFor(): void {
        $step = new HealthCheckPolicyStep();

        $this->assertSame(
            DeploymentStepHelper::HealthCheckPolicy_Found,
            $step->getSuccessStatus($this->deploymentWithPorts([[\HealthCheckTypes::Tcp, null]]))
        );
        $this->assertSame(
            DeploymentStepHelper::HealthCheckPolicy_NotFoundNotExpected,
            $step->getSuccessStatus($this->deploymentWithPorts([[null, null]]))
        );
    }

    // <editor-fold desc="Refusals that need no cluster">

    public function testADeploymentWithoutANameIsRefused(): void {
        $deployment = $this->deploymentWithPorts([[\HealthCheckTypes::Tcp, null]]);
        $deployment->name = '';

        $this->assertSame('Missing name', (new HealthCheckPolicyStep())->validateDeployCommand($deployment));
    }

    public function testADeploymentWithoutANamespaceIsRefused(): void {
        $deployment = $this->deploymentWithPorts([[\HealthCheckTypes::Tcp, null]]);
        $deployment->namespace = '';

        $this->assertSame('Missing namespace', (new HealthCheckPolicyStep())->validateDeployCommand($deployment));
    }

    // </editor-fold>

    // <editor-fold desc="Arrangements that are not behind a GKE gateway">

    /**
     * A specification on nginx or Contour is not routed by a Gateway at all, so the policy
     * would be read by nobody. This is the state most installations are in.
     */
    public function testASpecificationNotOnGatewayApiGeneratesNoSpec(): void {
        $workspace = Fixtures::workspaceOnGateway();
        $specification = Fixtures::deploymentSpecification(['network_type' => \NetworkTypes::NginxIngress]);
        Fixtures::servicePort([
            'deployment_specification_id' => $specification->id,
            'health_check_type' => \HealthCheckTypes::Http,
            'health_check_path' => '/health',
        ]);

        $deployment = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
        ]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    /**
     * A deployment that belongs to no workspace has no gateway to be behind, and the step
     * has to say so rather than follow a relation that is not there.
     */
    public function testADeploymentWithoutAWorkspaceGeneratesNoSpec(): void {
        $specification = Fixtures::deploymentSpecification();
        Fixtures::servicePort([
            'deployment_specification_id' => $specification->id,
            'health_check_type' => \HealthCheckTypes::Tcp,
        ]);

        $deployment = Fixtures::deployment(['deployment_specification_id' => $specification->id]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    public function testAWorkspaceWithoutADomainGeneratesNoSpec(): void {
        $workspace = Fixtures::workspace();
        $specification = Fixtures::deploymentSpecification();
        Fixtures::servicePort([
            'deployment_specification_id' => $specification->id,
            'health_check_type' => \HealthCheckTypes::Tcp,
        ]);

        $deployment = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
        ]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    public function testADomainWithoutAGatewayGeneratesNoSpec(): void {
        $domain = Fixtures::domain();
        $workspace = Fixtures::workspace(['domain_id' => $domain->id]);
        $specification = Fixtures::deploymentSpecification();
        Fixtures::servicePort([
            'deployment_specification_id' => $specification->id,
            'health_check_type' => \HealthCheckTypes::Tcp,
        ]);

        $deployment = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
        ]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    // </editor-fold>

    /**
     * @param array<array{0: ?string, 1: ?string}> $ports health check type and path per port
     */
    private function deploymentWithPorts(array $ports): Deployment {
        $workspace = Fixtures::workspaceOnGateway();
        $specification = Fixtures::deploymentSpecification();

        $portNumber = 80;
        foreach ($ports as [$type, $path]) {
            Fixtures::servicePort([
                'deployment_specification_id' => $specification->id,
                'name' => "port-{$portNumber}",
                'port' => $portNumber,
                'target_port' => $portNumber,
                'health_check_type' => $type,
                'health_check_path' => $path,
            ]);
            $portNumber++;
        }

        return Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
        ]);
    }

    private function buildManifest(Deployment $deployment): array {
        return $this->manifest(HealthCheckPolicyStep::class, $deployment);
    }

    private function buildConfig(Deployment $deployment): array {
        $manifest = $this->buildManifest($deployment);
        $this->assertArrayHasKey('spec', $manifest, 'expected a policy to be generated');
        return $manifest['spec']['default']['config'];
    }

}

