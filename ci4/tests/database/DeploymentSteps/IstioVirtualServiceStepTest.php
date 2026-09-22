<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\IstioVirtualServiceStep;
use App\ManifestTestCase;

/**
 * The Istio VirtualService, used by workspaces on the Istio network type.
 *
 * The smallest of the network steps and the most opinionated: almost everything in the
 * route is hardcoded.
 */
class IstioVirtualServiceStepTest extends ManifestTestCase {

    public function testVirtualServiceIsNamedAndNamespacedAfterTheDeployment(): void {
        $deployment = $this->deploymentOnIstio();

        $manifest = $this->build($deployment);

        $this->assertSame($deployment->name, $manifest['metadata']['name']);
        $this->assertSame($deployment->namespace, $manifest['metadata']['namespace']);
        $this->assertSame('4spaces.kso', $manifest['metadata']['annotations']['app.kubernetes.io/managed-by']);
    }

    /**
     * The gateway is referenced across namespaces: it lives where the certificate does,
     * not where the deployment does.
     */
    public function testGatewayIsTheDomainsGatewayInTheCertificateNamespace(): void {
        $deployment = $this->deploymentOnIstio([
            'name' => 'istio.example.org',
            'certificate_namespace' => 'istio-system',
        ]);

        $this->assertSame(
            ['istio-system/istio-example-org'],
            $this->spec($deployment)['gateways']
        );
    }

    /**
     * A domain name is not a legal object name, so the dots become dashes. Two domains
     * that differ only in where their dots are would collide, which is unlikely enough to
     * live with but worth knowing.
     */
    public function testGatewayNameReplacesDotsWithDashes(): void {
        $deployment = $this->deploymentOnIstio(['name' => 'a.b.c.example.org']);

        $this->assertSame('default/a-b-c-example-org', $this->spec($deployment)['gateways'][0]);
    }

    public function testHostIsTheWorkspacesUrl(): void {
        $deployment = $this->deploymentOnIstio();

        $this->assertSame(['tenant.test.example.org'], $this->spec($deployment)['hosts']);
    }

    /**
     * The Host header is rewritten to the in-cluster service name, so the workload sees
     * the name it is reachable under rather than the public one.
     */
    public function testTrafficIsRewrittenToTheClusterLocalService(): void {
        $deployment = $this->deploymentOnIstio();

        $route = $this->spec($deployment)['http'][0];
        $expected = "{$deployment->name}.{$deployment->namespace}.svc.cluster.local";

        $this->assertSame($expected, $route['rewrite']['authority']);
        $this->assertSame($expected, $route['route'][0]['destination']['host']);
    }

