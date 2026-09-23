<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\ClusterHealth;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * A node's figures as `kubectl describe node` adds them up, from the node, the pods on it and
 * metrics-server.
 */
class ClusterHealthTest extends CIUnitTestCase {

    private const int Now = 1_800_000_000;

    public function testAReadyNodeWithItsCapacityAllocatableAndRole(): void {
        $node = $this->nodes([$this->node()])[0];

        $this->assertSame('node-a', $node['name']);
        $this->assertTrue($node['ready']);
        $this->assertNull($node['ready_reason']);
        $this->assertSame(['control-plane'], $node['roles']);
        $this->assertSame('v1.30.2', $node['kubelet_version']);
        $this->assertSame(2 * 86400, $node['age_seconds']);
        $this->assertSame(4000, $node['cpu_capacity']);
        $this->assertSame(3800, $node['cpu_allocatable']);
        $this->assertSame(8 * 1024 ** 3, $node['memory_capacity']);
        $this->assertSame(110, $node['pods_allocatable']);
    }

    public function testANodeThatIsNotReadySaysWhatTheKubeletSaid(): void {
        $node = $this->nodes([$this->node(ready: 'False', readyMessage: 'Kubelet stopped posting node status.')])[0];

        $this->assertFalse($node['ready']);
        $this->assertSame('Kubelet stopped posting node status.', $node['ready_reason']);
    }

    public function testOnlyThePressuresThatAreTrueAreListed(): void {
        $node = $this->nodes([$this->node(pressures: ['MemoryPressure' => 'True', 'DiskPressure' => 'False'])])[0];

        $this->assertSame(['MemoryPressure'], $node['pressures']);
    }

    public function testRequestedIsTheSumOfThePodsOnTheNode(): void {
        $node = $this->nodes([$this->node()], [
            $this->pod('node-a', [['cpu' => '250m', 'memory' => '256Mi'], ['cpu' => '100m', 'memory' => '64Mi']]),
            $this->pod('node-a', [['cpu' => '1', 'memory' => '1Gi']]),
            $this->pod('node-b', [['cpu' => '2', 'memory' => '2Gi']]),
        ])[0];

        $this->assertSame(1350, $node['cpu_requested']);
        $this->assertSame((256 + 64 + 1024) * 1024 ** 2, $node['memory_requested']);
        $this->assertSame(2, $node['pods']);
    }

    /**
     * The scheduler counts neither: a finished pod holds nothing, and a pending one is on no node.
     */
    public function testFinishedAndUnscheduledPodsHoldNothing(): void {
        $node = $this->nodes([$this->node()], [
            $this->pod('node-a', [['cpu' => '1']], 'Succeeded'),
            $this->pod('node-a', [['cpu' => '1']], 'Failed'),
            $this->pod(null, [['cpu' => '1']], 'Pending'),
        ])[0];

        $this->assertSame(0, $node['cpu_requested']);
        $this->assertSame(0, $node['pods']);
    }

    /**
     * Init containers run one at a time before the rest, so the largest counts - and only if it is
     * more than the containers together.
     */
    public function testALargeInitContainerIsWhatThePodRequests(): void {
        $pod = $this->pod('node-a', [['cpu' => '100m']]);
        $pod['spec']['initContainers'] = [['resources' => ['requests' => ['cpu' => '500m']]], ['resources' => ['requests' => ['cpu' => '200m']]]];

        $node = $this->nodes([$this->node()], [$pod])[0];

        $this->assertSame(500, $node['cpu_requested']);
    }

    public function testUsageComesFromMetricsServer(): void {
        $node = ClusterHealth::Nodes([$this->node()], [], [
            ['metadata' => ['name' => 'node-a'], 'usage' => ['cpu' => '523m', 'memory' => '2Gi']],
        ], self::Now)[0];

        $this->assertSame(523, $node['cpu_usage']);
        $this->assertSame(2 * 1024 ** 3, $node['memory_usage']);
    }

    public function testWithoutMetricsServerThereIsNoUsage(): void {
        $node = ClusterHealth::Nodes([$this->node()], [], null, self::Now)[0];

        $this->assertNull($node['cpu_usage']);
        $this->assertNull($node['memory_usage']);
    }

    public function testACordonedNodeIsUnschedulable(): void {
        $node = $this->node();
        $node['spec']['unschedulable'] = true;

        $this->assertTrue($this->nodes([$node])[0]['unschedulable']);
    }

    // <editor-fold desc="Helpers">

    private function nodes(array $nodes, array $pods = []): array {
        return ClusterHealth::Nodes($nodes, $pods, [], self::Now);
    }

    private function node(string $ready = 'True', ?string $readyMessage = null, array $pressures = []): array {
        $conditions = [['type' => 'Ready', 'status' => $ready, 'message' => $readyMessage]];
        foreach ($pressures as $type => $status) {
            $conditions[] = ['type' => $type, 'status' => $status];
        }

        return [
            'metadata' => [
                'name' => 'node-a',
                'creationTimestamp' => gmdate('Y-m-d\TH:i:s\Z', self::Now - 2 * 86400),
                'labels' => ['kubernetes.io/hostname' => 'node-a', 'node-role.kubernetes.io/control-plane' => ''],
            ],
            'spec' => [],
            'status' => [
                'conditions' => $conditions,
                'nodeInfo' => ['kubeletVersion' => 'v1.30.2'],
                'capacity' => ['cpu' => '4', 'memory' => '8Gi', 'pods' => '110'],
                'allocatable' => ['cpu' => '3800m', 'memory' => '7Gi', 'pods' => '110'],
            ],
        ];
    }

    /**
     * @param list<array<string, string>> $requests One per container
     */
    private function pod(?string $node, array $requests, string $phase = 'Running'): array {
        return [
            'spec' => [
                'nodeName' => $node,
                'containers' => array_map(fn(array $request) => ['resources' => ['requests' => $request]], $requests),
            ],
            'status' => ['phase' => $phase],
        ];
    }

    // </editor-fold>

}
