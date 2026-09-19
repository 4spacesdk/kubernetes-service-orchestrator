<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Gateway;
use App\Fixtures;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\GatewaySteps\GatewayStep;

/**
 * The gateway itself: the thing every workspace's route attaches to.
 *
 * It is one step, but it builds three kinds across two namespaces - a Gateway, a
 * ReferenceGrant, and a redirect HTTPRoute - and none of it was under test. A gateway that
 * comes out wrong does not break one workspace; it breaks every workspace behind it, and
 * the failures are the kind that only appear in a browser.
 *
 * The cross namespace part is what needs a real api server most. A Gateway may only read a
 * certificate from another namespace when that namespace has granted it, and the grant is
 * a resource of its own with its own schema. Whether kso writes it, where, and how many
 * times cannot be answered by looking at one manifest.
 */
class GatewayStepTest extends ClusterTestCase {

    // <editor-fold desc="Listeners">

    /**
     * The explicit `$gateway->domains->find()` in `getAllResources()` is not what loads the
     * domains: the relation loads itself on first use, so removing the call changes
     * nothing any of these tests can see.
     */
    public function testTheGatewayGetsAnHttpListenerAndTwoHttpsListenersPerDomain(): void {
        $gateway = $this->gatewayWithDomain();
        $step = new GatewayStep();

        $this->assertSame('not-found', $step->getStatus($gateway));

        $step->deploy($gateway);

        $listeners = $this->gatewayOnCluster($gateway)['spec']['listeners'];
        $this->assertSame(
            ['http', 'https-test-example-org', 'https-wildcard-test-example-org'],
            array_column($listeners, 'name')
        );
        $this->assertSame('test.example.org', $listeners[1]['hostname']);
        $this->assertSame('*.test.example.org', $listeners[2]['hostname'], 'the apex is not matched by the wildcard');
        $this->assertSame('found', $step->getStatus($gateway));
    }

    /**
     * The gateway class is what decides which controller picks the gateway up. Wrong, and
     * nothing happens at all - no error, no listener, no traffic.
     */
    public function testTheGatewayClassIsWhatWasConfigured(): void {
        $gateway = $this->gatewayWithDomain([], ['gateway_class_name' => 'istio']);

        (new GatewayStep())->deploy($gateway);

        $this->assertSame('istio', $this->gatewayOnCluster($gateway)['spec']['gatewayClassName']);
    }

    /**
     * A domain with no certificate gets no https listener - there would be nothing to
     * terminate tls with. It still gets the shared http one.
     */
    public function testADomainWithoutACertificateGetsNoHttpsListener(): void {
        $gateway = $this->gatewayWithDomain(['certificate_name' => '']);

        (new GatewayStep())->deploy($gateway);

        $this->assertSame(['http'], array_column($this->gatewayOnCluster($gateway)['spec']['listeners'], 'name'));
    }

    /**
     * An address pins the gateway to an ip that has already been reserved, rather than
     * letting the cloud hand out a new one - which is what keeps dns pointing at something
     * that exists after a gateway is rebuilt.
     */
    public function testAReservedAddressIsCarriedThrough(): void {
        $gateway = $this->gatewayWithDomain();
        Fixtures::gatewayAddress(['gateway_id' => $gateway->id, 'type' => 'IPAddress', 'value' => '10.0.0.9']);

        (new GatewayStep())->deploy($gateway);

        $this->assertSame(
            [['type' => 'IPAddress', 'value' => '10.0.0.9']],
            $this->gatewayOnCluster($gateway)['spec']['addresses']
        );
    }

    /**
     * kso marks everything it applies. Without the annotation there is nothing in a shared
     * cluster that tells a gateway kso owns from one somebody created by hand, which is
     * what every cleanup and every audit goes by.
     */
    public function testTheGatewayIsMarkedAsManagedByKso(): void {
        $gateway = $this->gatewayWithDomain();

        (new GatewayStep())->deploy($gateway);

        $this->assertSame(
            '4spaces.kso',
            $this->gatewayOnCluster($gateway)['metadata']['annotations']['app.kubernetes.io/managed-by'] ?? null
        );
    }

    // </editor-fold>

    // <editor-fold desc="Reference grants">

