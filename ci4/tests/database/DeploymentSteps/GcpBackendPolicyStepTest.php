<?php namespace App\Tests\Database\DeploymentSteps;

use App\ManifestTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\GcpBackendPolicyStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;

/**
 * A GCPBackendPolicy raises the GCP backend service response timeout above its 30s default
 * for a Service behind a GKE Gateway.
 *
 * The manifest is built without touching the cluster, so what it should contain can be
 * checked here rather than after a deploy.
 */
class GcpBackendPolicyStepTest extends ManifestTestCase {

    public function testTimeoutEndsUpInTheSpec(): void {
        $deployment = Fixtures::deploymentBehindGkeGateway(['gateway_backend_timeout' => 600]);

        $spec = $this->buildSpec($deployment);

        $this->assertSame(600, $spec['default']['timeoutSec']);
    }

    /**
     * targetRef names the Service, carries no namespace, and uses the core group. The
     * policy lives alongside the Service it targets.
     */
    public function testTargetRefNamesTheServiceInTheCoreGroup(): void {
        $deployment = Fixtures::deploymentBehindGkeGateway(['gateway_backend_timeout' => 600]);

        $spec = $this->buildSpec($deployment);

        $this->assertSame(
            ['group' => '', 'kind' => 'Service', 'name' => $deployment->name],
            $spec['targetRef']
        );
        $this->assertArrayNotHasKey('namespace', $spec['targetRef']);
    }

    public function testManifestIsNamedAndNamespacedAfterTheDeployment(): void {
        $deployment = Fixtures::deploymentBehindGkeGateway(['gateway_backend_timeout' => 600]);

        $manifest = $this->buildManifest($deployment);

        $this->assertSame($deployment->name, $manifest['metadata']['name']);
        $this->assertSame($deployment->namespace, $manifest['metadata']['namespace']);
        $this->assertSame('4spaces.kso', $manifest['metadata']['annotations']['app.kubernetes.io/managed-by']);
    }

