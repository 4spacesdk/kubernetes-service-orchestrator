<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\ClusterDomains;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The cluster's Certificates held up against kso's domains, and what taking one over would do.
 */
class ClusterDomainsTest extends CIUnitTestCase {

    public function testACertificateKsoWroteIsKnownWithNothingDifferent(): void {
        $row = $this->only([$this->certificate('shop-cert', 'certs', ['*.shop.org', 'shop.org'], 'shop-cert')], [
            $this->known(4, 'shop.org', 'shop-cert', 'certs'),
        ]);

        $this->assertSame(ClusterDomains::Known, $row['status']);
        $this->assertSame(4, $row['domain_id']);
        $this->assertSame([], $row['differences']);
    }

    public function testAKnownCertificateSaysWhatDiffers(): void {
        $row = $this->only([$this->certificate('shop-cert', 'certs', ['shop.org', 'www.shop.org'], 'shop-cert', 'staging')], [
            $this->known(4, 'shop.org', 'shop-cert', 'certs'),
        ]);

        $this->assertSame([
            'www.shop.org is on the certificate and not in kso\'s',
            '*.shop.org is added by kso',
            'The issuer is staging, and letsencrypt in kso',
        ], $row['differences']);
    }

    /**
     * The one that breaks things: kso names the secret after the certificate, so a certificate
     * whose secret has another name would move it on kso's first apply.
     */
    public function testASecretKsoWouldRenameIsInThePlan(): void {
        $row = $this->only([$this->certificate('shop', 'certs', ['*.shop.org', 'shop.org'], 'shop-tls')], []);

        $this->assertSame(ClusterDomains::Unknown, $row['status']);
        $this->assertSame('shop.org', $row['plan']['domain']);
        $this->assertSame(['The secret is shop-tls, and the next apply names it shop - whatever mounts shop-tls loses its certificate'], $row['plan']['changes']);
    }

    public function testACertificateForOneHostGetsItsWildcardFromKso(): void {
        $row = $this->only([$this->certificate('api', 'certs', ['api.shop.org'], 'api')], []);

        $this->assertSame('api.shop.org', $row['plan']['domain']);
        $this->assertSame(['*.api.shop.org is added by the next apply'], $row['plan']['changes']);
    }

    public function testTheDomainIsLinkedToTheGatewayThatListensForIt(): void {
        $row = ClusterDomains::Compare([$this->certificate('shop', 'certs', ['*.shop.org', 'shop.org'], 'shop')], [], [
            'shop.org' => ['id' => 2, 'name' => 'public'],
        ])[0];

        $this->assertSame(['id' => 2, 'name' => 'public'], $row['plan']['gateway']);
    }

    public function testADomainKsoHasOnAnotherCertificateCannotBeTakenOverTwice(): void {
        $row = $this->only([$this->certificate('shop-new', 'certs', ['*.shop.org', 'shop.org'], 'shop-new')], [
            $this->known(4, 'shop.org', 'shop-cert', 'certs'),
        ]);

        $this->assertSame('kso already has the domain shop.org, on the certificate certs/shop-cert', $row['plan']['conflict']);
    }

    /**
     * The development cluster's RabbitMQ operator has two, for its webhook and its metrics - they
     * were offered as domains before this.
     */
    public function testACertificateForNamesInsideTheClusterIsInternalAndHasNoPlan(): void {
        $row = $this->only([$this->certificate('webhook', 'rabbitmq-system', [
            'cluster-operator-webhook-service.rabbitmq-system.svc',
            'cluster-operator-webhook-service.rabbitmq-system.svc.cluster.local',
        ], 'webhook', 'selfsigned-issuer')], []);

        $this->assertSame(ClusterDomains::Internal, $row['status']);
        $this->assertNull($row['plan']);
    }

    public function testOneOutsideNameMakesItADomain(): void {
        $this->assertFalse(ClusterDomains::IsInternal(['api.svc', 'api.shop.org']));
        $this->assertTrue(ClusterDomains::IsInternal(['api.ns.svc']));
    }

    public function testTheDomainIsTheShortestHostThatIsNotAWildcard(): void {
        $this->assertSame('shop.org', ClusterDomains::DomainOf(['www.shop.org', '*.shop.org', 'shop.org']));
        $this->assertSame('shop.org', ClusterDomains::DomainOf(['*.shop.org']));
        $this->assertNull(ClusterDomains::DomainOf([]));
    }

    public function testReadinessAndExpiryComeFromCertManager(): void {
        $certificate = $this->certificate('shop', 'certs', ['shop.org'], 'shop');
        $certificate['status'] = ['conditions' => [['type' => 'Ready', 'status' => 'False']], 'notAfter' => '2026-12-01T00:00:00Z'];

        $row = $this->only([$certificate], []);

        $this->assertFalse($row['ready']);
        $this->assertSame('2026-12-01T00:00:00Z', $row['not_after']);
    }

    // <editor-fold desc="Helpers">

    private function only(array $certificates, array $known): array {
        $rows = ClusterDomains::Compare($certificates, $known, []);
        $this->assertCount(1, $rows);
        return $rows[0];
    }

    private function certificate(string $name, string $namespace, array $dnsNames, string $secret, string $issuer = 'letsencrypt'): array {
        return [
            'metadata' => ['name' => $name, 'namespace' => $namespace],
            'spec' => ['dnsNames' => $dnsNames, 'secretName' => $secret, 'issuerRef' => ['name' => $issuer]],
        ];
    }

    private function known(int $id, string $name, string $certificate, string $namespace): array {
        return ['id' => $id, 'name' => $name, 'certificate_name' => $certificate, 'certificate_namespace' => $namespace, 'issuer_ref_name' => 'letsencrypt'];
    }

    // </editor-fold>

}