    /**
     * The permission that makes cross namespace tls work. Without it the api server accepts
     * the Gateway and its controller then refuses to read the secret - so the listener
     * exists and serves nothing.
     */
    public function testACertificateInAnotherNamespaceGetsAGrant(): void {
        $certificates = $this->anotherNamespace('certs');
        $gateway = $this->gatewayWithDomain(['certificate_namespace' => $certificates]);

        (new GatewayStep())->deploy($gateway);

        $grant = $this->grantOnCluster("kso-{$gateway->name}-grant", $certificates);
        $this->assertSame($this->testNamespace, $grant['spec']['from'][0]['namespace']);
        $this->assertSame('Gateway', $grant['spec']['from'][0]['kind']);
        $this->assertSame('Secret', $grant['spec']['to'][0]['kind']);
    }

    /**
     * A certificate in the gateway's own namespace needs no permission, and writing one
     * would be a resource nobody asked for in a namespace kso does not own.
     */
    /**
     * A grant needs both halves of the reference. A namespace on its own names no secret,
     * and writing a grant for it would hand a foreign namespace's secrets to the gateway
     * on the strength of a field nobody filled in.
     */
    public function testACertificateNamespaceWithoutANameGetsNoGrant(): void {
        $certificates = $this->anotherNamespace('certs');
        $gateway = $this->gatewayWithDomain([
            'certificate_name' => '',
            'certificate_namespace' => $certificates,
        ]);

        (new GatewayStep())->deploy($gateway);

        $this->assertSame([], $this->grantsIn($certificates));
    }

    public function testACertificateInTheGatewaysOwnNamespaceGetsNoGrant(): void {
        $gateway = $this->gatewayWithDomain(['certificate_namespace' => $this->testNamespace]);

        (new GatewayStep())->deploy($gateway);

        $this->assertSame([], $this->grantsIn($this->testNamespace));
    }

    /**
     * Two domains sharing a certificate namespace get **one** grant, not two with the same
     * name - the second would be an update of the first, and the loop is written to notice.
     */
    public function testTwoDomainsInOneNamespaceShareASingleGrant(): void {
        $certificates = $this->anotherNamespace('certs');
        $gateway = $this->gatewayWithDomain(['certificate_namespace' => $certificates]);
        Fixtures::domain([
            'gateway_id' => $gateway->id,
            'name' => 'other.example.org',
            'certificate_name' => 'other-cert',
            'certificate_namespace' => $certificates,
        ]);
        $step = new GatewayStep();

        $step->deploy($gateway);

        $this->assertCount(1, $this->grantsIn($certificates));

        // The cluster alone cannot tell one grant from two: both would carry the same name,
        // so the second is an update of the first and the namespace holds one object either
        // way. What the loop actually built is only visible before it is sent.
        $this->assertCount(
            2,
            json_decode($step->getPreview($gateway), true)['local'],
            'the gateway and a single grant, not one grant per domain'
        );
    }

    // </editor-fold>

    // <editor-fold desc="The https redirect route">

    public function testHttpsRedirectAddsARouteOnTheHttpListener(): void {
        $gateway = $this->gatewayWithDomain(['https_redirect' => true]);

        (new GatewayStep())->deploy($gateway);

        $route = $this->routeOnCluster('kso-redirect-test-example-org');
        $this->assertSame($gateway->name, $route['spec']['parentRefs'][0]['name'], 'it hangs off the gateway, not the domain');
        $this->assertSame($gateway->namespace, $route['spec']['parentRefs'][0]['namespace']);
        $this->assertSame('http', $route['spec']['parentRefs'][0]['sectionName'], 'it redirects what arrives on port 80');
        $this->assertSame(['test.example.org', '*.test.example.org'], $route['spec']['hostnames']);
        $redirect = $route['spec']['rules'][0]['filters'][0]['requestRedirect'];
        $this->assertSame('https', $redirect['scheme']);
        $this->assertSame(301, $redirect['statusCode']);
    }

    public function testWithoutHttpsRedirectThereIsNoRedirectRoute(): void {
        $gateway = $this->gatewayWithDomain(['https_redirect' => false]);

        (new GatewayStep())->deploy($gateway);

        $this->assertSame([], $this->routesIn($this->testNamespace));
    }

    // </editor-fold>

    // <editor-fold desc="Status, preview and terminate">

