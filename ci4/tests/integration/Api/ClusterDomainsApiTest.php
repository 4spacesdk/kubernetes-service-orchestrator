<?php namespace App\Tests\Integration\Api;

use App\ClusterControllerTestCase;
use App\Entities\Domain;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Models\DomainModel;

/**
 * The cluster's cert-manager Certificates held up against kso's domains, and taking one over -
 * against a Certificate made outside kso, with a secret named its own way.
 *
 * `ClusterDomainsTest` has the comparison on arrays; this is the whole of it through the API.
 */
class ClusterDomainsApiTest extends ClusterControllerTestCase {

    public function testACertificateMadeOutsideKsoIsListedWithWhatTakingItOverWouldDo(): void {
        $this->aCertificateMadeOutsideKso();

        $row = $this->rowFor('shop');

        $this->assertSame('unknown', $row['status']);
        $this->assertSame('shop.example.org', $row['plan']['domain']);
        $this->assertNull($row['plan']['conflict']);
        $this->assertContains(
            'The secret is shop-tls, and the next apply names it shop - whatever mounts shop-tls loses its certificate',
            $row['plan']['changes'],
        );
    }

    public function testTakingOverWithoutTypingTheNameIsRefused(): void {
        $this->aCertificateMadeOutsideKso();

        $body = $this->decode($this->signedIn()->post("domains/import?namespace={$this->testNamespace}&name=shop&confirm=sho"));

        $this->assertNotSame('OK', $body['status']);
        $this->assertFalse((new DomainModel())->where('name', 'shop.example.org')->find()->exists());
    }

    /**
     * The domain is written from the certificate, and the certificate in the cluster keeps its
     * secret until somebody applies it from kso.
     */
    public function testTakingOverWritesTheDomainAndAppliesNothing(): void {
        $this->aCertificateMadeOutsideKso();

        $body = $this->decode($this->signedIn()->post("domains/import?namespace={$this->testNamespace}&name=shop&confirm=shop"));

        $this->assertSame('OK', $body['status'], json_encode($body));
        $domain = new Domain();
        $domain->find($body['resource']['id']);
        $this->assertSame('shop.example.org', $domain->name);
        $this->assertSame('shop', $domain->certificate_name);
        $this->assertSame($this->testNamespace, $domain->certificate_namespace);
        $this->assertSame('letsencrypt', $domain->issuer_ref_name);

        $this->assertSame('shop-tls', $this->certificateInTheCluster()['spec']['secretName'], 'nothing is applied by a takeover');
        $this->assertSame('known', $this->rowFor('shop')['status']);
    }

    // <editor-fold desc="Helpers">

    private function aCertificateMadeOutsideKso(): void {
        (new NamespaceStep())->startDeployCommand($this->deploymentInTheTestNamespace());

        $this->cluster()->call('POST', "/apis/cert-manager.io/v1/namespaces/{$this->testNamespace}/certificates", json_encode([
            'apiVersion' => 'cert-manager.io/v1',
            'kind' => 'Certificate',
            'metadata' => ['name' => 'shop', 'namespace' => $this->testNamespace],
            'spec' => [
                'secretName' => 'shop-tls',
                'issuerRef' => ['name' => 'letsencrypt'],
                'dnsNames' => ['shop.example.org', '*.shop.example.org'],
            ],
        ]));
    }

    private function rowFor(string $name): array {
        $body = $this->decode($this->signedIn()->get('domains/in-cluster'));
        $this->assertSame('OK', $body['status'], json_encode($body));
        foreach ($body['resources'] as $row) {
            if ($row['namespace'] === $this->testNamespace && $row['name'] === $name) {
                return $row;
            }
        }
        $this->fail("{$name} is not in the list");
    }

    private function certificateInTheCluster(): array {
        $response = $this->cluster()->call('GET', "/apis/cert-manager.io/v1/namespaces/{$this->testNamespace}/certificates/shop");
        return json_decode((string) $response->getBody(), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
