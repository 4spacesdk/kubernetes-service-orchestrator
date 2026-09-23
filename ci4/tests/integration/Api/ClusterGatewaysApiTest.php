<?php namespace App\Tests\Integration\Api;

use App\ClusterControllerTestCase;
use App\Entities\Domain;
use App\Entities\Gateway;
use App\Fixtures;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sGateway;
use App\Models\GatewayModel;

/**
 * The cluster's Gateways held up against kso's, and taking one over - against a Gateway that was
 * made outside kso, the way a platform team makes one before kso arrives.
 *
 * `ClusterGatewaysTest` has the comparison on arrays; this is the whole of it through the API:
 * what the cluster really answers, the typed confirmation, the rows written, and that the takeover
 * applies nothing.
 */
class ClusterGatewaysApiTest extends ClusterControllerTestCase {

    public function testAGatewayMadeOutsideKsoIsListedWithWhatTakingItOverWouldDo(): void {
        $this->aGatewayMadeOutsideKso();

        $row = $this->rowFor('edge');

        $this->assertSame('unknown', $row['status']);
        $this->assertSame('eg', $row['gateway_class_name']);
        $this->assertSame(['example.org/owner' => 'platform'], $row['annotations']);
        $this->assertSame(['shop.example.org'], array_column($row['plan']['domains'], 'name'));
        $this->assertSame(['grpc (TCP 9000)'], $row['plan']['listeners_removed']);
        $this->assertSame(
            ["https-wildcard-shop-example-org (HTTPS 443 *.shop.example.org, {$this->testNamespace}/shop-cert)"],
            $row['plan']['listeners_added'],
        );
    }

    public function testTakingOverWithoutTypingTheNameIsRefused(): void {
        $this->aGatewayMadeOutsideKso();

        $body = $this->decode($this->signedIn()->post("gateways/import?namespace={$this->testNamespace}&name=edge&confirm=edg"));

        $this->assertNotSame('OK', $body['status']);
        $this->assertFalse((new GatewayModel())->where('name', 'edge')->find()->exists());
    }

    /**
     * kso's rows are written and the matching domain linked - and the Gateway in the cluster is as
     * it was, the listener kso would remove still on it, until somebody deploys.
     */
    public function testTakingOverWritesKsosRowsAndAppliesNothing(): void {
        $domain = $this->aGatewayMadeOutsideKso();

        $body = $this->decode($this->signedIn()->post("gateways/import?namespace={$this->testNamespace}&name=edge&confirm=edge"));

        $this->assertSame('OK', $body['status'], json_encode($body));
        $gateway = new Gateway();
        $gateway->find($body['resource']['id']);
        $this->assertSame('eg', $gateway->gateway_class_name);
        $this->assertSame(['example.org/owner' => 'platform'], $gateway->getAnnotations());

        $linked = new Domain();
        $linked->find($domain->id);
        $this->assertSame((int) $gateway->id, (int) $linked->gateway_id);

        $listeners = array_column($this->listenersInTheCluster(), 'name');
        $this->assertContains('grpc', $listeners, 'nothing is applied by a takeover');

        $this->assertSame('known', $this->rowFor('edge')['status']);
    }

    // <editor-fold desc="Helpers">

    /**
     * A Gateway with an https listener for a domain kso knows, and a TCP listener kso would not
     * write - and the kso domain, not yet on any gateway.
     */
    private function aGatewayMadeOutsideKso(): Domain {
        (new NamespaceStep())->startDeployCommand($this->deploymentInTheTestNamespace());

        $domain = Fixtures::domain([
            'name' => 'shop.example.org',
            'certificate_name' => 'shop-cert',
            'certificate_namespace' => $this->testNamespace,
            'gateway_id' => null,
        ]);

        $kso = K8sGateway::Listeners([['name' => 'shop.example.org', 'certificate_name' => 'shop-cert', 'certificate_namespace' => '']], $this->testNamespace);
        $this->cluster()->call('POST', "/apis/gateway.networking.k8s.io/v1/namespaces/{$this->testNamespace}/gateways", json_encode([
            'apiVersion' => 'gateway.networking.k8s.io/v1',
            'kind' => 'Gateway',
            'metadata' => ['name' => 'edge', 'namespace' => $this->testNamespace, 'annotations' => ['example.org/owner' => 'platform']],
            'spec' => [
                'gatewayClassName' => 'eg',
                // kso's http and https for the domain, without the wildcard, and one of its own.
                'listeners' => [$kso[0], $kso[1], ['name' => 'grpc', 'protocol' => 'TCP', 'port' => 9000]],
            ],
        ]));

        return $domain;
    }

    private function rowFor(string $name): array {
        $body = $this->decode($this->signedIn()->get('gateways/in-cluster'));
        $this->assertSame('OK', $body['status'], json_encode($body));
        foreach ($body['resources'] as $row) {
            if ($row['namespace'] === $this->testNamespace && $row['name'] === $name) {
                return $row;
            }
        }
        $this->fail("{$name} is not in the list");
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    private function listenersInTheCluster(): array {
        $response = $this->cluster()->call('GET', "/apis/gateway.networking.k8s.io/v1/namespaces/{$this->testNamespace}/gateways/edge");
        return json_decode((string) $response->getBody(), true)['spec']['listeners'] ?? [];
    }

    // </editor-fold>

}