    /**
     * Terminating takes the grant in the other namespace with it. Left behind, it is a
     * standing permission to read secrets, granted to a gateway that no longer exists.
     *
     * The order of the deletes is not asserted, and cannot usefully be: the three resources
     * have no ownership between them, so removing `array_reverse()` leaves the cluster in
     * the same state - the reversal is there so the gateway outlives what points at it,
     * which no api server check enforces.
     */
    public function testTerminatingRemovesEverythingInBothNamespaces(): void {
        $certificates = $this->anotherNamespace('certs');
        $gateway = $this->gatewayWithDomain(['certificate_namespace' => $certificates, 'https_redirect' => true]);
        $step = new GatewayStep();
        $step->deploy($gateway);

        $step->terminate($gateway);

        $this->eventually(fn () => $step->getStatus($gateway) === 'not-found');
        $this->eventually(fn () => $this->grantsIn($certificates) === []);
        $this->eventually(fn () => $this->routesIn($this->testNamespace) === []);
    }

    /**
     * Terminating a gateway that was never deployed is the ordinary case when one is
     * created and removed again, and every delete is guarded by `exists()`.
     */
    public function testTerminatingSomethingThatWasNeverDeployedIsQuiet(): void {
        $gateway = $this->gatewayWithDomain();

        (new GatewayStep())->terminate($gateway);

        $this->assertSame('not-found', (new GatewayStep())->getStatus($gateway));
    }

    public function testThePreviewShowsWhatIsThereAndWhatWouldBeSent(): void {
        $gateway = $this->gatewayWithDomain(['https_redirect' => true]);
        $step = new GatewayStep();

        $before = json_decode($step->getPreview($gateway), true);
        $this->assertCount(2, $before['local'], 'the gateway and its redirect route');
        $this->assertSame([], $before['remote'], 'nothing is applied yet');

        $step->deploy($gateway);

        $after = json_decode($step->getPreview($gateway), true);
        $this->assertCount(2, $after['remote']);
        $this->assertArrayNotHasKey('uid', json_decode($after['remote'][0], true)['metadata']);
        $this->assertArrayNotHasKey('status', json_decode($after['remote'][0], true));
    }

    /**
     * The status the UI polls. The api server writes one itself the moment the gateway is
     * created - Accepted and Programmed, both Unknown, "Waiting for controller" - and that
     * is what a user sees until a controller adopts the gateway. Nothing in kso produces
     * those words, which is exactly why they are worth pinning: they are the normal state
     * of a gateway in a cluster whose controller is missing or misconfigured.
     */
    public function testTheStatusSaysItIsWaitingForAControllerUntilOneTurnsUp(): void {
        $gateway = $this->gatewayWithDomain();
        $step = new GatewayStep();

        $this->assertSame([], $step->getKubernetesStatus($gateway), 'nothing applied, nothing to report');

        $step->deploy($gateway);

        $conditions = $step->getKubernetesStatus($gateway)['conditions'];
        $this->assertSame(['Accepted', 'Programmed'], array_column($conditions, 'type'));
        $this->assertSame(['Unknown', 'Unknown'], array_column($conditions, 'status'));
        $this->assertSame('Waiting for controller', $conditions[0]['message']);
    }

    /**
     * A gateway that has not been applied reports nothing, whatever is in the namespace.
     *
     * The guard matters more than it looks. php-k8s asks for events with a field selector
     * on the involved object's kind **and** name, and only the kind half survives the way
     * the query is encoded - so without the guard the panel of an unapplied gateway would
     * show the events of every Gateway in the namespace. The event written here is what
     * makes the difference between the two visible; there is one either way once the
     * gateway is out there.
     */
    public function testTheEventsPanelSaysNothingUntilTheGatewayIsApplied(): void {
        $gateway = $this->gatewayWithDomain();
        $step = new GatewayStep();
        $this->emitGatewayEvent($gateway, 'Programmed');

        $this->assertSame([], $step->getKubernetesEvents($gateway), 'nothing is applied yet');

        $step->deploy($gateway);

        $this->assertSame(['Programmed'], array_column($step->getKubernetesEvents($gateway), 'reason'));
    }

