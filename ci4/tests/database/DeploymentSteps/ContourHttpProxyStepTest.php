<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\Workspace;
use App\Fixtures;
use App\Libraries\DeploymentSteps\ContourHttpProxyStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\ManifestTestCase;

/**
 * The Contour HTTPProxy, used by workspaces on the Contour network type.
 *
 * Workspace level, not deployment level: it collects every deployment in the workspace,
 * groups them by the hostname they answer on, and writes one HTTPProxy per hostname. That
 * grouping is the part worth holding - a deployment quietly landing in the wrong proxy,
 * or in one of its own, is how traffic ends up at the wrong service.
 */
class ContourHttpProxyStepTest extends ManifestTestCase {

    public function testProxyIsNamedAfterTheHostnameAndLivesInTheWorkspaceNamespace(): void {
        $deployment = $this->workspaceWithOneContourDeployment();

        $manifest = $this->build($deployment)[0];

        $this->assertSame('tenant.test.example.org', $manifest['metadata']['name']);
        $this->assertSame('test', $manifest['metadata']['namespace']);
        $this->assertSame('4spaces.kso', $manifest['metadata']['annotations']['app.kubernetes.io/managed-by']);
    }

    public function testVirtualHostServesTheHostnameOverTheDomainsCertificate(): void {
        $deployment = $this->workspaceWithOneContourDeployment();

        $virtualhost = $this->build($deployment)[0]['spec']['virtualhost'];

        $this->assertSame('tenant.test.example.org', $virtualhost['fqdn']);
        $this->assertSame('test/test-cert', $virtualhost['tls']['secretName']);
    }

    public function testIngressClassComesFromTheDomain(): void {
        $deployment = $this->workspaceWithOneContourDeployment(['contour_ingress_class_name' => 'contour-internal']);

        $this->assertSame('contour-internal', $this->build($deployment)[0]['spec']['ingressClassName']);
    }

    public function testRouteSendsThePathToTheDeploymentsService(): void {
        $deployment = $this->workspaceWithOneContourDeployment([], ['path' => '/api', 'port' => 8080]);

        $route = $this->routes($deployment)[0];

        $this->assertSame([['prefix' => '/api']], $route['conditions']);
        $this->assertSame($deployment->name, $route['services'][0]['name']);
        $this->assertSame(8080, $route['services'][0]['port']);
        $this->assertIsInt($route['services'][0]['port']);
    }

    /**
     * Always on. The websocket port behind a proxy is exactly the thing that fails
     * silently when it is not.
     */
    public function testWebsocketsAreAlwaysEnabled(): void {
        $deployment = $this->workspaceWithOneContourDeployment();

        $this->assertTrue($this->routes($deployment)[0]['enableWebsockets']);
    }

    public function testTimeoutPolicyOnlyCarriesTheValuesThatAreSet(): void {
        $deployment = $this->workspaceWithOneContourDeployment([], [
            'timeout_policy_idle' => '60s',
            'timeout_policy_response' => '',
            'timeout_policy_idle_connection' => '30s',
        ]);

        $timeouts = $this->routes($deployment)[0]['timeoutPolicy'];

        $this->assertSame('60s', $timeouts['idle']);
        $this->assertSame('30s', $timeouts['idleConnection']);
        $this->assertArrayNotHasKey('response', $timeouts);
    }

    public function testProtocolIsOnlyWrittenWhenTheRouteSetsOne(): void {
        $with = $this->workspaceWithOneContourDeployment([], ['protocol' => 'h2c']);
        $without = $this->workspaceWithOneContourDeployment([], ['protocol' => '']);

        $this->assertSame('h2c', $this->routes($with)[0]['services'][0]['protocol']);
        $this->assertArrayNotHasKey('protocol', $this->routes($without)[0]['services'][0]);
    }

