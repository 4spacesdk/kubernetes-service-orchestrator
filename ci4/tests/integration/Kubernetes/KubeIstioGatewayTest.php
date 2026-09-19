<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Domain;
use App\Fixtures;
use App\Libraries\Kubernetes\KubeIstioGateway;
use RenokiCo\PhpK8s\ResourcesList;

/**
 * The Istio Gateway kso applies on a domain's behalf.
 *
 * `DomainsApiTest` drives `apply()` and `delete()` through their endpoints, which is where
 * the error handling is worth checking. This is the class itself: the manifest it builds -
 * the listeners, the redirect, the credential name the certificate is read under - and the
 * two reading methods the endpoints never call.
 *
 * **`getEvents()` and `getStatus()` have no call sites in the application.** Nothing under
 * `app/` calls them, and the domain's page offers the two panels for a certificate only.
 * They are tested here rather than left out because they are public, they compile, and a
 * page that started using them would be the wrong moment to find out what they answer -
 * which, in `getStatus()`'s case, is a fatal error. See the test.
 *
 * Istio's definitions are installed in the test cluster; its controller is not, so nothing
 * ever adopts the gateway. That is the honest limit and it shapes what these say.
 */
class KubeIstioGatewayTest extends ClusterTestCase {

    // <editor-fold desc="The manifest">

    /**
     * Two servers per domain: port 80 redirecting to https, and port 443 terminating tls
     * with the certificate. The apex is not in the host list - only the wildcard is - so a
     * request to the domain itself is not matched by this gateway at all.
     */
    public function testTheGatewayRedirectsPlainHttpAndTerminatesTlsOnTheWildcard(): void {
        $domain = $this->domainInTheTestNamespace();

        (new KubeIstioGateway($domain))->apply($this->cluster());

        $servers = $this->gatewayOnCluster($domain)['spec']['servers'];

        $this->assertSame(['http', 'https'], array_column(array_column($servers, 'port'), 'name'));
        $this->assertSame([80, 443], array_column(array_column($servers, 'port'), 'number'));

        // The protocol, not the port number, is what Istio listens with: a port 80 declared
        // as HTTPS expects a tls handshake from every plain request and answers nothing.
        $this->assertSame(['HTTP', 'HTTPS'], array_column(array_column($servers, 'port'), 'protocol'));

        $this->assertSame(['*.test.example.org'], $servers[0]['hosts']);
        $this->assertSame(['*.test.example.org'], $servers[1]['hosts'], 'both listeners serve the same names');
        $this->assertTrue($servers[0]['tls']['httpsRedirect']);
        $this->assertSame('SIMPLE', $servers[1]['tls']['mode']);
    }

    /**
     * The selector is what binds the Gateway to the ingress pods that are to serve it.
     * Nothing catches a wrong one: Istio's CRD keeps unknown fields and asks for no
     * selector at all, so a misspelled key is accepted by the api server and the gateway
     * is simply served by nobody.
     */
    public function testTheGatewayIsBoundToTheIstioIngressPods(): void {
        $domain = $this->domainInTheTestNamespace();

        (new KubeIstioGateway($domain))->apply($this->cluster());

        $this->assertSame(
            ['app' => 'istio-ingressgateway', 'istio' => 'ingressgateway'],
            $this->gatewayOnCluster($domain)['spec']['selector'] ?? []
        );
    }

    /**
     * The credential name is the secret Istio reads the certificate out of, and it is the
     * domain name itself rather than the `certificate_name` on the row. Get it wrong and
     * the listener comes up with no certificate - Istio reports that on its own status,
     * which this cluster has no controller to write.
     */
    public function testTheTlsCredentialIsNamedAfterTheDomain(): void {
        $domain = $this->domainInTheTestNamespace(['certificate_name' => 'something-else']);

        (new KubeIstioGateway($domain))->apply($this->cluster());

        $this->assertSame(
            'test.example.org',
            $this->gatewayOnCluster($domain)['spec']['servers'][1]['tls']['credentialName']
        );
    }

    /**
     * It goes into the certificate's namespace, not the workspace's - the gateway has to be
     * where the secret is - and it carries a label pointing back at the domain, which is
     * what makes kso's own resources findable in a cluster it shares.
     */
    public function testItIsPlacedWithTheCertificateAndLabelledWithTheDomain(): void {
        $domain = $this->domainInTheTestNamespace();

        (new KubeIstioGateway($domain))->apply($this->cluster());

        $metadata = $this->gatewayOnCluster($domain)['metadata'];

        $this->assertSame($this->testNamespace, $metadata['namespace']);
        $this->assertSame('test-example-org', $metadata['name'], 'dots are not allowed in a name');
        $this->assertSame('4spaces.kso', $metadata['labels']['app.kubernetes.io/managed-by']);
        $this->assertSame('test.example.org', $metadata['labels']['4spaces.kso/domain-ref']);
    }