    /**
     * No timeout set means no opinion, so the deployment keeps GKE's own default rather
     * than being handed a policy that says nothing.
     *
     * An unset timeout and a zero one are the same decision by two routes, and the `=== null`
     * half of the guard in `getTimeout()` is redundant rather than wrong: `(int) null` is 0,
     * so the `<= 0` half already covers it. Dropping it changes no outcome.
     */
    public function testNoTimeoutGeneratesNoSpec(): void {
        $deployment = Fixtures::deploymentBehindGkeGateway();

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    public function testZeroTimeoutGeneratesNoSpec(): void {
        $deployment = Fixtures::deploymentBehindGkeGateway(['gateway_backend_timeout' => 0]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    /**
     * Only GKE's own gateway classes honour the policy. A cluster can be GKE while a given
     * workspace routes through, say, Envoy - a policy there would be dead weight.
     */
    public function testNonGkeGatewayClassGeneratesNoSpec(): void {
        $workspace = Fixtures::workspaceOnGateway([], [], ['gateway_class_name' => 'envoy']);
        $specification = Fixtures::deploymentSpecification(['gateway_backend_timeout' => 600]);
        $deployment = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
        ]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    /**
     * `gke-l7-` and not `gke-`. Google's other gateway classes share the vendor prefix and
     * are not run by the load balancer that reads a GCPBackendPolicy, so widening the
     * prefix would attach a policy nothing acts on.
     */
    public function testAGkeGatewayClassThatIsNotAnL7OneGeneratesNoSpec(): void {
        $workspace = Fixtures::workspaceOnGateway([], [], ['gateway_class_name' => 'gke-td']);
        $specification = Fixtures::deploymentSpecification(['gateway_backend_timeout' => 600]);
        $deployment = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
        ]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    public function testSpecificationNotOnGatewayApiGeneratesNoSpec(): void {
        $workspace = Fixtures::workspaceOnGateway();
        $specification = Fixtures::deploymentSpecification([
            'network_type' => \NetworkTypes::NginxIngress,
            'gateway_backend_timeout' => 600,
        ]);
        $deployment = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
        ]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    public function testDomainWithoutAGatewayGeneratesNoSpec(): void {
        $domain = Fixtures::domain();
        $workspace = Fixtures::workspace(['domain_id' => $domain->id]);
        $specification = Fixtures::deploymentSpecification(['gateway_backend_timeout' => 600]);
        $deployment = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
        ]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    /**
     * What the step tells the deployment engine about itself.
     *
     * The engine builds the whole pipeline from these: the identifier keys it in
     * `DeploymentStepHelper`, the level decides whether it runs per deployment or once per
     * workspace, and the four `has*Command()` flags decide which buttons a deployment's
     * page offers. A flipped flag is not a crash - it is a command that quietly stops being
     * offered, or one offered for a step that cannot answer it.
     */
    public function testTheStepDeclaresItselfToTheDeploymentEngine(): void {
        $step = new GcpBackendPolicyStep();

        $this->assertSame(DeploymentSteps::GcpBackendPolicy, $step->getIdentifier());
        $this->assertSame(DeploymentStepLevels::Deployment, $step->getLevel());
        $this->assertSame('GCP Backend Policy', $step->getName());
        $this->assertSame([], $step->getTriggers(), 'nothing redeploys a policy on its own');

        $this->assertTrue($step->hasPreviewCommand());
        $this->assertTrue($step->hasStatusCommand());
        $this->assertTrue($step->hasDeployCommand());
        $this->assertTrue($step->hasTerminateCommand());

        // The policy is inert until GKE's controller reads it, and GKE reports what it did
        // on the backend service rather than on the policy. There is nothing to show.
        $this->assertFalse($step->hasKubernetesEvents());
        $this->assertFalse($step->hasKubernetesStatus());
        $this->assertSame([], $step->getKubernetesEvents(Fixtures::deploymentBehindGkeGateway()));
        $this->assertSame([], $step->getKubernetesStatus(Fixtures::deploymentBehindGkeGateway()));
    }

    /**
     * The status a finished deploy is held against. It is not one value: a deployment that
     * should have no policy is finished when there is none, and comparing it against
     * `found` would leave every workspace without a timeout stuck reporting failure.
     */
    public function testSuccessMeansFoundOnlyWhenAPolicyWasAskedFor(): void {
        $step = new GcpBackendPolicyStep();

        $this->assertSame(
            DeploymentStepHelper::GcpBackendPolicy_Found,
            $step->getSuccessStatus(Fixtures::deploymentBehindGkeGateway(['gateway_backend_timeout' => 600]))
        );
        $this->assertSame(
            DeploymentStepHelper::GcpBackendPolicy_NotFoundNotExpected,
            $step->getSuccessStatus(Fixtures::deploymentBehindGkeGateway())
        );
    }

    // <editor-fold desc="Refusals that need no cluster">

    public function testADeploymentWithoutANameIsRefused(): void {
        $deployment = Fixtures::deploymentBehindGkeGateway(['gateway_backend_timeout' => 600]);
        $deployment->name = '';

        $this->assertSame('Missing name', (new GcpBackendPolicyStep())->validateDeployCommand($deployment));
    }

    public function testADeploymentWithoutANamespaceIsRefused(): void {
        $deployment = Fixtures::deploymentBehindGkeGateway(['gateway_backend_timeout' => 600]);
        $deployment->namespace = '';

        $this->assertSame('Missing namespace', (new GcpBackendPolicyStep())->validateDeployCommand($deployment));
    }

    // </editor-fold>

    /**
     * A deployment that belongs to no workspace has no gateway to be behind, and the step
     * has to say so rather than follow a relation that is not there. Deployments inside a
     * package template are in exactly this state.
     */
    public function testADeploymentWithoutAWorkspaceGeneratesNoSpec(): void {
        $specification = Fixtures::deploymentSpecification(['gateway_backend_timeout' => 600]);
        $deployment = Fixtures::deployment(['deployment_specification_id' => $specification->id]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    public function testAWorkspaceWithoutADomainGeneratesNoSpec(): void {
        $workspace = Fixtures::workspace();
        $specification = Fixtures::deploymentSpecification(['gateway_backend_timeout' => 600]);
        $deployment = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
        ]);

        $this->assertArrayNotHasKey('spec', $this->buildManifest($deployment));
    }

    private function buildManifest(Deployment $deployment): array {
        return $this->manifest(GcpBackendPolicyStep::class, $deployment);
    }

    private function buildSpec(Deployment $deployment): array {
        $manifest = $this->buildManifest($deployment);
        $this->assertArrayHasKey('spec', $manifest, 'expected a policy to be generated');
        return $manifest['spec'];
    }

}

