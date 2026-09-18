<?php namespace App\Tests\Database\DeploymentSteps;

use App\ManifestTestCase;
use App\Entities\Deployment;
use App\Entities\Workspace;
use App\Fixtures;
use App\Libraries\DeploymentSteps\GatewayHttpRouteStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;

/**
 * The HTTPRoutes that put a workspace on a Gateway API gateway.
 *
 * This step carries the most rules of any of them: routes are grouped per hostname, split
 * once a hostname needs more than 16 of them, attached to a listener that depends on
 * whether the hostname is the apex, and accompanied by a redirect route per alias.
 */
class GatewayHttpRouteStepTest extends ManifestTestCase {

    private const DomainName = 'example.org';

    public function testRouteIsNamedAfterTheHostnameAndLivesInTheWorkspaceNamespace(): void {
        $deployment = $this->workspaceWithOneRoute();

        $routes = $this->build($deployment);

        $this->assertCount(1, $routes);
        $this->assertSame('tenant.example.org', $routes[0]['metadata']['name']);
        $this->assertSame('test', $routes[0]['metadata']['namespace']);
        $this->assertSame(['tenant.example.org'], $routes[0]['spec']['hostnames']);
    }

    public function testRuleMatchesThePathAndSendsItToTheDeployment(): void {
        $deployment = $this->workspaceWithOneRoute('/api', 8080);

        $rule = $this->build($deployment)[0]['spec']['rules'][0];

        $this->assertSame(['type' => 'PathPrefix', 'value' => '/api'], $rule['matches'][0]['path']);
        $this->assertSame($deployment->name, $rule['backendRefs'][0]['name']);
        $this->assertSame(8080, $rule['backendRefs'][0]['port']);
    }

    /**
     * KNative only ever listens on 80, whatever the route says.
     */
    public function testKNativeServiceIsAlwaysAddressedOnPort80(): void {
        $workspace = $this->workspace();
        $specification = Fixtures::deploymentSpecification(['workload_type' => \WorkloadTypes::KNativeService]);
        Fixtures::httpProxyRoute(['deployment_specification_id' => $specification->id, 'port' => 8080]);
        $deployment = $this->deploymentIn($workspace, $specification);

        $rule = $this->build($deployment)[0]['spec']['rules'][0];

        $this->assertSame(80, $rule['backendRefs'][0]['port']);
    }

    /**
     * Two deployments answering on the same hostname belong in the same HTTPRoute, since a
     * hostname can only be claimed once.
     */
    public function testDeploymentsSharingAHostnameShareOneRoute(): void {
        $workspace = $this->workspace();

        $first = Fixtures::deploymentSpecification();
        Fixtures::httpProxyRoute(['deployment_specification_id' => $first->id, 'path' => '/api', 'port' => 8080]);
        $deployment = $this->deploymentIn($workspace, $first, 'backend');

        $second = Fixtures::deploymentSpecification();
        Fixtures::httpProxyRoute(['deployment_specification_id' => $second->id]);
        $this->deploymentIn($workspace, $second, 'frontend');

        $routes = $this->build($deployment);

        $this->assertCount(1, $routes);
        $this->assertCount(2, $routes[0]['spec']['rules']);
    }

    /**
     * Gateway API caps an HTTPRoute at 16 rules, so a hostname that needs more is served by
     * several routes. The first keeps the hostname as its name and the rest are suffixed.
     */
    public function testMoreThanSixteenRulesAreSplitAcrossRoutes(): void {
        $workspace = $this->workspace();
        $specification = Fixtures::deploymentSpecification();
        for ($i = 0; $i < 17; $i++) {
            Fixtures::httpProxyRoute(['deployment_specification_id' => $specification->id, 'path' => "/p{$i}"]);
        }
        $deployment = $this->deploymentIn($workspace, $specification);

        $routes = $this->build($deployment);

        $this->assertCount(2, $routes);
        $this->assertCount(16, $routes[0]['spec']['rules']);
        $this->assertCount(1, $routes[1]['spec']['rules']);
        $this->assertSame('tenant.example.org', $routes[0]['metadata']['name']);
        $this->assertSame('tenant.example.org-1', $routes[1]['metadata']['name']);
        $this->assertSame(['tenant.example.org'], $routes[1]['spec']['hostnames']);
    }