    /**
     * The step reads the domain through the workspace, so a deployment without one cannot
     * produce a manifest. It says so rather than building something half-addressed.
     */
    public function testDeploymentWithoutAWorkspaceIsRejected(): void {
        $spec = Fixtures::deploymentSpecification(['network_type' => \NetworkTypes::Istio]);
        $deployment = Fixtures::deployment(['deployment_specification_id' => $spec->id]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This step require workspace');

        $this->build($deployment);
    }

    // <editor-fold desc="What the step says it is">

    /**
     * A specification stores the identifier, and `GetStep()` is what turns it back into a
     * step. A step whose identifier does not round trip is unreachable from a saved
     * specification - it would simply never run, and nothing would say so.
     */
    public function testTheStepIsReachableUnderItsOwnIdentifier(): void {
        $step = new IstioVirtualServiceStep();

        $this->assertSame(DeploymentSteps::IstioVirtualService, $step->getIdentifier());
        $this->assertInstanceOf(IstioVirtualServiceStep::class, DeploymentStepHelper::GetStep($step->getIdentifier()));
    }

    /**
     * Deployment level, unlike the Contour and Gateway steps: the VirtualService is named
     * after the deployment and routes to that deployment's service, so there is one per
     * deployment rather than one per hostname.
     */
    public function testTheStepRunsAtDeploymentLevel(): void {
        $this->assertSame(DeploymentStepLevels::Deployment, (new IstioVirtualServiceStep())->getLevel());
    }

    /**
     * No triggers, so nothing that changes a deployment re-applies the VirtualService on
     * its own - `EmitTrigger()` walks the steps and matches on this list.
     */
    public function testNoTriggerReAppliesTheVirtualService(): void {
        $this->assertSame([], (new IstioVirtualServiceStep())->getTriggers());
    }

    /**
     * `toArray()` is the whole contract the UI has with a step: which buttons it draws and
     * which it leaves disabled. Asserting the array rather than each flag is deliberate -
     * a flag that silently flips to false takes a button away, and nothing else notices.
     */
    public function testTheUiIsOfferedEveryCommand(): void {
        $this->assertSame([
            'identifier' => DeploymentSteps::IstioVirtualService,
            'level' => DeploymentStepLevels::Deployment,
            'name' => 'Istio Virtual Service',
            'hasPreviewCommand' => true,
            'hasStatusCommand' => true,
            'hasDeployCommand' => true,
            'hasKubernetesEvents' => true,
            'hasKubernetesStatus' => true,
            'hasTerminateCommand' => true,
        ], (new IstioVirtualServiceStep())->toArray());
    }

    public function testASuccessfulDeployMeansTheVirtualServiceIsFound(): void {
        $this->assertSame(
            DeploymentStepHelper::IstioVirtualService_Found,
            (new IstioVirtualServiceStep())->getSuccessStatus(new Deployment())
        );
    }

    // </editor-fold>

    // <editor-fold desc="Refusing to deploy">

    /**
     * The VirtualService is named after the deployment, so an unnamed one has nothing to
     * be called.
     */
    public function testADeploymentWithoutANameIsNotDeployable(): void {
        $deployment = Fixtures::deployment(['name' => '']);

        $this->assertSame('Missing name', $this->validate($deployment));
    }

    public function testADeploymentWithoutANamespaceIsNotDeployable(): void {
        $deployment = Fixtures::deployment(['namespace' => '']);

        $this->assertSame('Missing namespace', $this->validate($deployment));
    }

    public function testADeploymentWithoutAWorkspaceIsNotDeployable(): void {
        $deployment = Fixtures::deployment(['workspace_id' => null]);

        $this->assertSame('Missing workspace', $this->validate($deployment));
    }

    public function testAWorkspaceWithoutANamespaceIsNotDeployable(): void {
        $deployment = $this->deploymentOnIstio([], ['namespace' => '']);

        $this->assertSame('Missing workspace namespace', $this->validate($deployment));
    }

    public function testAWorkspaceWithoutADomainIsNotDeployable(): void {
        $deployment = Fixtures::deployment(['workspace_id' => Fixtures::workspace()->id]);

        $this->assertSame('Missing workspace domain', $this->validate($deployment));
    }

    /**
     * The domain row can go while the workspace still points at it - the workspace keeps
     * the id, and the step has to survive reading it back.
     */
    public function testADomainThatHasBeenDeletedIsReported(): void {
        $domain = Fixtures::domain(['enable_istio_gateway' => true]);
        $deployment = Fixtures::deployment(['workspace_id' => Fixtures::workspace(['domain_id' => $domain->id])->id]);
        $domain->delete();

        $this->assertSame('domain no longer exists', $this->validate($deployment));
    }

    /**
     * The VirtualService names a Gateway that only exists when the domain asked for one.
     * Without it the manifest would reference a gateway nobody created.
     */
    public function testADomainWithoutAnIstioGatewayIsRefused(): void {
        $deployment = $this->deploymentOnIstio(['enable_istio_gateway' => false]);

        $this->assertSame('domain istio gateway not enabled', $this->validate($deployment));
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures and reading">

    /**
     * @param array<string, mixed> $domain overrides for the domain the workspace is on
     */
    private function deploymentOnIstio(array $domain = [], array $workspace = []): Deployment {
        $row = Fixtures::workspaceOnGateway($domain, $workspace);
        $spec = Fixtures::deploymentSpecification(['network_type' => \NetworkTypes::Istio]);

        return Fixtures::deployment([
            'workspace_id' => $row->id,
            'deployment_specification_id' => $spec->id,
        ]);
    }

    /**
     * Every answer asserted here is reached before the step looks at the Namespace, so no
     * cluster is involved - which is why these live in the database suite.
     */
    private function validate(Deployment $deployment): ?string {
        return (new IstioVirtualServiceStep())->validateDeployCommand($deployment);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Deployment $deployment): array {
        return $this->manifest(IstioVirtualServiceStep::class, $deployment);
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(Deployment $deployment): array {
        return $this->build($deployment)['spec'];
    }

    // </editor-fold>

}
