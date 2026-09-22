<?php namespace App\Libraries\Kubernetes;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * A cluster that answers "is this resource there?" from a `ClusterIndex` instead of asking the
 * api server.
 *
 * It is the same cluster otherwise: **only a GET of a single resource of an indexed kind is
 * answered from the index**. Writes, watches, logs, exec, lists and anything of a kind that was
 * not indexed go out as before. A step therefore needs no changes, and neither does anything
 * that happens to run while one of these is in hand.
 *
 * Built by `KubeAuth::indexed()` so it is connected exactly as the ordinary cluster is.
 */
class IndexedCluster extends KubernetesCluster {

    private ?ClusterIndex $index = null;

    /** How many GETs the index answered, and how many still went to the api server. */
    public int $served = 0;
    public int $passedOn = 0;

    public function useIndex(ClusterIndex $index): static {
        $this->index = $index;
        return $this;
    }

    public function runOperation(string $operation, string $path, $payload = '', array $query = ['pretty' => 1]) {
        if ($operation !== self::GET_OP || !$this->index) {
            return parent::runOperation($operation, $path, $payload, $query);
        }

        $answer = $this->index->answerFor($path);
        if ($answer === null) {
            $this->passedOn++;
            return parent::runOperation($operation, $path, $payload, $query);
        }

        $this->served++;
        if ($answer === false) {
            // The shape `exists()` reads: a 404 from the api server, which it turns into false.
            throw new KubernetesAPIException("{$path} not found", 404, ['code' => 404]);
        }

        $resourceClass = $this->resourceClass;
        return (new $resourceClass($this, $answer))->synced();
    }

}
