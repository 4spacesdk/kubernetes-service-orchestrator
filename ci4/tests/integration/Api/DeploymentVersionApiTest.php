<?php namespace App\Tests\Integration\Api;

use App\ClusterControllerTestCase;
use App\Entities\Deployment;
use App\Fixtures;

/**
 * Setting a deployment's version rolls it out, synchronously, in the same request. Whether
 * the rollout worked used to be dropped: the answer was OK either way. The bulk version
 * update (LIST-6) reads this answer to say which deployments failed.
 *
 * Both tests start from a deployment that is fully in the cluster. One that is not stays in
 * Draft - its Service step needs the Deployment to exist - and a Draft deployment is not
 * rolled out at all, so it would answer OK without the cluster being asked.
 */
class DeploymentVersionApiTest extends ClusterControllerTestCase {

    public function testARolloutThatWorksIsAnsweredOk(): void {
        $deployment = $this->deploymentThatIsInTheCluster();

        $body = $this->decode($this->signedIn()->put("deployments/{$deployment->id}/version?value=1.29.1-alpine"));

        $this->assertSame('OK', $body['status'], $body['error'] ?? '');
        $this->assertSame('1.29.1-alpine', $this->reload($deployment)->version);
        $this->assertSame(\DeploymentStatusTypes::Active, $this->reload($deployment)->status, 'the rollout was never attempted');
    }

    /**
     * A memory request above the limit passes kso's own checks, and the api server refuses
     * the Deployment. The version is still saved - the message says so.
     */
    public function testARolloutTheClusterRefusesIsReported(): void {
        $deployment = $this->deploymentThatIsInTheCluster();
        $deployment->memory_limit = 64;
        $deployment->memory_request = 256;
        $deployment->save();

        $body = $this->decode($this->signedIn()->put("deployments/{$deployment->id}/version?value=1.29.1-alpine"));

        $this->assertSame('ERROR', $body['status']);
        $this->assertStringStartsWith('The version is saved, but the deploy failed: ', $body['error']);
        $this->assertStringContainsString('must be less than or equal to memory limit', $body['error']);
        $this->assertSame('1.29.1-alpine', $this->reload($deployment)->version);
    }

    private function deploymentThatIsInTheCluster(): Deployment {
        $deployment = $this->deploymentInTheTestNamespace(['status' => \DeploymentStatusTypes::Active]);
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'http',
            'port' => 80,
            'target_port' => 80,
        ]);
        foreach (['namespace', 'deployment', 'service'] as $step) {
            $body = $this->decode($this->signedIn()->put("deployment-steps/{$step}/deploy?deploymentId={$deployment->id}"));
            $this->assertSame('OK', $body['status'], "{$step}: " . ($body['error'] ?? ''));
        }
        return $this->reload($deployment);
    }

    private function reload(Deployment $deployment): Deployment {
        $reloaded = new Deployment();
        $reloaded->find($deployment->id);
        return $reloaded;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

}