    public function testExactlySixteenRulesStayInOneRoute(): void {
        $workspace = $this->workspace();
        $specification = Fixtures::deploymentSpecification();
        for ($i = 0; $i < 16; $i++) {
            Fixtures::httpProxyRoute(['deployment_specification_id' => $specification->id, 'path' => "/p{$i}"]);
        }
        $deployment = $this->deploymentIn($workspace, $specification);

        $this->assertCount(1, $this->build($deployment));
    }

    public function testParentRefNamesTheGatewayAndItsNamespace(): void {
        $deployment = $this->workspaceWithOneRoute();

        $parentRef = $this->build($deployment)[0]['spec']['parentRefs'][0];

        $this->assertSame('test-gateway', $parentRef['name']);
        $this->assertSame('test', $parentRef['namespace']);
        $this->assertArrayNotHasKey('sectionName', $parentRef, 'no https redirect means no listener is named');
    }

    /**
     * With https redirect the route attaches to a named listener. A subdomain is served by
     * the wildcard listener.
     */
    public function testSubdomainAttachesToTheWildcardListener(): void {
        $deployment = $this->workspaceWithOneRoute('/', 80, true);

        $parentRef = $this->build($deployment)[0]['spec']['parentRefs'][0];

        $this->assertSame('https-wildcard-example-org', $parentRef['sectionName']);
    }

    /**
     * The apex needs a listener of its own: a wildcard listener does not match it. This is
     * the case that was serving the apex from the wrong listener.
     */
    public function testApexAttachesToItsOwnListener(): void {
        $workspace = $this->workspace('', true);
        $specification = Fixtures::deploymentSpecification();
        Fixtures::httpProxyRoute(['deployment_specification_id' => $specification->id]);
        $deployment = $this->deploymentIn($workspace, $specification);

        $routes = $this->build($deployment);

        $this->assertSame('example.org', $routes[0]['metadata']['name']);
        $this->assertSame('https-example-org', $routes[0]['spec']['parentRefs'][0]['sectionName']);
    }

    public function testAliasGetsItsOwnRedirectRoute(): void {
        $deployment = $this->workspaceWithOneRoute('/', 80, false, 'old-name');

        $routes = $this->build($deployment);
        $redirect = $this->redirectRoutes($routes)[0];

        $this->assertSame('kso-alias-redirect-old-name.example.org', $redirect['metadata']['name']);
        $this->assertSame(['old-name.example.org'], $redirect['spec']['hostnames']);

        $filter = $redirect['spec']['rules'][0]['filters'][0];
        $this->assertSame('RequestRedirect', $filter['type']);
        $this->assertSame('tenant.example.org', $filter['requestRedirect']['hostname']);
        $this->assertSame(301, $filter['requestRedirect']['statusCode']);
    }

    /**
     * The label is how the step finds its own redirects again when an alias is removed.
     */
    public function testRedirectRouteIsLabelledWithItsWorkspace(): void {
        $deployment = $this->workspaceWithOneRoute('/', 80, false, 'old-name');

        $redirect = $this->redirectRoutes($this->build($deployment))[0];

        $this->assertSame(
            (string)$deployment->workspace_id,
            $redirect['metadata']['labels']['4spaces.kso/alias-redirect']
        );
    }

    public function testAnAliasCanBeAFullHostnameOnTheDomain(): void {
        $deployment = $this->workspaceWithOneRoute('/', 80, false, 'shop.example.org');

        $redirect = $this->redirectRoutes($this->build($deployment))[0];

        $this->assertSame(['shop.example.org'], $redirect['spec']['hostnames']);
    }

    public function testSeveralAliasesGetARedirectEach(): void {
        $deployment = $this->workspaceWithOneRoute('/', 80, false, 'one, two');

        $this->assertCount(2, $this->redirectRoutes($this->build($deployment)));
    }

    public function testNoAliasesMeansNoRedirectRoutes(): void {
        $deployment = $this->workspaceWithOneRoute();

        $this->assertCount(0, $this->redirectRoutes($this->build($deployment)));
    }

