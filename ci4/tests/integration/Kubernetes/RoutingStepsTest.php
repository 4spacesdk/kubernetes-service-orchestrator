<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\ContourHttpProxyStep;
use App\Libraries\DeploymentSteps\GatewayHttpRouteStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\IngressStep;
use App\Libraries\DeploymentSteps\IstioVirtualServiceStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\ServiceStep;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;

/**
 * The three steps that put a workspace on a hostname.
 *
 * All three build kinds Kubernetes has never heard of - an HTTPRoute, a Contour HTTPProxy,
 * an Istio VirtualService - and each is validated by a schema that ships with the project
 * that defined it. That schema is the thing a manifest test cannot consult: it is what
 * decides whether a field name is right, whether a value is in the allowed set, and
 * whether a required field was left out.
 *
 * The definitions are installed into the throwaway cluster at startup from
 * `scripts/cluster-crds.txt`. **The controllers are not**, and are not needed: nothing
 * here asks for a packet to be routed, only for the manifest to be accepted and handed
 * back.
 */
class RoutingStepsTest extends ClusterTestCase {

    // <editor-fold desc="Gateway API">

    public function testARouteIsNamedAfterTheHostnameAndAttachedToTheGateway(): void {
        $deployment = $this->routableDeployment();
        $step = new GatewayHttpRouteStep();

        $step->startDeployCommand($deployment);

        $route = $this->namespacedResource('gateway.networking.k8s.io/v1/httproutes', 'tenant.test.example.org');
        $this->assertSame(['tenant.test.example.org'], $route['spec']['hostnames']);
        $this->assertSame('test-gateway', $route['spec']['parentRefs'][0]['name']);
        $this->assertSame('test', $route['spec']['parentRefs'][0]['namespace']);
        $this->assertSame([DeploymentStepHelper::GatewayHttpRoute_Found], $step->getStatus($deployment));
    }

    /**
     * With `https_redirect` the route attaches to one named listener rather than the
     * gateway as a whole, because a wildcard listener does not match the apex hostname.
     * `sectionName` is a Gateway API field with its own validation, and getting it wrong is
     * how a workspace ends up attached to nothing.
     */
    public function testHttpsRedirectAttachesTheRouteToTheWildcardListener(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::GatewayApi, ['https_redirect' => true]);

        (new GatewayHttpRouteStep())->startDeployCommand($deployment);

