<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\Kubernetes\Cluster;
use App\Libraries\Kubernetes\ClusterDidNotAnswer;
use App\Libraries\Kubernetes\KubeAuth;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;

/**
 * Deployment::checkStatus() against a cluster that does not answer, and one that answers no.
 *
 * Nothing listens on 127.0.0.1:9, so the connection is refused at once.
 */
class DeploymentCheckStatusTest extends DatabaseTestCase {

    /**
     * A timeout is not an answer. It used to come back from validation as a reason, and a
     * Synced deployment became a Draft until the next status check.
     */
    public function testAClusterThatDoesNotAnswerLeavesTheStatusAsItWas(): void {
        $deployment = $this->syncedDeployment();

        $error = KubeAuth::Using(new Cluster('http://127.0.0.1:9'), fn () => $deployment->checkStatus(false));

        $this->assertStringContainsString('cURL error 7', $error);
        $this->assertSame(\DeploymentStatusTypes::Synced, $this->reread($deployment)->status);
    }

    /**
     * The Draft was not a failure to `updateVersion()`, so an auto update counted it as deployed.
     */
    public function testAnUpdateTheClusterDidNotAnswerIsAnError(): void {
        $deployment = $this->syncedDeployment();

        $error = KubeAuth::Using(new Cluster('http://127.0.0.1:9'), fn () => $deployment->updateVersion('2.0.0'));

        $this->assertStringContainsString('cURL error 7', $error);
        $this->assertSame(\DeploymentStatusTypes::Synced, $this->reread($deployment)->status);
    }

    /**
     * A cluster that answers 401 has answered. That is still a reason the deployment cannot be
     * deployed, and still a Draft.
     */
    public function testAClusterThatAnswersUnauthorizedStillMakesADraft(): void {
        $deployment = $this->syncedDeployment();

        $error = KubeAuth::Using($this->clusterAnswering401(), fn () => $deployment->checkStatus(false));

        $this->assertNull($error);
        $this->assertSame(\DeploymentStatusTypes::Draft, $this->reread($deployment)->status);
    }

    /**
     * Read by a person deciding what to do about it - the cluster's own words, not "Namespace_Error".
     */
    public function testUnauthorizedIsTheReasonGiven(): void {
        $deployment = $this->syncedDeployment();

        $reason = KubeAuth::Using($this->clusterAnswering401(), fn () => (new NamespaceStep())->reasonItCannotBeUsed($deployment));

        $this->assertStringContainsString('Unauthorized', $reason);
    }

    public function testNoAnswerIsNotAReason(): void {
        $deployment = $this->syncedDeployment();

        $this->expectException(ClusterDidNotAnswer::class);
        $this->expectExceptionMessage('cURL error 7');

        KubeAuth::Using(new Cluster('http://127.0.0.1:9'), fn () => (new NamespaceStep())->reasonItCannotBeUsed($deployment));
    }

    private function syncedDeployment(): Deployment {
        return Fixtures::deployableDeployment([
            'status' => \DeploymentStatusTypes::Synced,
            'version' => 'old',
            'image' => 'registry.example.org/test/app',
        ]);
    }

    private function reread(Deployment $deployment): Deployment {
        return (new Deployment())->find($deployment->id);
    }

    private function clusterAnswering401(): Cluster {
        return new class('http://127.0.0.1:9') extends Cluster {
            public function getClient() {
                return new Client(['handler' => HandlerStack::create(fn () => Create::promiseFor(new Response(
                    401,
                    ['Content-Type' => 'application/json'],
                    '{"kind":"Status","status":"Failure","message":"Unauthorized","reason":"Unauthorized","code":401}'
                )))]);
            }
        };
    }

}
