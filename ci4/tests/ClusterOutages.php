<?php namespace App;

/**
 * The two ways a cluster stops answering, arranged for the length of one test.
 *
 * Several endpoints exist to report a cluster that is not behaving, and their `catch`
 * blocks are the part a page actually shows an operator. Reaching those blocks needs the
 * cluster call to fail, and the throwaway cluster is perfectly healthy - so what is
 * changed is the credentials kso is given, which is also how it fails in production: a
 * fresh installation has none, and a rotated certificate leaves it with the wrong ones.
 *
 * **Both write to the process environment**, which every later test in the process reads,
 * so both put back what they found before returning - including when the body throws.
 * `KubeAuth` reads `KUBERNETES_KUBECONFIG` with `getenv()` and `KUBERNETES_AUTH` through
 * `env()`, and `env()` looks at $_ENV and $_SERVER first, which is why the second one goes
 * through `ClusterEnvironment`.
 *
 * Nothing here carries a real credential: the rejected one is a token the cluster has
 * never issued, and the configuration it is put into is the test cluster's own.
 */
trait ClusterOutages {

    /**
     * kso configured with a token the api server will not accept - a rotated service
     * account, or a kubeconfig copied from somewhere it no longer applies. The api server
     * answers 401, which php-k8s reports as a `KubernetesAPIException`.
     *
     * @param callable(): void $body
     */
    protected function withCredentialsTheClusterRejects(callable $body): void {
        $original = (string) getenv('KUBERNETES_KUBECONFIG');

        $config = yaml_parse(base64_decode($original));
        $config['users'][0]['user'] = ['token' => 'not-a-token-this-cluster-issued'];

        putenv('KUBERNETES_KUBECONFIG=' . base64_encode(yaml_emit($config)));

        try {
            $body();
        } finally {
            putenv('KUBERNETES_KUBECONFIG=' . $original);
        }
    }

    /**
     * kso with no cluster configured at all, which is a fresh installation before anyone
     * has filled in the environment. `KubeAuth::authenticate()` throws a plain exception
     * rather than reaching the network.
     *
     * @param callable(): void $body
     */
    protected function withNoClusterConfigured(callable $body): void {
        putenv('KUBERNETES_AUTH=');
        $_ENV['KUBERNETES_AUTH'] = '';
        $_SERVER['KUBERNETES_AUTH'] = '';

        try {
            $body();
        } finally {
            ClusterEnvironment::restore();
        }
    }

}