        $route = $this->namespacedResource('gateway.networking.k8s.io/v1/httproutes', 'tenant.test.example.org');
        $this->assertSame('https-wildcard-test-example-org', $route['spec']['parentRefs'][0]['sectionName']);
    }

    public function testAnAliasGetsARedirectRouteOfItsOwn(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::GatewayApi, [], ['aliases' => 'old-name']);

        (new GatewayHttpRouteStep())->startDeployCommand($deployment);

        $redirect = $this->namespacedResource('gateway.networking.k8s.io/v1/httproutes', 'kso-alias-redirect-old-name.test.example.org');
        $filter = $redirect['spec']['rules'][0]['filters'][0];
        $this->assertSame('RequestRedirect', $filter['type']);
        $this->assertSame('tenant.test.example.org', $filter['requestRedirect']['hostname']);
        $this->assertSame(301, $filter['requestRedirect']['statusCode']);
    }

    /**
     * The one piece of this step that reads the cluster back rather than writing to it.
     * Redirect routes are labelled with the workspace id so that a later deploy can list
     * them and delete the ones no longer asked for. Nothing else removes them: take an
     * alias off a workspace without this and the old hostname keeps redirecting forever,
     * and it is still holding a name no other workspace can then use.
     */
    public function testRemovingAnAliasRemovesItsRedirectRoute(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::GatewayApi, [], ['aliases' => 'old-name,older-name']);
        $step = new GatewayHttpRouteStep();
        $step->startDeployCommand($deployment);
        $this->assertCount(3, $this->routesIn($this->testNamespace), 'the workspace plus two redirects');

        $deployment->workspace->aliases = 'old-name';
        $deployment->workspace->save();
        $step->startDeployCommand($deployment);

        $names = array_map(fn (array $route) => $route['metadata']['name'], $this->routesIn($this->testNamespace));
        sort($names);
        $this->assertSame([
            'kso-alias-redirect-old-name.test.example.org',
            'tenant.test.example.org',
        ], $names);
    }

    public function testTerminatingRemovesEveryRoute(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::GatewayApi, [], ['aliases' => 'old-name']);
        $step = new GatewayHttpRouteStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(fn () => $this->routesIn($this->testNamespace) === []);
    }

    /**
     * A specification with no routes has nothing to route, and no HTTPRoute is built at
     * all - the rules are chunked, and there are no chunks. Worth stating, because the
     * empty case ends differently for Contour below.
     */
    public function testASpecificationWithoutRoutesGetsNoHttpRoute(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::GatewayApi, [], [], withRoute: false);

        (new GatewayHttpRouteStep())->startDeployCommand($deployment);

        $this->assertSame([], $this->routesIn($this->testNamespace));
    }

    public function testAnHttpRouteIsDeployableOnceItsNamespaceExists(): void {
        $step = new GatewayHttpRouteStep();
        $deployment = $this->routableDeployment(\NetworkTypes::GatewayApi, [], [], applyNamespace: false);

        $this->assertSame('Missing Namespace', $step->validateDeployCommand($deployment));

        (new NamespaceStep())->startDeployCommand($deployment);

        $this->assertNull($step->validateDeployCommand($deployment));
    }

    public function testTheHttpRoutePreviewShowsWhatIsThereAndWhatWouldBeSent(): void {
        $deployment = $this->routableDeployment();
        $step = new GatewayHttpRouteStep();

        $before = $this->preview($step, $deployment);
        $this->assertCount(1, $before['local']);
        $this->assertSame([], $before['remote'], 'nothing is applied yet');

        $step->startDeployCommand($deployment);

        $after = $this->preview($step, $deployment);
        $remote = json_decode($after['remote'][0], true);
        $this->assertSame('tenant.test.example.org', $remote['metadata']['name']);
        $this->assertArrayNotHasKey('uid', $remote['metadata'], 'the api servers own bookkeeping is stripped');
        $this->assertArrayNotHasKey('managedFields', $remote['metadata']);
        $this->assertArrayNotHasKey('status', $remote);
    }

    /**
     * An HTTPRoute carries no status until a controller adopts it - unlike a Gateway,
     * which the api server fills in itself. `null` is therefore what the panel shows in
     * a cluster whose Gateway controller is missing, and the step passes it through rather
     * than inventing an empty one.
     */
    public function testTheHttpRouteStatusIsEmptyUntilAControllerAdoptsIt(): void {
        $deployment = $this->routableDeployment();
        $step = new GatewayHttpRouteStep();
        $step->startDeployCommand($deployment);

        $this->assertSame([null], $step->getKubernetesStatus($deployment));
    }

    public function testTheHttpRouteEventsAreItsOwnAndNobodyElses(): void {
        $deployment = $this->routableDeployment();
        $step = new GatewayHttpRouteStep();
        $step->startDeployCommand($deployment);

        $this->assertSame([], $step->getKubernetesEvents($deployment), 'nothing has happened to it yet');

        $this->emitEventFor($this->firstResource($step, $deployment), 'SyncFailed', 'no matching listener');

        $this->assertSame(
            [['type' => 'Warning', 'reason' => 'SyncFailed', 'from' => 'kso-test', 'message' => 'no matching listener']],
            $this->eventSummaries($step->getKubernetesEvents($deployment))
        );
    }

    // </editor-fold>

    // <editor-fold desc="Contour">

    public function testAnHttpProxyCarriesTheHostnameAndItsCertificate(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::Contour);
        $step = new ContourHttpProxyStep();

        $step->startDeployCommand($deployment);

        $proxy = $this->namespacedResource('projectcontour.io/v1/httpproxies', 'tenant.test.example.org');
        $this->assertSame('tenant.test.example.org', $proxy['spec']['virtualhost']['fqdn']);
        $this->assertSame(
            "{$this->testNamespace}/test-cert",
            $proxy['spec']['virtualhost']['tls']['secretName'],
            'the certificate is referenced across namespaces'
        );
        $this->assertSame([DeploymentStepHelper::ContourHttpProxy_Found], $step->getStatus($deployment));
    }

    public function testTerminatingRemovesTheHttpProxy(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::Contour);
        $step = new ContourHttpProxyStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === [DeploymentStepHelper::ContourHttpProxy_NotFound]
        );
    }

    /**
     * Contour used to build one proxy per hostname whether or not there were routes to put
     * in it, so a specification with none sent `routes: []` - and php-k8s rewrites **every**
     * empty list in the payload to `{}` before sending it, by string replacement, with three
     * hardcoded exceptions. Contour's schema wants an array, so the deploy failed with a 422
     * naming a field nobody wrote. Nothing to route, no proxy - the same as the Gateway API
     * step above.
     */
    public function testAContourSpecificationWithoutRoutesGetsNoProxy(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::Contour, [], [], withRoute: false);

        (new ContourHttpProxyStep())->startDeployCommand($deployment);

        $this->assertSame([], $this->httpProxiesIn($this->testNamespace));
    }

    public function testAnHttpProxyIsDeployableOnceItsNamespaceExists(): void {
        $step = new ContourHttpProxyStep();
        $deployment = $this->contourDeployment(applyNamespace: false);

        $this->assertSame('Missing Namespace', $step->validateDeployCommand($deployment));

        (new NamespaceStep())->startDeployCommand($deployment);

        $this->assertNull($step->validateDeployCommand($deployment));
    }

    public function testTheHttpProxyPreviewShowsWhatIsThereAndWhatWouldBeSent(): void {
        $deployment = $this->contourDeployment();
        $step = new ContourHttpProxyStep();

        $before = $this->preview($step, $deployment);
        $this->assertCount(1, $before['local']);
        $this->assertSame([], $before['remote'], 'nothing is applied yet');

        $step->startDeployCommand($deployment);

        $remote = json_decode($this->preview($step, $deployment)['remote'][0], true);
        $this->assertSame('tenant.test.example.org', $remote['metadata']['name']);
        $this->assertArrayNotHasKey('uid', $remote['metadata']);
        $this->assertArrayNotHasKey('status', $remote, 'a status that changes on its own is not part of a diff');
    }

    /**
     * Contour's own definition gives an HTTPProxy a default status the moment it is
     * created, and it says in as many words that no controller has looked at it. Nothing
     * in kso produces those words, which is exactly why they are worth pinning: they are
     * the normal state of a proxy in a cluster whose Contour is missing.
     */
    public function testTheHttpProxyStatusSaysItIsWaitingForAController(): void {
        $deployment = $this->contourDeployment();
        $step = new ContourHttpProxyStep();
        $step->startDeployCommand($deployment);

        $this->assertSame([
            ['currentStatus' => 'NotReconciled', 'description' => 'Waiting for controller'],
        ], $step->getKubernetesStatus($deployment));
    }

    public function testTheHttpProxyEventsAreItsOwnAndNobodyElses(): void {
        $deployment = $this->contourDeployment();
        $step = new ContourHttpProxyStep();
        $step->startDeployCommand($deployment);

        $this->assertSame([], $step->getKubernetesEvents($deployment));

        $this->emitEventFor($this->firstResource($step, $deployment), 'SecretNotFound', 'the certificate is not there');

        $this->assertSame(
            [['type' => 'Warning', 'reason' => 'SecretNotFound', 'from' => 'kso-test', 'message' => 'the certificate is not there']],
            $this->eventSummaries($step->getKubernetesEvents($deployment))
        );
    }

    // </editor-fold>

    // <editor-fold desc="Istio">

    public function testAVirtualServiceRoutesTheHostnameToTheClusterService(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::Istio);
        $step = new IstioVirtualServiceStep();

        $step->startDeployCommand($deployment);

        $service = $this->namespacedResource('networking.istio.io/v1/virtualservices', $deployment->name);
        $this->assertSame(['tenant.test.example.org'], $service['spec']['hosts']);
        $this->assertSame(
            "{$deployment->name}.{$this->testNamespace}.svc.cluster.local",
            $service['spec']['http'][0]['route'][0]['destination']['host']
        );
        $this->assertSame(DeploymentStepHelper::IstioVirtualService_Found, $step->getStatus($deployment));
    }

    /**
     * The gateway is named across namespaces as `<namespace>/<name>`, which Istio's schema
     * accepts and its controller resolves. A bare name would mean the workspace's own
     * namespace, where no gateway lives.
     */
    public function testTheVirtualServiceNamesTheGatewayInItsOwnNamespace(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::Istio);

        (new IstioVirtualServiceStep())->startDeployCommand($deployment);

        $service = $this->namespacedResource('networking.istio.io/v1/virtualservices', $deployment->name);
        $this->assertStringStartsWith('default/', $service['spec']['gateways'][0]);
    }

    public function testTerminatingRemovesTheVirtualService(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::Istio);
        $step = new IstioVirtualServiceStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::IstioVirtualService_NotFound
        );
    }

    public function testAVirtualServiceIsDeployableOnceItsNamespaceAndServiceExist(): void {
        $step = new IstioVirtualServiceStep();
        $deployment = $this->istioDeployment(applyNamespace: false);

        $this->assertSame('Missing Namespace', $step->validateDeployCommand($deployment));

        (new NamespaceStep())->startDeployCommand($deployment);
        $this->assertSame('Missing Service', $step->validateDeployCommand($deployment));

        (new ServiceStep())->startDeployCommand($deployment);
        $this->assertNull($step->validateDeployCommand($deployment));
    }

    /**
     * The Istio step builds one resource rather than a list, so its preview has a single
     * remote and says `null` - not an empty list - when there is nothing applied yet.
     */
    public function testTheVirtualServicePreviewShowsWhatIsThereAndWhatWouldBeSent(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::Istio);
        $step = new IstioVirtualServiceStep();

        $before = $this->preview($step, $deployment);
        $this->assertNull($before['remote'], 'nothing is applied yet');
        $this->assertSame($deployment->name, json_decode($before['local'], true)['metadata']['name']);

        $step->startDeployCommand($deployment);

        $remote = json_decode($this->preview($step, $deployment)['remote'], true);
        $this->assertSame(['tenant.test.example.org'], $remote['spec']['hosts']);
        $this->assertArrayNotHasKey('uid', $remote['metadata']);
        $this->assertArrayNotHasKey('resourceVersion', $remote['metadata']);
    }

    /**
     * A bug, pinned rather than closed. `getKubernetesStatus()` is declared to return an
     * array and hands back `status` untouched - and a VirtualService has no `status` until
     * an Istio controller writes one, which is the whole life of a cluster where Istio is
     * missing or has not caught up yet. The other three steps here collect their statuses
     * into a list and so answer `[null]`; this one raises a TypeError, and the panel that
     * asked for it gets a 500 rather than an empty status.
     */
    public function testTheVirtualServiceStatusRaisesATypeErrorUntilAControllerWritesOne(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::Istio);
        $step = new IstioVirtualServiceStep();
        $step->startDeployCommand($deployment);

        $this->expectException(\TypeError::class);

        $step->getKubernetesStatus($deployment);
    }

    /**
     * The other half of the same method: once something has written a status, it is handed
     * back as it stands. Istio is not installed here, so the status is written the way its
     * controller would - straight to the `status` subresource.
     */
    public function testTheVirtualServiceStatusIsHandedBackOnceSomethingHasWrittenOne(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::Istio);
        $step = new IstioVirtualServiceStep();
        $step->startDeployCommand($deployment);

        $this->writeStatus(
            "/apis/networking.istio.io/v1/namespaces/{$this->testNamespace}/virtualservices/{$deployment->name}",
            ['observedGeneration' => 1]
        );

        $this->assertSame(['observedGeneration' => 1], $step->getKubernetesStatus($deployment));
    }

    public function testTheVirtualServiceEventsAreItsOwnAndNobodyElses(): void {
        $deployment = $this->routableDeployment(\NetworkTypes::Istio);
        $step = new IstioVirtualServiceStep();
        $step->startDeployCommand($deployment);

        $this->assertSame([], $step->getKubernetesEvents($deployment));

        $this->emitEventFor($this->firstResource($step, $deployment), 'Rejected', 'no gateway of that name');

        $this->assertSame(
            [['type' => 'Warning', 'reason' => 'Rejected', 'from' => 'kso-test', 'message' => 'no gateway of that name']],
            $this->eventSummaries($step->getKubernetesEvents($deployment))
        );
    }

    // </editor-fold>

    // <editor-fold desc="Nginx ingress">

    public function testAnIngressCarriesItsClassHostnameAndAnnotations(): void {
        $deployment = $this->ingressDeployment();
        $step = new IngressStep();

        $this->assertSame(DeploymentStepHelper::Ingress_NotFound, $step->getStatus($deployment));

        $step->startDeployCommand($deployment);

        $ingress = $this->cluster()->getIngressByName($deployment->name, $this->testNamespace);
        $this->assertSame('nginx', $ingress->getAttribute('spec.ingressClassName'));
        $this->assertSame('tenant.test.example.org', $ingress->getAttribute('spec.rules')[0]['host']);
        $this->assertSame(
            '8m',
            $ingress->getAnnotations()['nginx.ingress.kubernetes.io/proxy-body-size'],
            'the size is stored as a number and sent with its unit'
        );
        $this->assertSame(DeploymentStepHelper::Ingress_Found, $step->getStatus($deployment));
    }

    /**
     * With TLS on, the ingress names the certificate secret for its hostname. Without it
     * the workspace is served over http only, so which of the two happened is worth an
     * assertion rather than an eyeball on a manifest.
     */
    public function testTlsPutsTheCertificateSecretOnTheHostname(): void {
        $deployment = $this->ingressDeployment(['enable_tls' => true]);

        (new IngressStep())->startDeployCommand($deployment);

        $tls = $this->cluster()->getIngressByName($deployment->name, $this->testNamespace)->getAttribute('spec.tls');
        $this->assertSame(['tenant.test.example.org'], $tls[0]['hosts']);
        $this->assertSame('test-cert', $tls[0]['secretName']);
    }

    /**
     * The ingress points at a Service by name, and the api server does not check that the
     * Service is there - it will accept an ingress pointing at nothing. kso checks instead,
     * before it applies.
     */
    public function testTheIngressStepRefusesUntilTheServiceExists(): void {
        $deployment = $this->ingressDeployment(applyService: false);

        $this->assertSame('Missing Service', (new IngressStep())->validateDeployCommand($deployment));
    }

    public function testTerminatingRemovesTheIngress(): void {
        $deployment = $this->ingressDeployment();
        $step = new IngressStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(fn () => $step->getStatus($deployment) === DeploymentStepHelper::Ingress_NotFound);
    }

    public function testAnIngressIsDeployableOnceItsNamespaceAndServiceExist(): void {
        $step = new IngressStep();
        $deployment = $this->ingressDeployment(applyService: false, applyNamespace: false);

        $this->assertSame('Missing Namespace', $step->validateDeployCommand($deployment));

        (new NamespaceStep())->startDeployCommand($deployment);
        $this->assertSame('Missing Service', $step->validateDeployCommand($deployment));

        (new ServiceStep())->startDeployCommand($deployment);
        $this->assertNull($step->validateDeployCommand($deployment));
    }

    /**
     * Two ingresses on one specification, so the preview says something about the list
     * rather than about a single manifest: both are offered locally, and both come back
     * once they are applied.
     */
    public function testTheIngressPreviewShowsEveryIngressOnTheSpecification(): void {
        $deployment = $this->ingressDeployment();
        $this->secondIngressOn($deployment);
        $step = new IngressStep();

        $before = $this->preview($step, $deployment);
        $this->assertCount(2, $before['local']);
        $this->assertSame([], $before['remote'], 'nothing is applied yet');

        $step->startDeployCommand($deployment);

        $after = $this->preview($step, $deployment);
        $this->assertSame(
            [$deployment->name, $deployment->name . '-1'],
            array_map(fn (string $json) => json_decode($json, true)['metadata']['name'], $after['remote'])
        );
        $this->assertArrayNotHasKey('status', json_decode($after['remote'][0], true));
    }

    /**
     * An Ingress is a built-in kind, so the api server gives it a status of its own the
     * moment it is created - an empty load balancer, because no controller has put an
     * address on it yet. That empty object is what the panel shows until one does.
     */
    public function testTheIngressStatusIsAnEmptyLoadBalancerUntilAControllerFillsItIn(): void {
        $deployment = $this->ingressDeployment();
        $step = new IngressStep();
        $step->startDeployCommand($deployment);

        $this->assertSame([['loadBalancer' => []]], $step->getKubernetesStatus($deployment));
    }

    public function testTheIngressEventsAreItsOwnAndNobodyElses(): void {
        $deployment = $this->ingressDeployment();
        $step = new IngressStep();
        $step->startDeployCommand($deployment);

        $this->assertSame([], $step->getKubernetesEvents($deployment));

        $this->emitEventFor($this->firstResource($step, $deployment), 'Sync', 'scheduled for sync');

        $this->assertSame(
            [['type' => 'Warning', 'reason' => 'Sync', 'from' => 'kso-test', 'message' => 'scheduled for sync']],
            $this->eventSummaries($step->getKubernetesEvents($deployment))
        );
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * A deployment behind a gateway, in this test's namespace. All three steps refuse to
     * build anything without the workspace, its domain and the domain's gateway.
     *
     * @param array<string, mixed> $domain
     * @param array<string, mixed> $workspace
     */
    private function routableDeployment(
        string $networkType = \NetworkTypes::GatewayApi,
        array $domain = [],
        array $workspace = [],
        bool $withRoute = true,
        bool $applyNamespace = true
    ): Deployment {
        $gateway = Fixtures::gateway();
        $domainRow = Fixtures::domain(array_merge(['gateway_id' => $gateway->id], $domain));
        $deployment = $this->deploymentInTheTestNamespace(
            [],
            array_merge(['domain_id' => $domainRow->id], $workspace)
        );

        $specification = $deployment->findDeploymentSpecification();
        $specification->network_type = $networkType;
        $specification->save();

        // The routes on the specification are what every one of these steps turns into
        // rules. Without one there is nothing to route - see the tests that say so.
        if ($withRoute) {
            Fixtures::httpProxyRoute([
                'deployment_specification_id' => $deployment->deployment_specification_id,
                'path' => '/',
                'port' => 80,
                // What the UI sets for a Contour specification, and the only kind of value
                // Contour's schema takes. Empty everywhere else.
                'protocol' => $networkType === \NetworkTypes::Contour ? 'h2c' : '',
            ]);
        }

        // Left out by the tests that want to see a step refuse until the namespace is
        // there, since that is the last thing every one of them checks.
        if ($applyNamespace) {
            (new NamespaceStep())->startDeployCommand($deployment);
        }

        return $deployment;
    }

    /**
     * A workspace on a domain Contour serves. The two domain fields are what
     * `validateDeployCommand()` insists on, and neither is on by default.
     */
    private function contourDeployment(bool $applyNamespace = true): Deployment {
        return $this->routableDeployment(
            \NetworkTypes::Contour,
            ['enable_contour' => true, 'contour_ingress_class_name' => 'contour'],
            applyNamespace: $applyNamespace
        );
    }

    /**
     * A workspace on a domain with an Istio gateway, which is what the VirtualService step
     * insists on before it will run.
     */
    private function istioDeployment(bool $applyNamespace = true): Deployment {
        $deployment = $this->routableDeployment(
            \NetworkTypes::Istio,
            ['enable_istio_gateway' => true],
            applyNamespace: $applyNamespace
        );

        // The step asks for a Service, and a Service with no ports is refused by the api
        // server - so the port is part of arranging the thing the step is waiting for.
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'http',
            'port' => 80,
            'target_port' => 80,
        ]);

        return $deployment;
    }

    /**
     * A deployment served by an nginx ingress: a port on the service, a rule pointing at
     * it, and the Service applied - which the step insists on before it will run.
     *
     * @param array<string, mixed> $ingress
     */
    private function ingressDeployment(array $ingress = [], bool $applyService = true, bool $applyNamespace = true): Deployment {
        $deployment = $this->routableDeployment(
            \NetworkTypes::NginxIngress,
            [],
            [],
            withRoute: false,
            applyNamespace: $applyNamespace
        );

        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'http',
            'port' => 80,
            'target_port' => 80,
        ]);
        $definition = Fixtures::ingress(array_merge(
            ['deployment_specification_id' => $deployment->deployment_specification_id],
            $ingress
        ));
        Fixtures::ingressRulePath([
            'deployment_specification_ingress_id' => $definition->id,
            'path' => '/',
            'path_type' => 'Prefix',
            'backend_service_port_name' => 'http',
        ]);

        if ($applyService) {
            (new ServiceStep())->startDeployCommand($deployment);
        }

        return $deployment;
    }

    /**
     * A second ingress on the same specification, so a step that builds a list builds more
     * than one.
     */
    private function secondIngressOn(Deployment $deployment): void {
        $definition = Fixtures::ingress(['deployment_specification_id' => $deployment->deployment_specification_id]);
        Fixtures::ingressRulePath([
            'deployment_specification_ingress_id' => $definition->id,
            'path' => '/second',
            'path_type' => 'Prefix',
            'backend_service_port_name' => 'http',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function preview(object $step, Deployment $deployment): array {
        return json_decode($step->getPreview($deployment), true);
    }

    /**
     * The first resource a step would apply, on the cluster. Every one of these builders is
     * protected, which is what `ManifestTestCase` reaches through as well - here it is
     * needed with `$auth = true`, so the resource can be written to.
     */
    private function firstResource(object $step, Deployment $deployment): \RenokiCo\PhpK8s\Kinds\K8sResource {
        $method = new \ReflectionMethod($step, method_exists($step, 'getResources') ? 'getResources' : 'getResource');

        $built = $method->invoke($step, $deployment, true);

        return is_array($built) ? $built[0] : $built;
    }

    /**
     * An event against a resource, as a controller would write one.
     *
     * Nothing is running in this cluster to produce one, and the loop that turns events
     * into rows is the part of each step worth holding: it reads six fields off an event
     * and one of them - `source.component` - is an array index that is not always there.
     * `newEvent()` does not set a namespace of its own, and an event lands in `default`
     * without it, where the resource's own namespace query will never find it.
     */
    private function emitEventFor(\RenokiCo\PhpK8s\Kinds\K8sResource $resource, string $reason, string $message): void {
        $resource->newEvent()
            ->setNamespace($resource->getNamespace())
            ->setAttribute('type', 'Warning')
            ->setAttribute('reason', $reason)
            ->setAttribute('message', $message)
            ->setAttribute('count', 1)
            ->setAttribute('lastTimestamp', gmdate('Y-m-d\TH:i:s\Z'))
            ->setAttribute('source', ['component' => 'kso-test'])
            ->createOrUpdate();
    }

    /**
     * The parts of an event row that are ours rather than the cluster's. `date` is left out
     * on purpose: it is the timestamp the api server stored, reformatted.
     *
     * @param array<array<string, mixed>> $events
     * @return array<array<string, mixed>>
     */
    private function eventSummaries(array $events): array {
        return array_map(
            fn (array $event) => [
                'type' => $event['type'],
                'reason' => $event['reason'],
                'from' => $event['from'],
                'message' => $event['message'],
            ],
            $events
        );
    }

    /**
     * Write a status onto a resource the way its controller would.
     *
     * A status lives on a subresource of its own, so an ordinary update never touches it -
     * which is why a resource in this cluster has whatever status its definition defaults
     * to and no more. This is the only way to ask what a step does once one is there.
     *
     * @param array<string, mixed> $status
     */
    private function writeStatus(string $path, array $status): void {
        $stored = $this->get($path);
        $stored['status'] = $status;

        $this->cluster()->call('PUT', $path . '/status', json_encode($stored));
    }

    /**
     * Read a custom resource back as the api server stored it.
     *
     * php-k8s has no typed accessor for a kind it does not know, and the stored document is
     * what matters here anyway: a field the schema rejected would not be in it.
     *
     * @return array<string, mixed>
     */
    private function namespacedResource(string $groupVersionPlural, string $name): array {
        [$group, $version, $plural] = explode('/', $groupVersionPlural);

        return $this->get("/apis/{$group}/{$version}/namespaces/{$this->testNamespace}/{$plural}/{$name}");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * @return array<array<string, mixed>>
     */
    private function httpProxiesIn(string $namespace): array {
        return $this->get("/apis/projectcontour.io/v1/namespaces/{$namespace}/httpproxies")['items'] ?? [];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function routesIn(string $namespace): array {
        return $this->get("/apis/gateway.networking.k8s.io/v1/namespaces/{$namespace}/httproutes")['items'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array {
        return json_decode(
            $this->cluster()->call('GET', $path)->getBody()->getContents(),
            true
        );
    }

    // </editor-fold>

}
