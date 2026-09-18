<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Domain;
use App\Fixtures;
use App\Libraries\Kubernetes\KubeCertificate;

/**
 * The cert-manager Certificate kso asks for on a domain's behalf.
 *
 * This is not a deployment step - it hangs off a domain, and the three endpoints on
 * `Domains` drive it: apply, events, status. All of it is cluster work and none of it was
 * under test.
 *
 * What it asks for is a wildcard certificate plus the apex, issued by a named issuer, with
 * a set of annotations that tell whichever secret-syncing operator is installed that this
 * secret may be copied into other namespaces. That last part is the interesting one: it is
 * how one certificate ends up usable by every workspace, and it is four annotation keys
 * that have to be exactly right or nothing is copied and nothing says why.
 *
 * cert-manager's definitions are installed in the test cluster. Its controller is not, so
 * nothing is ever issued - which is the honest limit here.
 */
class KubeCertificateTest extends ClusterTestCase {

    public function testACertificateAsksForTheWildcardAndTheApex(): void {
        $domain = $this->domainInTheTestNamespace(['name' => 'example.org']);

        (new KubeCertificate($domain))->apply($this->cluster());

        $spec = $this->certificate($domain)['spec'];
        $this->assertSame(['*.example.org', 'example.org'], $spec['dnsNames']);
    }

    /**
     * The issuer decides who signs. A name that does not resolve leaves the certificate
     * pending forever, so what kso sends has to be what the operator configured.
     */
    public function testTheIssuerAndSecretAreTheOnesTheDomainNames(): void {
        $domain = $this->domainInTheTestNamespace([
            'certificate_name' => 'wildcard-cert',
            'issuer_ref_name' => 'letsencrypt-production',
        ]);

        (new KubeCertificate($domain))->apply($this->cluster());

        $spec = $this->certificate($domain)['spec'];
        $this->assertSame('letsencrypt-production', $spec['issuerRef']['name']);
        $this->assertSame('wildcard-cert', $spec['secretName'], 'the secret is named after the certificate');
    }

    /**
     * The annotations that let the issued secret be copied into the namespaces that need
     * it. They are keys belonging to two different operators, and a typo in any of them
     * means the secret stays where it was made - with every workspace's listener then
     * pointing at a secret that is not there.
     */
    public function testTheSecretIsMarkedAsSafeToCopyIntoOtherNamespaces(): void {
        $domain = $this->domainInTheTestNamespace();

        (new KubeCertificate($domain))->apply($this->cluster());

        $this->assertSame([
            'kubed.appscode.com/sync' => '',
            'reflector.v1.k8s.emberstack.com/reflection-allowed' => 'true',
            'reflector.v1.k8s.emberstack.com/reflection-allowed-namespaces' => '',
            'reflector.v1.k8s.emberstack.com/reflection-auto-enabled' => 'true',
        ], $this->certificate($domain)['spec']['secretTemplate']['annotations']);
    }

    /**
     * Applying twice is what the button does, and a certificate that already exists has to
     * be updated rather than refused.
     */
    public function testApplyingTwiceUpdatesTheCertificate(): void {
        $domain = $this->domainInTheTestNamespace(['issuer_ref_name' => 'staging']);
        $certificate = new KubeCertificate($domain);
        $certificate->apply($this->cluster());

        $domain->issuer_ref_name = 'production';
        $domain->save();
        (new KubeCertificate($domain))->apply($this->cluster());

        $this->assertSame('production', $this->certificate($domain)['spec']['issuerRef']['name']);
    }

    /**
     * **Today's behaviour: deleting does nothing at all.**
     *
     * `delete()` builds the resource from the domain's fields rather than fetching it, so
     * php-k8s has not marked it as coming from the cluster - and its `delete()` opens with
     * `if (! $this->isSynced()) return true;`. It returns success without sending a
     * request. Every deployment step calls `synced()` first; these two certificate classes
     * do not.
     *
     * Nothing calls this today, which is the only reason it has not been noticed. See
     * FEAT-17.
     */
    public function testDeletingDoesNothingAtAll(): void {
        $domain = $this->domainInTheTestNamespace();
        $certificate = new KubeCertificate($domain);
        $certificate->apply($this->cluster());

        $certificate->delete($this->cluster());

        $this->assertCount(1, $this->certificatesInTheTestNamespace(), 'it is still there');
    }

    /**
     * Nothing has been applied, so there is nothing to report. The expiry cron job runs
     * over every domain whether or not a certificate was ever asked for, and it reads this.
     */
    public function testTheStatusOfSomethingNeverAppliedIsEmpty(): void {
        $domain = $this->domainInTheTestNamespace();

        $this->assertSame([], (new KubeCertificate($domain))->getStatus($this->cluster()));
        $this->assertCount(0, (new KubeCertificate($domain))->getEvents($this->cluster()));
    }

    /**
     * **Today's behaviour, and the honest limit of this suite.** cert-manager writes the
     * status - `notAfter`, `renewalTime`, the conditions the UI shows - and its controller
     * is not installed here, so a freshly applied certificate has none. `getStatus()` is
     * declared to return an array and hands back whatever is under `status`, which is
     * nothing, so it dies on its own return type.
     *
     * That is the same shape as FEAT-16, and it is reachable in production: any cluster
     * where cert-manager is missing, not yet running, or has not got to this certificate
     * yet. The expiry cron job and the domain's certificate panel both go through here.
     * See FEAT-17.
     */
    public function testTheStatusOfAFreshCertificateDiesUntilCertManagerWritesOne(): void {
        $domain = $this->domainInTheTestNamespace();
        $certificate = new KubeCertificate($domain);
        $certificate->apply($this->cluster());

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must be of type array, null returned');

        $certificate->getStatus($this->cluster());
    }

    /**
     * Today's behaviour. `apply()` catches everything and hands it to a method that formats
     * it into a string, which is then dropped - so a certificate the api server refused
     * looks exactly like one it accepted, and the endpoint answers success either way.
     *
     * A namespace that is not there is the realistic way in: a domain keeps pointing at one
     * after it is removed. See FEAT-17.
     */
    public function testACertificateThatCouldNotBeCreatedIsSwallowedWithoutAWord(): void {
        $domain = $this->domainInTheTestNamespace([
            'certificate_namespace' => $this->testNamespace . '-not-a-namespace',
        ]);
        $certificate = new KubeCertificate($domain);

        $certificate->apply($this->cluster());

        $this->assertSame([], $certificate->getStatus($this->cluster()), 'nothing was created, and nothing said so');
    }

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
     * @return array<string, mixed>
     */
    private function certificate(Domain $domain): array {
        return $this->get(
            "/apis/cert-manager.io/v1/namespaces/{$this->testNamespace}/certificates/{$domain->certificate_name}"
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function certificatesInTheTestNamespace(): array {
        return $this->get("/apis/cert-manager.io/v1/namespaces/{$this->testNamespace}/certificates")['items'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array {
        return json_decode($this->cluster()->call('GET', $path)->getBody()->getContents(), true);
    }

    // </editor-fold>

}