    /**
     * A specification on another network type is not served by this step, so it contributes
     * no rules even when it sits in the same workspace.
     */
    public function testSpecificationOnAnotherNetworkTypeContributesNoRules(): void {
        $workspace = $this->workspace();

        $gatewayApi = Fixtures::deploymentSpecification();
        Fixtures::httpProxyRoute(['deployment_specification_id' => $gatewayApi->id, 'path' => '/api', 'port' => 8080]);
        $deployment = $this->deploymentIn($workspace, $gatewayApi, 'backend');

        $nginx = Fixtures::deploymentSpecification(['network_type' => \NetworkTypes::NginxIngress]);
        Fixtures::httpProxyRoute(['deployment_specification_id' => $nginx->id]);
        $this->deploymentIn($workspace, $nginx, 'frontend');

        $routes = $this->build($deployment);

        $this->assertCount(1, $routes[0]['spec']['rules']);
        $this->assertSame('backend', $routes[0]['spec']['rules'][0]['backendRefs'][0]['name']);
    }

    public function testWorkspaceWithoutAGatewayGeneratesNothing(): void {
        $domain = Fixtures::domain(['name' => self::DomainName]);
        $workspace = Fixtures::workspace(['domain_id' => $domain->id]);
        $specification = Fixtures::deploymentSpecification();
        Fixtures::httpProxyRoute(['deployment_specification_id' => $specification->id]);
        $deployment = $this->deploymentIn($workspace, $specification);

        $this->assertCount(0, $this->build($deployment));
    }

    // <editor-fold desc="What the step says it is">

    /**
     * A specification stores the identifier, and `GetStep()` is what turns it back into a
     * step. A step whose identifier does not round trip is unreachable from a saved
     * specification - it would simply never run, and nothing would say so.
     */
    public function testTheStepIsReachableUnderItsOwnIdentifier(): void {
        $step = new GatewayHttpRouteStep();

        $this->assertSame(DeploymentSteps::GatewayHttpRoute, $step->getIdentifier());
        $this->assertInstanceOf(GatewayHttpRouteStep::class, DeploymentStepHelper::GetStep($step->getIdentifier()));
    }

    /**
     * Workspace level: the routes are grouped per hostname across every deployment in the
     * workspace, so there is one run of this step for the workspace and not one per
     * deployment.
     */
    public function testTheStepRunsAtWorkspaceLevel(): void {
        $this->assertSame(DeploymentStepLevels::Workspace, (new GatewayHttpRouteStep())->getLevel());
    }

    /**
     * No triggers, so nothing that changes a deployment re-applies the routes on its own -
     * `EmitTrigger()` walks the steps and matches on this list.
     */
    public function testNoTriggerReAppliesTheRoutes(): void {
        $this->assertSame([], (new GatewayHttpRouteStep())->getTriggers());
    }

    /**
     * `toArray()` is the whole contract the UI has with a step: which buttons it draws and
     * which it leaves disabled. Asserting the array rather than each flag is deliberate -
     * a flag that silently flips to false takes a button away, and nothing else notices.
     */
    public function testTheUiIsOfferedEveryCommand(): void {
        $this->assertSame([
            'identifier' => DeploymentSteps::GatewayHttpRoute,
            'level' => DeploymentStepLevels::Workspace,
            'name' => 'Gateway HTTP Route',
            'hasPreviewCommand' => true,
            'hasStatusCommand' => true,
            'hasDeployCommand' => true,
            'hasKubernetesEvents' => true,
            'hasKubernetesStatus' => true,
            'hasTerminateCommand' => true,
        ], (new GatewayHttpRouteStep())->toArray());
    }

    public function testASuccessfulDeployMeansTheRouteIsFound(): void {
        $this->assertSame(
            DeploymentStepHelper::GatewayHttpRoute_Found,
            (new GatewayHttpRouteStep())->getSuccessStatus(new Deployment())
        );
    }

    // </editor-fold>

    // <editor-fold desc="Refusing to deploy">

    public function testADeploymentWithoutAWorkspaceIsNotDeployable(): void {
        $deployment = Fixtures::deployment(['workspace_id' => null]);

        $this->assertSame('Missing workspace', $this->validate($deployment));
    }

    public function testAWorkspaceWithoutANamespaceIsNotDeployable(): void {
        $workspace = Fixtures::workspaceOnGateway([], ['namespace' => '']);

        $this->assertSame('Missing workspace namespace', $this->validate($this->deploymentIn($workspace, Fixtures::deploymentSpecification())));
    }

