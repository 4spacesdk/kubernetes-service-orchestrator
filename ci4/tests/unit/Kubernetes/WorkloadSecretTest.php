<?php namespace App\Tests\Unit\Kubernetes;

use App\Entities\Deployment;
use App\Libraries\Kubernetes\WorkloadSecret;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The Secret a workload's secret variables go in: its name, its keys, and what changes its
 * checksum.
 */
class WorkloadSecretTest extends CIUnitTestCase {

    public function testTheNameCarriesTheWorkloadAndItsKind(): void {
        $this->assertSame('api-deployment-env', WorkloadSecret::For('api', 'deployment')->name);
        $this->assertSame('nightly-cronjob-env', WorkloadSecret::For('nightly', 'cronjob')->name);
    }

    /**
     * A Secret's name is at most 253 characters.
     */
    public function testALongNameIsCutToWhatKubernetesTakes(): void {
        $this->assertSame(253, strlen(WorkloadSecret::For(str_repeat('a', 300), 'deployment')->name));
    }

    public function testAKeyIsTheContainerAndTheVariable(): void {
        $secret = WorkloadSecret::For('api', 'deployment');

        $this->assertSame('api.DB_PASS', $secret->add('api', 'DB_PASS', 'x'));
        $this->assertSame('migrate.DB_PASS', $secret->add('migrate', 'DB_PASS', 'y'));
        $this->assertSame(['api.DB_PASS' => 'x', 'migrate.DB_PASS' => 'y'], $secret->data());
    }

    /**
     * Anything a Secret's key cannot hold is replaced, with a hash so two such names do not
     * meet.
     */
    public function testAVariableNameAKeyCannotHoldIsMadeOneThatCan(): void {
        $secret = WorkloadSecret::For('api', 'deployment');

        $first = $secret->add('api', 'MY VAR', 'x');
        $second = $secret->add('api', 'MY:VAR', 'y');

        $this->assertMatchesRegularExpression('/^[-._a-zA-Z0-9]+$/', $first);
        $this->assertStringStartsWith('api.MY_VAR-', $first);
        $this->assertNotSame($first, $second);
    }

    public function testTheChecksumFollowsTheValuesNotTheOrder(): void {
        $one = WorkloadSecret::For('api', 'deployment');
        $one->add('api', 'A', '1');
        $one->add('api', 'B', '2');
        $other = WorkloadSecret::For('api', 'deployment');
        $other->add('api', 'B', '2');
        $other->add('api', 'A', '1');
        $changed = WorkloadSecret::For('api', 'deployment');
        $changed->add('api', 'A', '1');
        $changed->add('api', 'B', '3');

        $this->assertSame($one->checksum(), $other->checksum());
        $this->assertNotSame($one->checksum(), $changed->checksum());
    }

    public function testTheResourceIsAnOpaqueSecretLabelledWithTheDeployment(): void {
        $deployment = new Deployment();
        $deployment->name = 'api';
        $deployment->namespace = 'tenant';
        $secret = WorkloadSecret::For('api', 'deployment');
        $secret->add('api', 'API_TOKEN', 'token-value');

        $resource = json_decode($secret->toResource($deployment)->toJson(), true);

        $this->assertSame('Secret', $resource['kind']);
        $this->assertSame('Opaque', $resource['type']);
        $this->assertSame(['name' => 'api-deployment-env', 'namespace' => 'tenant', 'labels' => ['app.kubernetes.io/managed-by' => 'kso', 'app' => 'api']], $resource['metadata']);
        $this->assertSame(['api.API_TOKEN' => base64_encode('token-value')], $resource['data']);
    }

}