    /**
     * KNative puts its own proxy in front of the workload and only listens on 80, so the
     * port on the route is ignored for that workload type.
     */
    public function testKnativeWorkloadsAreAlwaysAddressedOnPortEighty(): void {
        $deployment = $this->workspaceWithOneContourDeployment(
            [],
            ['port' => 8080],
            ['workload_type' => \WorkloadTypes::KNativeService]
        );

        $this->assertSame(80, $this->routes($deployment)[0]['services'][0]['port']);
    }

    /**
     * KNative routes by Host header, so the request has to arrive claiming to be the
     * in-cluster service - while the original hostname is passed along separately, which
     * is what KNative needs to resolve the revision.
     */
    public function testKnativeWorkloadsGetTheirHostHeaderRewritten(): void {
        $deployment = $this->workspaceWithOneContourDeployment(
            [],
            [],
            ['workload_type' => \WorkloadTypes::KNativeService]
        );

        $route = $this->routes($deployment)[0];

        $this->assertSame(
            [['name' => 'Host', 'value' => "{$deployment->name}.{$deployment->namespace}.svc.cluster.local"]],
            $route['requestHeadersPolicy']['set']
        );
        $this->assertSame(
            [['name' => 'K-Original-Host', 'value' => 'tenant.test.example.org']],
            $route['services'][0]['requestHeadersPolicy']['set']
        );
    }

    public function testPlainDeploymentsGetNoHeaderRewriting(): void {
        $deployment = $this->workspaceWithOneContourDeployment();

        $this->assertArrayNotHasKey('requestHeadersPolicy', $this->routes($deployment)[0]);
    }

    /**
     * Two deployments answering on the same hostname share one proxy, which is the whole
     * reason this step works at workspace level rather than per deployment.
     */
    public function testDeploymentsOnTheSameHostnameShareOneProxy(): void {
        $workspace = Fixtures::workspaceOnGateway();
        $first = $this->contourDeployment($workspace, [], ['path' => '/api']);
        $this->contourDeployment($workspace, [], ['path' => '/admin']);

        $manifests = $this->build($first);

        $this->assertCount(1, $manifests);
        $this->assertCount(2, $manifests[0]['spec']['routes']);
    }

    /**
     * A specification can put its deployment on a different hostname with a prefix, and
     * then it needs a proxy of its own.
     */
    public function testADifferentHostnameGetsItsOwnProxy(): void {
        $workspace = Fixtures::workspaceOnGateway();
        $first = $this->contourDeployment($workspace);
        $this->contourDeployment($workspace, ['domain_prefix' => 'admin-']);

        $names = array_map(
            static fn (array $manifest) => $manifest['metadata']['name'],
            $this->build($first)
        );

        $this->assertCount(2, $names);
        $this->assertContains('tenant.test.example.org', $names);
        $this->assertContains('admin-tenant.test.example.org', $names);
    }