    /**
     * **Today's behaviour, pinned rather than endorsed.** The panel of one gateway shows
     * the events of every Gateway in its namespace, because the name half of php-k8s'
     * field selector never reaches the api server - see the comment on
     * `getKubernetesEvents()`. The namespace half does work, which is what the second
     * assertion holds down: an event in another namespace stays out.
     */
    public function testTheEventsPanelStillShowsAnotherGatewaysEvents(): void {
        $gateway = $this->gatewayWithDomain();
        $step = new GatewayStep();
        $step->deploy($gateway);

        $neighbour = $this->aGatewayCalled($gateway, 'kso-other-gateway', $gateway->namespace);
        $this->emitGatewayEvent($neighbour, 'SomebodyElses');
        $elsewhere = $this->aGatewayCalled($gateway, $gateway->name, $this->anotherNamespace('events'));
        $this->emitGatewayEvent($elsewhere, 'InAnotherNamespace');

        $this->assertSame(
            ['SomebodyElses'],
            array_column($step->getKubernetesEvents($gateway), 'reason'),
            'the name filter does not apply, the namespace filter does'
        );
    }

    // </editor-fold>

    /**
     * A gateway in this test's namespace, with one domain hanging off it.
     *
     * @param array<string, mixed> $domain
     * @param array<string, mixed> $gateway
     */
    private function gatewayWithDomain(array $domain = [], array $gateway = []): Gateway {
        $row = Fixtures::gateway(array_merge([
            'name' => 'kso-gateway',
            'namespace' => $this->testNamespace,
        ], $gateway));

        Fixtures::domain(array_merge([
            'gateway_id' => $row->id,
            'name' => 'test.example.org',
            'certificate_name' => 'test-cert',
            'certificate_namespace' => $this->testNamespace,
        ], $domain));

        $this->createTheNamespace();

        return $row;
    }

    /**
     * The gateway's own namespace has to exist before anything is applied into it. The
     * namespace step works off a deployment, so it gets one pointed at the same namespace.
     */
    private function createTheNamespace(): void {
        (new NamespaceStep())->startDeployCommand($this->deploymentInTheTestNamespace());
    }

    /**
     * A stand-in for a second gateway, used only as the target of an event. Nothing is
     * applied for it - an event may point at an object that does not exist, and the panel
     * is built from the event list rather than from the objects.
     */
    private function aGatewayCalled(Gateway $template, string $name, string $namespace): Gateway {
        $other = clone $template;
        $other->name = $name;
        $other->namespace = $namespace;

        return $other;
    }

    /**
     * One event in the gateway's namespace, pointing at the gateway by kind and name.
     */
    private function emitGatewayEvent(Gateway $gateway, string $reason): void {
        $this->cluster()->call(
            'POST',
            "/api/v1/namespaces/{$gateway->namespace}/events",
            json_encode([
                'apiVersion' => 'v1',
                'kind' => 'Event',
                'metadata' => [
                    'name' => $gateway->name . '.' . bin2hex(random_bytes(8)),
                    'namespace' => $gateway->namespace,
                ],
                'involvedObject' => [
                    'apiVersion' => 'gateway.networking.k8s.io/v1',
                    'kind' => 'Gateway',
                    'name' => $gateway->name,
                    'namespace' => $gateway->namespace,
                ],
                'type' => 'Normal',
                'count' => 1,
                'reason' => $reason,
                'message' => 'written by the test, the cluster has no gateway controller',
                'source' => ['component' => 'kso-test'],
                'lastTimestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function gatewayOnCluster(Gateway $gateway): array {
        return $this->get("/apis/gateway.networking.k8s.io/v1/namespaces/{$gateway->namespace}/gateways/{$gateway->name}");
    }

    /**
     * @return array<string, mixed>
     */
    private function grantOnCluster(string $name, string $namespace): array {
        return $this->get("/apis/gateway.networking.k8s.io/v1beta1/namespaces/{$namespace}/referencegrants/{$name}");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function grantsIn(string $namespace): array {
        return $this->get("/apis/gateway.networking.k8s.io/v1beta1/namespaces/{$namespace}/referencegrants")['items'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function routeOnCluster(string $name): array {
        return $this->get("/apis/gateway.networking.k8s.io/v1/namespaces/{$this->testNamespace}/httproutes/{$name}");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function routesIn(string $namespace): array {
        return $this->get("/apis/gateway.networking.k8s.io/v1/namespaces/{$namespace}/httproutes")['items'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array {
        return json_decode($this->cluster()->call('GET', $path)->getBody()->getContents(), true);
    }

}
