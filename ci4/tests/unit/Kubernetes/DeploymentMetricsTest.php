<?php namespace App\Tests\Unit\Kubernetes;

use App\Entities\Deployment;
use App\Libraries\Kubernetes\DeploymentMetrics;
use CodeIgniter\Test\CIUnitTestCase;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * What a deployment's pods are using, put beside what they were given.
 *
 * The answers here are metrics-server's own, copied from the development cluster: cpu in
 * nanocores, memory in `Ki`, one entry per container.
 */
class DeploymentMetricsTest extends CIUnitTestCase {

    public function testEveryPodIsListedAndTheyAreAddedUp(): void {
        $metrics = new FakeDeploymentMetrics([
            $this->podMetrics('api-1', cpu: '36236n', memory: '63068Ki'),
            $this->podMetrics('api-2', cpu: '8956861n', memory: '65304Ki'),
        ]);

        $answer = $metrics->of($this->deployment());

        $this->assertTrue($answer['available']);
        $this->assertCount(2, $answer['pods']);
        $this->assertSame(9, $answer['cpu_millicores'], '0 + 9 millicores');
        $this->assertSame((63068 + 65304) * 1024, $answer['memory_bytes']);
        $this->assertSame('59s', $answer['window']);
    }

    /**
     * A pod with more than one container is more than one row, and the deployment's total is all
     * of them - a sidecar's memory is memory the pod is using.
     */
    public function testEveryContainerOfAPodCounts(): void {
        $pod = $this->podMetrics('api-1', cpu: '100m', memory: '10Mi');
        $pod['containers'][] = ['name' => 'sidecar', 'usage' => ['cpu' => '50m', 'memory' => '5Mi']];

        $answer = (new FakeDeploymentMetrics([$pod]))->of($this->deployment());

        $this->assertCount(2, $answer['pods']);
        $this->assertSame(150, $answer['cpu_millicores']);
        $this->assertSame(15 * 1024 ** 2, $answer['memory_bytes']);
    }

    /**
     * What one pod was given, in the same units as what it is using - the web app should not have
     * to know that kso keeps millicores in one field and mebibytes in another.
     */
    public function testWhatThePodsWereGivenComesBackInTheSameUnits(): void {
        $answer = (new FakeDeploymentMetrics([]))->of($this->deployment());

        $this->assertSame(10, $answer['cpu_request']);
        $this->assertSame(500, $answer['cpu_limit']);
        $this->assertSame(10 * 1024 ** 2, $answer['memory_request_bytes']);
        $this->assertSame(954 * 1024 ** 2, $answer['memory_limit_bytes']);
    }

    /**
     * metrics-server is not part of Kubernetes. A cluster without it answers 404, and that is an
     * installation that has not got it - not a failed request.
     */
    public function testAClusterWithoutMetricsServerSaysSoInsteadOfFailing(): void {
        $metrics = new FakeDeploymentMetrics([]);
        $metrics->failWith('the server could not find the requested resource');

        $answer = $metrics->of($this->deployment());

        $this->assertFalse($answer['available']);
        $this->assertStringContainsString('could not find', $answer['reason']);
        $this->assertNull($answer['cpu_millicores']);
        $this->assertSame([], $answer['pods']);
    }

    /**
     * Nothing measured is nothing, not zero: a deployment whose pods have just started has no
     * numbers yet, and "0 of 500m" would be a measurement nobody took.
     */
    public function testNothingMeasuredIsNothingRatherThanZero(): void {
        $answer = (new FakeDeploymentMetrics([]))->of($this->deployment());

        $this->assertNull($answer['cpu_millicores']);
        $this->assertNull($answer['memory_bytes']);
    }

    private function deployment(): Deployment {
        $deployment = new Deployment();
        $deployment->name = 'api';
        $deployment->namespace = 'ns';
        $deployment->cpu_request = 10;
        $deployment->cpu_limit = 500;
        $deployment->memory_request = 10;
        $deployment->memory_limit = 954;
        return $deployment;
    }

    /**
     * @return array<string, mixed>
     */
    private function podMetrics(string $pod, string $cpu, string $memory): array {
        return [
            'metadata' => ['name' => $pod, 'namespace' => 'ns'],
            'window' => '59s',
            'containers' => [['name' => 'api', 'usage' => ['cpu' => $cpu, 'memory' => $memory]]],
        ];
    }

}

/**
 * `DeploymentMetrics` with the one call to the cluster answered from an array.
 */
class FakeDeploymentMetrics extends DeploymentMetrics {

    private ?string $failure = null;

    /**
     * @param list<array> $metrics
     */
    public function __construct(private readonly array $metrics) {
        parent::__construct(new KubernetesCluster('http://127.0.0.1:9'));
    }

    public function failWith(string $reason): void {
        $this->failure = $reason;
    }

    protected function fetch(Deployment $deployment): array {
        if ($this->failure !== null) {
            throw new \RuntimeException($this->failure);
        }
        return $this->metrics;
    }

}
