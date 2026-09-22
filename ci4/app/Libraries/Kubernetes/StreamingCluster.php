<?php namespace App\Libraries\Kubernetes;

use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * A cluster that hands out a pod's log stream as a plain socket, instead of reading it to the end
 * itself.
 *
 * php-k8s' own `watchContainerLogs()` owns the loop: it blocks on one socket until the callback
 * says stop, which is one process per pod. Following a whole deployment means several streams at
 * once, so the loop has to be ours - see `KubeLog::watchDeployment()`. Everything about the
 * connection is still php-k8s': the url it would have called, with the certificates and the token
 * it would have used.
 */
class StreamingCluster extends KubernetesCluster {

    /**
     * @return resource|false the stream, or false when it could not be opened
     */
    public function openLogStream(string $namespace, string $pod, string $container, array $query = []) {
        $url = $this->getCallableUrl("/api/v1/namespaces/{$namespace}/pods/{$pod}/log", [
            'container' => $container,
            'follow' => 1,
            'timestamps' => 1,
            ...$query,
        ]);

        return $this->createSocketConnection($url);
    }

}
