<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\ClusterIndex;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sKNativeService;
use App\Libraries\Kubernetes\IndexedCluster;
use CodeIgniter\Test\CIUnitTestCase;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sDeployment;
use RenokiCo\PhpK8s\Kinds\K8sClusterRole;
use RenokiCo\PhpK8s\Kinds\K8sService;

/**
 * The index the statuses are recomputed from, and the cluster that answers out of it.
 *
 * Everything here turns on the api path: php-k8s builds one per resource, and the index has to
 * recognise it without being told which kind it is looking at. The one that matters most is the
 * Knative Service, whose kind is also `Service` - the group in the path is what tells the two
 * apart, and an index that keyed on the kind alone would answer for the wrong one.
 */
class ClusterIndexTest extends CIUnitTestCase {

    // <editor-fold desc="What the index answers">

    public function testAResourceThatWasListedIsAnswered(): void {
        $index = ClusterIndex::Of([
            K8sDeployment::class => ['dev/api' => ['metadata' => ['name' => 'api', 'namespace' => 'dev']]],
        ]);

        $answer = $index->answerFor('/apis/apps/v1/namespaces/dev/deployments/api');

        $this->assertIsArray($answer);
        $this->assertSame('api', $answer['metadata']['name']);
    }

    /**
     * False, not null: the kind was looked at and this one was not among them. That is what makes
     * a step report its resource as gone.
     */
    public function testAResourceOfAnIndexedKindThatIsNotThereIsAnsweredWithFalse(): void {
        $index = ClusterIndex::Of([K8sDeployment::class => []]);

        $this->assertFalse($index->answerFor('/apis/apps/v1/namespaces/dev/deployments/api'));
    }

    /**
     * Null is "ask the cluster yourself" - a CRD this cluster does not have, or a custom resource,
     * which can be any kind at all.
     */
    public function testAKindThatWasNotIndexedIsNotAnswered(): void {
        $index = ClusterIndex::Of([K8sDeployment::class => []]);

        $this->assertNull($index->answerFor('/apis/rabbitmq.com/v1beta1/namespaces/dev/rabbitmqclusters/rabbit'));
    }

    /**
     * A Knative Service is a `Service` too. Kept apart by the group in the path, because a
     * deployment that runs as one has both a `Service` in `/api/v1` and one in
     * `/apis/serving.knative.dev/v1`, under the same name in the same namespace.
     */
    public function testAKnativeServiceIsNotTheSameAsAService(): void {
        $index = ClusterIndex::Of([
            K8sService::class => ['dev/api' => ['metadata' => ['name' => 'api']]],
            K8sKNativeService::class => [],
        ]);

        $this->assertIsArray($index->answerFor('/api/v1/namespaces/dev/services/api'));
        $this->assertFalse($index->answerFor('/apis/serving.knative.dev/v1/namespaces/dev/services/api'));
    }

    public function testAClusterScopedResourceIsKeptWithoutANamespace(): void {
        $index = ClusterIndex::Of([
            K8sClusterRole::class => ['/api-reader' => ['metadata' => ['name' => 'api-reader']]],
        ]);

        $this->assertIsArray($index->answerFor('/apis/rbac.authorization.k8s.io/v1/clusterroles/api-reader'));
        $this->assertFalse($index->answerFor('/apis/rbac.authorization.k8s.io/v1/clusterroles/somebody-elses'));
    }

    /**
     * A list is what the index is built from, not something it serves: the caller wants every
     * resource in a namespace, and that is not the question that was asked of the cluster.
     */
    public function testAListIsNotAnsweredFromTheIndex(): void {
        $index = ClusterIndex::Of([K8sDeployment::class => []]);

        $this->assertNull($index->answerFor('/apis/apps/v1/namespaces/dev/deployments'));
    }

    // </editor-fold>

    // <editor-fold desc="The cluster that reads it">

    public function testAnIndexedClusterAnswersAGetWithoutCallingOut(): void {
        $cluster = $this->cluster(ClusterIndex::Of([
            K8sDeployment::class => ['dev/api' => ['kind' => 'Deployment', 'metadata' => ['name' => 'api', 'namespace' => 'dev']]],
        ]));

        $resource = (new K8sDeployment($cluster))->setName('api')->setNamespace('dev')->get();

        $this->assertSame('api', $resource->getName());
        $this->assertSame(1, $cluster->served);
        $this->assertSame(0, $cluster->passedOn);
    }

    /**
     * `exists()` reads a 404 and answers false, so that is the shape a missing resource is
     * reported in - not an empty resource, which would read as being there.
     */
    public function testAMissingResourceComesBackAsA404(): void {
        $cluster = $this->cluster(ClusterIndex::Of([K8sDeployment::class => []]));

        $this->assertFalse((new K8sDeployment($cluster))->setName('api')->setNamespace('dev')->exists());
        $this->assertSame(1, $cluster->served);
    }

    /**
     * And a kind it knows nothing about goes to the api server - which is not there in this test,
     * so the attempt itself is the evidence.
     */
    public function testAKindThatIsNotIndexedIsAskedOfTheApiServer(): void {
        $cluster = $this->cluster(ClusterIndex::Of([K8sDeployment::class => []]));

        try {
            (new K8sService($cluster))->setName('api')->setNamespace('dev')->get();
            $this->fail('the call was answered from the index');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(KubernetesAPIException::class, $e, 'answered as a 404 rather than attempted');
        }

        $this->assertSame(1, $cluster->passedOn);
        $this->assertSame(0, $cluster->served);
    }

    private function cluster(ClusterIndex $index): IndexedCluster {
        // A url nothing listens on: every test here is about what does *not* leave the process.
        return (new IndexedCluster('http://127.0.0.1:9'))->useIndex($index);
    }

    // </editor-fold>

}