    /**
     * Applying twice is what re-saving a domain does, and the second time round the
     * resource exists - so it takes the update path rather than the create one. A create
     * against an existing name is rejected with a 409 that nothing catches.
     */
    public function testApplyingTwiceUpdatesRatherThanFails(): void {
        $domain = $this->domainInTheTestNamespace();
        $gateway = new KubeIstioGateway($domain);

        $this->assertTrue($gateway->apply($this->cluster()));
        $this->assertTrue($gateway->apply($this->cluster()));
    }

    // </editor-fold>

    // <editor-fold desc="The two methods nothing calls">

    /**
     * Nothing is reported until the gateway is out there, and then the api server's list is.
     *
     * The event is written by hand - this cluster has no Istio controller, so nothing ever
     * produces one - and it is what makes the two branches tell each other apart. Without
     * it both answer an empty list and the guard could be removed unnoticed.
     */
    public function testTheEventListIsEmptyUntilTheGatewayIsApplied(): void {
        $domain = $this->domainInTheTestNamespace();
        $gateway = new KubeIstioGateway($domain);
        $this->emitGatewayEvent($domain, 'Reconciled');

        $before = $gateway->getEvents($this->cluster());
        $this->assertInstanceOf(ResourcesList::class, $before);
        $this->assertSame(0, $before->count(), 'nothing is applied yet');

        $gateway->apply($this->cluster());

        $after = $gateway->getEvents($this->cluster());
        $this->assertSame(1, $after->count());
        $this->assertSame('Reconciled', $after->first()->getAttribute('reason'));
    }

    public function testTheStatusIsEmptyBeforeAnythingIsApplied(): void {
        $domain = $this->domainInTheTestNamespace();

        $this->assertSame([], (new KubeIstioGateway($domain))->getStatus($this->cluster()));
    }

    /**
     * Today's behaviour. An Istio Gateway has no `status`
     * subresource, so the attribute is absent and `getStatus()` - declared to return an
     * array - hands back null. The call dies on its own return type.
     *
     * Nothing calls it today, so nothing breaks. A page that started showing the panel would
     * be a fatal error on a domain that was applied successfully, which is the worst moment
     * for it. Reported, not fixed.
     */
    public function testTheStatusPanelWouldDieOnAGatewayThatIsActuallyThere(): void {
        $domain = $this->domainInTheTestNamespace();
        $gateway = new KubeIstioGateway($domain);
        $gateway->apply($this->cluster());

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must be of type array, null returned');

        $gateway->getStatus($this->cluster());
    }

    // </editor-fold>

    // <editor-fold desc="Removing it">

    /**
     * Today's behaviour, and the same bug `KubeCertificate` has. `delete()` builds the resource
     * fresh and never calls `synced()`, and php-k8s answers `delete()` on an unsynced
     * resource with `return true` before it sends anything. So it reports success, sends
     * nothing, and the gateway stays where it was.
     *
     * The `catch` in the method is unreachable for the same reason: nothing is sent, so
     * nothing can fail. Its lines stay uncovered, and that is the finding rather than a gap.
     */
    public function testDeletingReportsSuccessAndLeavesTheGatewayBehind(): void {
        $domain = $this->domainInTheTestNamespace();
        $gateway = new KubeIstioGateway($domain);
        $gateway->apply($this->cluster());

        $this->assertTrue($gateway->delete($this->cluster()));

        $this->assertNotSame([], $this->gatewayOnCluster($domain), 'it said it removed it, and it did not');
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * @param array<string, mixed> $overrides
     */
    private function domainInTheTestNamespace(array $overrides = []): Domain {
        $this->cluster()->namespace()->setName($this->testNamespace)->createOrUpdate();

        return Fixtures::domain(array_merge([
            'name' => 'test.example.org',
            'certificate_name' => 'test-cert',
            'certificate_namespace' => $this->testNamespace,
        ], $overrides));
    }

    /**
     * One event in the certificate's namespace, pointing at the Istio gateway.
     */
    private function emitGatewayEvent(Domain $domain, string $reason): void {
        $this->cluster()->call(
            'POST',
            "/api/v1/namespaces/{$domain->certificate_namespace}/events",
            json_encode([
                'apiVersion' => 'v1',
                'kind' => 'Event',
                'metadata' => [
                    'name' => $domain->getIstioGatewayName() . '.' . bin2hex(random_bytes(8)),
                    'namespace' => $domain->certificate_namespace,
                ],
                'involvedObject' => [
                    'apiVersion' => 'networking.istio.io/v1',
                    'kind' => 'Gateway',
                    'name' => $domain->getIstioGatewayName(),
                    'namespace' => $domain->certificate_namespace,
                ],
                'type' => 'Normal',
                'count' => 1,
                'reason' => $reason,
                'message' => 'written by the test, the cluster has no istio controller',
                'source' => ['component' => 'kso-test'],
                'lastTimestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function gatewayOnCluster(Domain $domain): array {
        return json_decode(
            $this->cluster()->call(
                'GET',
                "/apis/networking.istio.io/v1/namespaces/{$domain->certificate_namespace}"
                . "/gateways/{$domain->getIstioGatewayName()}"
            )->getBody()->getContents(),
            true
        );
    }

    // </editor-fold>

}
