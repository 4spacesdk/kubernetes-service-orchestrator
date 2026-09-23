<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\GatewaySteps\ClusterGateways;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The cluster's Gateways held up against kso's, and what taking one over would do.
 *
 * The class that differs is the development cluster's on 2026-09-23: kso's only Gateway was `eg` in
 * the cluster and `gke-l7-regional-external-managed` in kso, and its next Deploy would have tried to
 * change it.
 */
class ClusterGatewaysTest extends CIUnitTestCase {

    public function testAGatewayKsoHasIsKnownAndSaysWhatDiffers(): void {
        $row = $this->only([$this->gateway('klartboard', 'envoy-gateway', 'eg', $this->ksoListeners('dev.example.org', 'envoy-gateway'))], [
            $this->known(2, 'klartboard', 'envoy-gateway', 'gke-l7-regional-external-managed'),
        ], [
            $this->domain(1, 'dev.example.org', 2),
        ]);

        $this->assertSame(ClusterGateways::Known, $row['status']);
        $this->assertSame(2, $row['gateway_id']);
        $this->assertSame(['The class is eg in the cluster and gke-l7-regional-external-managed in kso'], $row['differences']);
        $this->assertNull($row['plan']);
    }

    /**
     * A certificate named without a namespace is in the Gateway's own - the cluster leaves it out,
     * kso writes it, and that is not a difference.
     */
    public function testACertificateWithoutANamespaceIsInTheGatewaysOwn(): void {
        $listeners = $this->ksoListeners('dev.example.org', 'gw');
        foreach ($listeners as &$listener) {
            unset($listener['tls']['certificateRefs'][0]['namespace']);
        }

        $row = $this->only([$this->gateway('main', 'gw', 'eg', $listeners)], [$this->known(2, 'main', 'gw', 'eg')], [$this->domain(1, 'dev.example.org', 2)]);

        $this->assertSame([], $row['differences']);
    }

    public function testAListenerKsoWouldNotWriteIsADifferenceOfAKnownGateway(): void {
        $listeners = [...$this->ksoListeners('dev.example.org', 'gw'), ['name' => 'grpc', 'protocol' => 'TCP', 'port' => 9000]];

        $row = $this->only([$this->gateway('main', 'gw', 'eg', $listeners)], [$this->known(2, 'main', 'gw', 'eg')], [$this->domain(1, 'dev.example.org', 2)]);

        $this->assertSame(['The listener grpc (TCP 9000) is in the cluster and not in kso'], $row['differences']);
    }

    public function testAGatewayKsoDoesNotHaveIsUnknownWithAPlan(): void {
        $listeners = [
            ...$this->ksoListeners('shop.example.org', 'edge'),
            ['name' => 'grpc', 'protocol' => 'TCP', 'port' => 9000],
            ['name' => 'api', 'protocol' => 'HTTPS', 'port' => 443, 'hostname' => 'api.other.org', 'tls' => ['certificateRefs' => [['name' => 'other']]]],
        ];

        $row = $this->only([$this->gateway('public', 'edge', 'eg', $listeners)], [], [
            $this->domain(1, 'shop.example.org', null),
            $this->domain(2, 'unrelated.org', null),
        ]);

        $this->assertSame(ClusterGateways::Unknown, $row['status']);
        $this->assertSame([['id' => 1, 'name' => 'shop.example.org']], $row['plan']['domains']);
        $this->assertSame([
            'grpc (TCP 9000)',
            'api (HTTPS 443 api.other.org, edge/other)',
        ], $row['plan']['listeners_removed'], 'what kso does not build is what its first Deploy takes away');
        $this->assertSame([], $row['plan']['listeners_added']);
    }

    /**
     * Moving a domain off another kso gateway would take its listeners away there, so it is left
     * where it is and said.
     */
    public function testADomainOnAnotherKsoGatewayStaysThere(): void {
        $row = $this->only([$this->gateway('new', 'edge', 'eg', $this->ksoListeners('shop.example.org', 'edge'))], [
            $this->known(7, 'old', 'edge', 'eg'),
        ], [
            $this->domain(1, 'shop.example.org', 7),
        ]);

        $this->assertSame([], $row['plan']['domains']);
        $this->assertSame([['id' => 1, 'name' => 'shop.example.org', 'gateway' => 'old']], $row['plan']['domains_elsewhere']);
        $this->assertContains('https-shop-example-org (HTTPS 443 shop.example.org, edge/shop-cert)', $row['plan']['listeners_removed']);
    }

    public function testAGatewayWithKsosMarkAndNoRowIsAnOrphan(): void {
        $gateway = $this->gateway('left-behind', 'edge', 'eg', []);
        $gateway['metadata']['annotations'] = ['app.kubernetes.io/managed-by' => '4spaces.kso'];

        $this->assertSame(ClusterGateways::Orphan, $this->only([$gateway], [], [])['status']);
    }

    /**
     * kso's own mark and kubectl's copy of the last manifest are bookkeeping - taking them over
     * would write them back as settings.
     */
    public function testBookkeepingAnnotationsAreNotTakenOver(): void {
        $gateway = $this->gateway('public', 'edge', 'eg', []);
        $gateway['metadata']['annotations'] = [
            'kubectl.kubernetes.io/last-applied-configuration' => '{}',
            'networking.gke.io/certmap' => 'main',
        ];

        $this->assertSame(['networking.gke.io/certmap' => 'main'], $this->only([$gateway], [], [])['annotations']);
    }

    public function testTheOnesKsoDoesNotHaveComeFirst(): void {
        $rows = ClusterGateways::Compare([
            $this->gateway('a', 'ns', 'eg', []),
            $this->gateway('b', 'ns', 'eg', []),
        ], [$this->known(1, 'a', 'ns', 'eg')], []);

        $this->assertSame(['b', 'a'], array_column($rows, 'name'));
    }

    // <editor-fold desc="Helpers">

    private function only(array $inCluster, array $known, array $domains): array {
        $rows = ClusterGateways::Compare($inCluster, $known, $domains);
        $this->assertCount(1, $rows);
        return $rows[0];
    }

    private function gateway(string $name, string $namespace, string $class, array $listeners): array {
        return [
            'metadata' => ['name' => $name, 'namespace' => $namespace],
            'spec' => ['gatewayClassName' => $class, 'listeners' => $listeners],
        ];
    }

    private function known(int $id, string $name, string $namespace, string $class): array {
        return ['id' => $id, 'name' => $name, 'namespace' => $namespace, 'gateway_class_name' => $class, 'addresses' => []];
    }

    private function domain(int $id, string $name, ?int $gatewayId): array {
        return ['id' => $id, 'name' => $name, 'gateway_id' => $gatewayId, 'certificate_name' => str_replace('.example.org', '', $name) . '-cert', 'certificate_namespace' => ''];
    }

    /**
     * The listeners kso itself writes for one domain.
     */
    private function ksoListeners(string $domain, string $namespace): array {
        $certificate = str_replace('.example.org', '', $domain) . '-cert';
        return \App\Libraries\Kubernetes\CustomResourceDefinitions\K8sGateway::Listeners(
            [['name' => $domain, 'certificate_name' => $certificate, 'certificate_namespace' => '']],
            $namespace,
        );
    }

    // </editor-fold>

}
