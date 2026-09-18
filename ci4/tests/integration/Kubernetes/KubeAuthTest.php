<?php namespace App\Tests\Integration\Kubernetes;

use App\IntegrationTestCase;
use App\Libraries\Kubernetes\KubeAuth;
use RenokiCo\PhpK8s\Kinds\K8sNode;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * Can kso reach the cluster it is configured for?
 *
 * Nothing else covers this. `KubeAuth` reads a kubeconfig, or in-cluster credentials, and
 * for GKE it shells out to `gke-auth` and exchanges a service account key for a token.
 * Every deployment step depends on it, and none of it can be checked without a cluster:
 * a unit test would only prove that php-k8s was called the way we believe it works.
 *
 * Read only, on purpose. It authenticates and lists nodes, which changes nothing.
 */
class KubeAuthTest extends IntegrationTestCase {

    protected function requiredEnvironment(): array {
        return ['KUBERNETES_AUTH'];
    }

    public function testAuthenticationReturnsAUsableCluster(): void {
        $cluster = (new KubeAuth())->authenticate();

        $this->assertInstanceOf(KubernetesCluster::class, $cluster);
    }

    /**
     * Authenticating is not the same as being allowed in: a token can be built from a
     * kubeconfig and still be rejected. Listing nodes is the cheapest call that proves the
     * credentials are accepted and the api server answers.
     */
    public function testTheClusterAnswers(): void {
        $cluster = (new KubeAuth())->authenticate();

        $nodes = $cluster->getAllNodes();

        $this->assertGreaterThan(0, $nodes->count(), 'a cluster with no nodes is not a cluster we can deploy to');
        $this->assertInstanceOf(K8sNode::class, $nodes->first());
    }

    /**
     * An unset KUBERNETES_AUTH is a configuration mistake, and it should say so rather
     * than failing somewhere further in with a confusing error.
     */
    public function testUnknownAuthMethodIsRejected(): void {
        $original = getenv('KUBERNETES_AUTH');

        // env() reads $_ENV and $_SERVER before it reaches getenv(), so all three have to
        // be set for the override to be seen.
        $this->setAuthMethod('something-else');

        try {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('missing KUBERNETES_AUTH');

            (new KubeAuth())->authenticate();
        } finally {
            $this->setAuthMethod((string)$original);
        }
    }

    private function setAuthMethod(string $value): void {
        putenv("KUBERNETES_AUTH={$value}");
        $_ENV['KUBERNETES_AUTH'] = $value;
        $_SERVER['KUBERNETES_AUTH'] = $value;
    }

}