    public function testAWorkspaceWithoutADomainIsNotDeployable(): void {
        $workspace = Fixtures::workspace();

        $this->assertSame('Missing workspace domain', $this->validate($this->deploymentIn($workspace, Fixtures::deploymentSpecification())));
    }

    /**
     * The domain row can go while the workspace still points at it - the workspace keeps
     * the id, and the step has to survive reading it back.
     */
    public function testADomainThatHasBeenDeletedIsReported(): void {
        $domain = Fixtures::domain(['gateway_id' => Fixtures::gateway()->id]);
        $workspace = Fixtures::workspace(['domain_id' => $domain->id]);
        $deployment = $this->deploymentIn($workspace, Fixtures::deploymentSpecification());
        $domain->delete();

        $this->assertSame('domain no longer exists', $this->validate($deployment));
    }

    /**
     * Gateway API routes attach to a Gateway by name. Without one there is nothing to
     * attach to, and `getResources()` answers the same question by building nothing.
     */
    public function testADomainWithNoGatewayIsRefused(): void {
        $workspace = Fixtures::workspace(['domain_id' => Fixtures::domain()->id]);

        $this->assertSame(
            'domain has no gateway assigned',
            $this->validate($this->deploymentIn($workspace, Fixtures::deploymentSpecification()))
        );
    }

    public function testAGatewayThatHasBeenDeletedIsReported(): void {
        $gateway = Fixtures::gateway();
        $workspace = Fixtures::workspace(['domain_id' => Fixtures::domain(['gateway_id' => $gateway->id])->id]);
        $deployment = $this->deploymentIn($workspace, Fixtures::deploymentSpecification());
        $gateway->delete();

        $this->assertSame('assigned gateway no longer exists', $this->validate($deployment));
    }

    // </editor-fold>

    // <editor-fold desc="Status without a cluster">

    /**
     * `getStatus()` is called from `checkStatus()` on every deployment, including ones that
     * never had a workspace. It answers `error` rather than letting the exception out of
     * the builder, because a single unbuildable deployment would otherwise take the whole
     * status poll with it.
     */
    public function testStatusOfADeploymentWithoutAWorkspaceIsError(): void {
        $deployment = Fixtures::deployment(['workspace_id' => null]);

        $this->assertSame(
            DeploymentStepHelper::GatewayHttpRoute_Error,
            (new GatewayHttpRouteStep())->getStatus($deployment)
        );
    }

    public function testBuildingWithoutAWorkspaceSaysSo(): void {
        $deployment = Fixtures::deployment(['workspace_id' => null]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This step require workspace');

        $this->build($deployment);
    }

    // </editor-fold>

    private function workspace(string $subdomain = 'tenant', bool $httpsRedirect = false, string $aliases = ''): Workspace {
        return Fixtures::workspaceOnGateway(
            ['name' => self::DomainName, 'https_redirect' => $httpsRedirect],
            ['subdomain' => $subdomain, 'aliases' => $aliases]
        );
    }

    private function workspaceWithOneRoute(
        string $path = '/',
        int $port = 80,
        bool $httpsRedirect = false,
        string $aliases = ''
    ): Deployment {
        $workspace = $this->workspace('tenant', $httpsRedirect, $aliases);
        $specification = Fixtures::deploymentSpecification();
        Fixtures::httpProxyRoute([
            'deployment_specification_id' => $specification->id,
            'path' => $path,
            'port' => $port,
        ]);

        return $this->deploymentIn($workspace, $specification);
    }

    private function deploymentIn(Workspace $workspace, $specification, string $name = 'test-deployment'): Deployment {
        return Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
            'name' => $name,
        ]);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function build(Deployment $deployment): array {
        return $this->manifests(GatewayHttpRouteStep::class, $deployment);
    }

    /**
     * Every answer asserted here is reached before the step looks at the Namespace, so no
     * cluster is involved - which is why these live in the database suite.
     */
    private function validate(Deployment $deployment): ?string {
        return (new GatewayHttpRouteStep())->validateDeployCommand($deployment);
    }

    /**
     * @param array<array<string, mixed>> $routes
     * @return array<array<string, mixed>>
     */
    private function redirectRoutes(array $routes): array {
        return array_values(array_filter(
            $routes,
            fn(array $route) => str_starts_with($route['metadata']['name'], 'kso-alias-redirect-')
        ));
    }

}