    /**
     * The hostnames are collected from every deployment in the workspace, but only Contour
     * ones contribute routes - so a deployment on another network type, on a hostname of its
     * own, used to produce an HTTPProxy with an empty route list. Contour's schema refuses
     * that, and the 422 named a field nobody wrote, so the whole deploy failed on a hostname
     * the workspace was not even serving through Contour. There is nothing to route, so
     * there is no proxy.
     */
    public function testAHostnameWithNoContourRoutesGetsNoProxy(): void {
        $workspace = Fixtures::workspaceOnGateway();
        $contour = $this->contourDeployment($workspace);

        $otherSpec = Fixtures::deploymentSpecification([
            'network_type' => \NetworkTypes::GatewayApi,
            'domain_prefix' => 'other-',
        ]);
        // A route of its own, which is what makes the empty result meaningful: the route
        // exists and is left out because of the network type, not because there is none.
        Fixtures::httpProxyRoute(['deployment_specification_id' => $otherSpec->id, 'path' => '/']);
        Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $otherSpec->id,
            'name' => 'other-deployment',
        ]);

        $names = array_column(array_column($this->build($contour), 'metadata'), 'name');

        $this->assertNotContains('other-tenant.test.example.org', $names);
        $this->assertContains('tenant.test.example.org', $names, 'the Contour hostname is still served');
    }

    public function testEveryTimeoutIsWrittenWhenTheRouteSetsThemAll(): void {
        $deployment = $this->workspaceWithOneContourDeployment([], [
            'timeout_policy_idle' => '60s',
            'timeout_policy_response' => '90s',
            'timeout_policy_idle_connection' => '30s',
        ]);

        $this->assertSame(
            ['idle' => '60s', 'response' => '90s', 'idleConnection' => '30s'],
            $this->routes($deployment)[0]['timeoutPolicy']
        );
    }

    // <editor-fold desc="What the step says it is">

    /**
     * A specification stores the identifier, and `GetStep()` is what turns it back into a
     * step. A step whose identifier does not round trip is unreachable from a saved
     * specification - it would simply never run, and nothing would say so.
     */
    public function testTheStepIsReachableUnderItsOwnIdentifier(): void {
        $step = new ContourHttpProxyStep();

        $this->assertSame(DeploymentSteps::ContourHttpProxy, $step->getIdentifier());
        $this->assertInstanceOf(ContourHttpProxyStep::class, DeploymentStepHelper::GetStep($step->getIdentifier()));
    }

    /**
     * Workspace level, which is what the class comment above is about: one proxy per
     * hostname for the whole workspace. At deployment level the UI would list it once per
     * deployment and each would claim the same object.
     */
    public function testTheStepRunsAtWorkspaceLevel(): void {
        $this->assertSame(DeploymentStepLevels::Workspace, (new ContourHttpProxyStep())->getLevel());
    }

    /**
     * No triggers, so nothing that changes a deployment re-applies the proxy on its own -
     * `EmitTrigger()` walks the steps and matches on this list. The workspace deploy is
     * the only thing that runs it.
     */
    public function testNoTriggerReAppliesTheProxy(): void {
        $this->assertSame([], (new ContourHttpProxyStep())->getTriggers());
    }

    /**
     * `toArray()` is the whole contract the UI has with a step: which buttons it draws and
     * which it leaves disabled. Asserting the array rather than each flag is deliberate -
     * a flag that silently flips to false takes a button away, and nothing else notices.
     */
    public function testTheUiIsOfferedEveryCommand(): void {
        $this->assertSame([
            'identifier' => DeploymentSteps::ContourHttpProxy,
            'level' => DeploymentStepLevels::Workspace,
            'name' => 'Contour Http Proxy',
            'hasPreviewCommand' => true,
            'hasStatusCommand' => true,
            'hasDeployCommand' => true,
            'hasKubernetesEvents' => true,
            'hasKubernetesStatus' => true,
            'hasTerminateCommand' => true,
        ], (new ContourHttpProxyStep())->toArray());
    }

    public function testASuccessfulDeployMeansTheProxyIsFound(): void {
        $this->assertSame(
            DeploymentStepHelper::ContourHttpProxy_Found,
            (new ContourHttpProxyStep())->getSuccessStatus(new Deployment())
        );
    }

    // </editor-fold>

    // <editor-fold desc="Refusing to deploy">

    /**
     * Everything the step reads hangs off the workspace, so without one there is nothing
     * to build a proxy from.
     */
    public function testADeploymentWithoutAWorkspaceIsNotDeployable(): void {
        $deployment = Fixtures::deployment(['workspace_id' => null]);

        $this->assertSame('Missing workspace', $this->validate($deployment));
    }

    public function testAWorkspaceWithoutANamespaceIsNotDeployable(): void {
        $deployment = $this->contourDeployment(Fixtures::workspaceOnGateway([], ['namespace' => '']));

        $this->assertSame('Missing workspace namespace', $this->validate($deployment));
    }

    public function testAWorkspaceWithoutADomainIsNotDeployable(): void {
        $deployment = $this->contourDeployment(Fixtures::workspace());

        $this->assertSame('Missing workspace domain', $this->validate($deployment));
    }

    /**
     * The domain row can go while the workspace still points at it - the workspace keeps
     * the id, and the step has to survive reading it back.
     */
    public function testADomainThatHasBeenDeletedIsReported(): void {
        $domain = Fixtures::domain(['enable_contour' => true, 'contour_ingress_class_name' => 'contour']);
        $deployment = $this->contourDeployment(Fixtures::workspace(['domain_id' => $domain->id]));
        $domain->delete();

        $this->assertSame('domain no longer exists', $this->validate($deployment));
    }

    /**
     * Contour is per domain, and a domain that is not on Contour has no ingress class and
     * no controller watching. Deploying anyway would leave an object nothing serves.
     */
    public function testADomainWithContourTurnedOffIsRefused(): void {
        $deployment = $this->contourDeployment(Fixtures::workspaceOnGateway(['enable_contour' => false]));

        $this->assertSame('domain contour not enabled', $this->validate($deployment));
    }

    /**
     * The ingress class is what picks which Contour installation serves the proxy. Empty,
     * the object is accepted by the api server and adopted by nobody.
     */
    public function testADomainWithoutAnIngressClassIsRefused(): void {
        $deployment = $this->contourDeployment(Fixtures::workspaceOnGateway([
            'enable_contour' => true,
            'contour_ingress_class_name' => '',
        ]));

        $this->assertSame('domain contour ingress class name is missing', $this->validate($deployment));
    }

    // </editor-fold>

    // <editor-fold desc="Status without a cluster">

    /**
     * `getStatus()` is called from `checkStatus()` on every deployment, including ones
     * that never had a workspace. It answers `error` rather than letting the exception out
     * of the builder, because a single unbuildable deployment would otherwise take the
     * whole status poll with it.
     */
    public function testStatusOfADeploymentWithoutAWorkspaceIsError(): void {
        $deployment = Fixtures::deployment(['workspace_id' => null]);

        $this->assertSame(
            DeploymentStepHelper::ContourHttpProxy_Error,
            (new ContourHttpProxyStep())->getStatus($deployment)
        );
    }

    public function testBuildingWithoutAWorkspaceSaysSo(): void {
        $deployment = Fixtures::deployment(['workspace_id' => null]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This step require workspace');

        $this->build($deployment);
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures and reading">

    /**
     * @param array<string, mixed> $domain overrides for the workspace's domain
     * @param array<string, mixed> $route overrides for the http proxy route
     * @param array<string, mixed> $specification overrides for the specification
     */
    private function workspaceWithOneContourDeployment(
        array $domain = [],
        array $route = [],
        array $specification = []
    ): Deployment {
        return $this->contourDeployment(Fixtures::workspaceOnGateway($domain), $specification, $route);
    }

    /**
     * One deployment on Contour inside an existing workspace, with a route.
     *
     * @param array<string, mixed> $specification overrides for the specification
     * @param array<string, mixed> $route overrides for the http proxy route
     */
    private function contourDeployment(
        Workspace $workspace,
        array $specification = [],
        array $route = []
    ): Deployment {
        $spec = Fixtures::deploymentSpecification(array_merge(
            ['network_type' => \NetworkTypes::Contour],
            $specification
        ));
        Fixtures::httpProxyRoute(array_merge(
            ['deployment_specification_id' => $spec->id, 'path' => '/', 'port' => 80],
            $route
        ));

        return Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $spec->id,
            'name' => 'test-deployment-' . $spec->id,
        ]);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function build(Deployment $deployment): array {
        return $this->manifests(ContourHttpProxyStep::class, $deployment);
    }

    /**
     * Every answer asserted here is reached before the step looks at the Namespace, so no
     * cluster is involved - which is why these live in the database suite.
     */
    private function validate(Deployment $deployment): ?string {
        return (new ContourHttpProxyStep())->validateDeployCommand($deployment);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function routes(Deployment $deployment): array {
        return $this->build($deployment)[0]['spec']['routes'];
    }

    // </editor-fold>

}
