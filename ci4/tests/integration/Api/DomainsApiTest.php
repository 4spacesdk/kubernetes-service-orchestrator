<?php namespace App\Tests\Integration\Api;

use App\ClusterControllerTestCase;
use App\Entities\Domain;
use App\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The certificate and Istio buttons on a domain's page.
 *
 * Each is a shell around `KubeCertificate` or `KubeIstioGateway`, and the shells do the
 * formatting an operator actually reads: dates parsed out of cert-manager's status, the
 * condition list, the event list. None of that can be reached without a cluster.
 *
 * cert-manager's definitions are installed in the test cluster; its controller is not, so
 * nothing is ever issued. That is the honest limit and it shapes what these say.
 */
class DomainsApiTest extends ClusterControllerTestCase {

    public function testApplyingACertificateCreatesIt(): void {
        $domain = $this->domainInTheTestNamespace();

        $body = $this->decode($this->signedIn()->put("domains/{$domain->id}/certificate/apply"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame(
            ['*.test.example.org', 'test.example.org'],
            $this->certificate($domain)['spec']['dnsNames']
        );
    }

    public function testApplyingTwiceUpdatesRatherThanFails(): void {
        $domain = $this->domainInTheTestNamespace(['issuer_ref_name' => 'staging']);
        $this->signedIn()->put("domains/{$domain->id}/certificate/apply");

        $domain->issuer_ref_name = 'production';
        $domain->save();
        $body = $this->decode($this->signedIn()->put("domains/{$domain->id}/certificate/apply"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame('production', $this->certificate($domain)['spec']['issuerRef']['name']);
    }

    /**
     * Nothing has happened to the certificate, so the list is empty. The endpoint maps
     * every event through `$event->getAttribute('source')['component']`, which would fail
     * on an event without a source - worth knowing that the empty case at least is safe.
     */
    public function testTheEventListIsEmptyForACertificateNothingHasTouched(): void {
        $domain = $this->domainInTheTestNamespace();
        $this->signedIn()->put("domains/{$domain->id}/certificate/apply");

        $body = $this->decode($this->signedIn()->get("domains/{$domain->id}/certificate/events"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame([], $body['resources']);
    }

    /**
     * **Today's behaviour, and it is FEAT-17 seen from the endpoint.** cert-manager writes
     * the status, and its controller is not installed - so `getStatus()` returns null from
     * a method declared to return an array, and the page dies. The same happens in any
     * cluster where cert-manager is missing or has not got to this certificate yet.
     */
    public function testTheStatusPanelDiesUntilCertManagerHasWrittenAStatus(): void {
        $domain = $this->domainInTheTestNamespace();
        $this->signedIn()->put("domains/{$domain->id}/certificate/apply");

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must be of type array, null returned');

        $this->signedIn()->get("domains/{$domain->id}/certificate/status");
    }

    /**
     * The panel with something in it, which is the half the cluster cannot produce by
     * itself: nothing runs cert-manager here, so the status is written straight to the
     * subresource and the endpoint is asked what it makes of it.
     *
     * Three things are decided in that mapping and none of them is obvious from the code.
     * The three timestamps are reformatted, so a caller reads local `Y-m-d H:i:s` rather
     * than cert-manager's RFC 3339; the condition list is rebuilt field by field, so a
     * field added upstream does not appear here until someone adds it; and `reason` and
     * `message` are read without a fallback, which is what makes the condition below carry
     * both.
     */
    public function testTheStatusPanelReformatsWhatCertManagerWrote(): void {
        $domain = $this->domainInTheTestNamespace();
        $this->signedIn()->put("domains/{$domain->id}/certificate/apply");

        $this->writeCertificateStatus($domain, [
            'notBefore' => '2026-01-02T03:04:05Z',
            'notAfter' => '2026-04-02T03:04:05Z',
            'renewalTime' => '2026-03-03T03:04:05Z',
            'conditions' => [
                [
                    'type' => 'Ready',
                    'status' => 'True',
                    'reason' => 'Ready',
                    'message' => 'Certificate is up to date and has not expired',
                    'lastTransitionTime' => '2026-01-02T03:04:05Z',
                ],
            ],
        ]);

        $resource = $this->decode($this->signedIn()->get("domains/{$domain->id}/certificate/status"))['resource'];

        $this->assertSame(date('Y-m-d H:i:s', strtotime('2026-04-02T03:04:05Z')), $resource['notAfter']);
        $this->assertSame(date('Y-m-d H:i:s', strtotime('2026-01-02T03:04:05Z')), $resource['notBefore']);
        $this->assertSame(date('Y-m-d H:i:s', strtotime('2026-03-03T03:04:05Z')), $resource['renewalTime']);

        $this->assertSame([
            [
                'type' => 'Ready',
                'status' => 'True',
                'reason' => 'Ready',
                'lastTransitionTime' => date('Y-m-d H:i:s', strtotime('2026-01-02T03:04:05Z')),
                'message' => 'Certificate is up to date and has not expired',
            ],
        ], $resource['conditions']);
    }

    /**
     * A certificate that was never applied has no status at all, and that path *is*
     * guarded - so the panel answers with empty dates rather than dying. The difference
     * between this and the test above is one `exists()` call inside `KubeCertificate`.
     */
    public function testTheStatusPanelIsEmptyWhenNothingWasApplied(): void {
        $domain = $this->domainInTheTestNamespace();

        $body = $this->decode($this->signedIn()->get("domains/{$domain->id}/certificate/status"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame([], $body['resource']['conditions']);
    }

    /**
     * The event panel with an event in it. Nothing issues a certificate here, so the event
     * is put in the namespace by hand - which is also the only way to say what the mapping
     * does, since the endpoint rewrites every one of the five fields it shows.
     *
     * `source.component` is the one worth the trouble: the endpoint reads it as
     * `getAttribute('source')['component']` with no guard at all, so an event without a
     * source is an `Undefined array key` rather than a missing column. Kubernetes writes
     * one on everything it emits, which is why nobody has hit it.
     */
    public function testTheEventListCarriesWhatTheClusterSaidAboutTheCertificate(): void {
        $domain = $this->domainInTheTestNamespace();
        $this->signedIn()->put("domains/{$domain->id}/certificate/apply");

        $this->emitCertificateEvent($domain, [
            'type' => 'Warning',
            'reason' => 'IssuerNotFound',
            'message' => 'Issuer test-issuer not found',
            'lastTimestamp' => '2026-01-02T03:04:05Z',
            'source' => ['component' => 'cert-manager-certificates-issuing'],
        ]);

        $resources = $this->decode($this->signedIn()->get("domains/{$domain->id}/certificate/events"))['resources'];

        $this->assertSame([
            [
                'type' => 'Warning',
                'reason' => 'IssuerNotFound',
                'age' => date('Y-m-d H:i:s', strtotime('2026-01-02T03:04:05Z')),
                'from' => 'cert-manager-certificates-issuing',
                'message' => 'Issuer test-issuer not found',
            ],
        ], $resources);
    }

    /**
     * Every one of these follows the `if ($item->exists())` shape, so an unknown id is
     * answered with success and an empty resource rather than a refusal. That is FEAT-9,
     * and it is the opposite of what the Gateways endpoints do.
     */
    #[DataProvider('theCertificateEndpoints')]
    public function testAnUnknownDomainIsAnsweredWithSuccess(string $method, string $path): void {
        $body = $this->decode($this->signedIn()->$method("domains/999999/{$path}"));

        $this->assertSame('OK', $body['status']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function theCertificateEndpoints(): array {
        return [
            'apply' => ['put', 'certificate/apply'],
            'events' => ['get', 'certificate/events'],
            'status' => ['get', 'certificate/status'],
        ];
    }

    // <editor-fold desc="Istio gateway">

    public function testApplyingTheIstioGatewayCreatesIt(): void {
        $domain = $this->domainInTheTestNamespace();

        $body = $this->decode($this->signedIn()->put("domains/{$domain->id}/istio-gateway/apply"));

        $this->assertSame('OK', $body['status']);
        $this->assertNotSame([], $this->istioGateways());
    }

    /**
     * What an operator sees when the cluster refuses. The namespace does not exist, so the
     * gateway cannot be created - and this is the one endpoint pair in the file that turns
     * that into a message: `KubeIstioGateway::apply()` returns the text of the exception
     * instead of throwing, and the controller checks for a string before answering.
     *
     * The certificate endpoints next door do not. `KubeCertificate::apply()` catches the
     * same exception, hands it to `KubeHelper::PrintException()` and throws the result away,
     * so applying a certificate into a namespace that is not there is reported as success.
     */
    public function testAClusterRefusalComesBackAsAMessage(): void {
        $domain = $this->domainInTheTestNamespace([
            'certificate_namespace' => $this->testNamespace . '-not-a-namespace',
        ]);

        $body = $this->decode($this->signedIn()->put("domains/{$domain->id}/istio-gateway/apply"));

        $this->assertSame('ERROR', $body['status']);
        $this->assertNotSame('', $body['error']);
    }

    /**
     * Today's behaviour, and the second half of FEAT-17: `KubeIstioGateway::delete()` has
     * the same missing `synced()` as the certificate class, so terminating reports success
     * and leaves the gateway where it was.
     */
    public function testTerminatingTheIstioGatewayLeavesItBehind(): void {
        $domain = $this->domainInTheTestNamespace();
        $this->signedIn()->put("domains/{$domain->id}/istio-gateway/apply");

        $body = $this->decode($this->signedIn()->put("domains/{$domain->id}/istio-gateway/terminate"));

        $this->assertSame('OK', $body['status']);
        $this->assertNotSame([], $this->istioGateways(), 'it said it removed it, and it did not');
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
            'issuer_ref_name' => 'test-issuer',
        ], $overrides));
    }

    /**
     * Write the certificate's status the way cert-manager would.
     *
     * The CRD declares a `status` subresource, so the field is ignored on an ordinary write
     * and has to go to `/status` on its own. The object is read back first because that
     * endpoint takes a whole resource and rejects one without the current
     * `metadata.resourceVersion`.
     *
     * @param array<string, mixed> $status
     */
    private function writeCertificateStatus(Domain $domain, array $status): void {
        $path = "/apis/cert-manager.io/v1/namespaces/{$this->testNamespace}/certificates/{$domain->certificate_name}";

        $certificate = $this->fromCluster($path);
        $certificate['status'] = $status;

        $this->cluster()->call('PUT', $path . '/status', json_encode($certificate));
    }

    /**
     * Put one event in the namespace, pointing at the certificate.
     *
     * `getEvents()` finds it by a field selector on `involvedObject.kind` and
     * `involvedObject.name`, so those two are what make the event visible to the endpoint -
     * the namespace of the event itself is what scopes the query.
     *
     * @param array<string, mixed> $event
     */
    private function emitCertificateEvent(Domain $domain, array $event): void {
        $this->cluster()->call(
            'POST',
            "/api/v1/namespaces/{$this->testNamespace}/events",
            json_encode(array_merge([
                'apiVersion' => 'v1',
                'kind' => 'Event',
                'metadata' => [
                    'name' => $domain->certificate_name . '.' . bin2hex(random_bytes(8)),
                    'namespace' => $this->testNamespace,
                ],
                'involvedObject' => [
                    'apiVersion' => 'cert-manager.io/v1',
                    'kind' => 'Certificate',
                    'name' => $domain->certificate_name,
                    'namespace' => $this->testNamespace,
                ],
            ], $event))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function certificate(Domain $domain): array {
        return $this->fromCluster(
            "/apis/cert-manager.io/v1/namespaces/{$this->testNamespace}/certificates/{$domain->certificate_name}"
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function istioGateways(): array {
        return $this->fromCluster("/apis/networking.istio.io/v1/namespaces/{$this->testNamespace}/gateways")['items'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromCluster(string $path): array {
        return json_decode($this->cluster()->call('GET', $path)->getBody()->getContents(), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
