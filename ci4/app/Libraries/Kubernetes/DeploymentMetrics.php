<?php namespace App\Libraries\Kubernetes;

use App\Entities\Deployment;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sPodMetrics;
use DebugTool\Data;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * What a deployment's pods are using right now, against what they were given.
 *
 * `kubectl top` in kso's own words: one call to metrics.k8s.io per deployment, the containers'
 * usage in the units kso's own fields are in - millicores and bytes - and the requests and limits
 * beside them, because a number on its own says nothing. Half a core is a lot for a form handler
 * and nothing for an importer; 480 of 500 millicores is the sentence.
 *
 * **Not history.** This is the last minute or so, measured by metrics-server, and it is gone when
 * the next one arrives. Graphs over time need somewhere to keep them, which is a larger decision.
 *
 * **A cluster without metrics-server answers 404.** That is not a failure: it is an installation
 * that has not got it, and the web app says so rather than showing an error.
 */
class DeploymentMetrics {

    public function __construct(
        private readonly KubernetesCluster $cluster,
    ) {
    }

    /**
     * @return array{
     *     available: bool,
     *     reason: string|null,
     *     window: string|null,
     *     cpu_millicores: int|null,
     *     memory_bytes: int|null,
     *     cpu_request: int|null,
     *     cpu_limit: int|null,
     *     memory_request_bytes: int|null,
     *     memory_limit_bytes: int|null,
     *     pods: list<array{pod: string, container: string, cpu_millicores: int|null, memory_bytes: int|null}>
     * }
     */
    public function of(Deployment $deployment): array {
        $answer = [
            'available' => true,
            'reason' => null,
            'window' => null,
            'cpu_millicores' => null,
            'memory_bytes' => null,
            // What one pod was given, which is what one pod's usage is held against.
            'cpu_request' => $deployment->cpu_request ?: null,
            'cpu_limit' => $deployment->cpu_limit ?: null,
            'memory_request_bytes' => $deployment->memory_request ? $deployment->memory_request * 1024 ** 2 : null,
            'memory_limit_bytes' => $deployment->memory_limit ? $deployment->memory_limit * 1024 ** 2 : null,
            'pods' => [],
        ];

        try {
            $metrics = $this->fetch($deployment);
        } catch (\Throwable $e) {
            // A cluster with no metrics-server, or one that would not answer this minute. Neither
            // is worth a failed request: the rest of the dialog is still worth showing.
            Data::debug('No metrics for', $deployment->name, ':', KubeHelper::PrintException($e));
            return [...$answer, 'available' => false, 'reason' => KubeHelper::PrintException($e)];
        }

        $cpu = null;
        $memory = null;
        foreach ($metrics as $podMetrics) {
            $pod = $podMetrics['metadata']['name'] ?? '';
            $answer['window'] ??= $podMetrics['window'] ?? null;

            foreach ($podMetrics['containers'] ?? [] as $container) {
                $millicores = Quantity::Millicores($container['usage']['cpu'] ?? null);
                $bytes = Quantity::Bytes($container['usage']['memory'] ?? null);

                $answer['pods'][] = [
                    'pod' => $pod,
                    'container' => $container['name'] ?? '',
                    'cpu_millicores' => $millicores,
                    'memory_bytes' => $bytes,
                ];

                // Null stays null until something is measured: a deployment nobody could measure
                // is not a deployment using nothing.
                if ($millicores !== null) {
                    $cpu = ($cpu ?? 0) + $millicores;
                }
                if ($bytes !== null) {
                    $memory = ($memory ?? 0) + $bytes;
                }
            }
        }

        return [...$answer, 'cpu_millicores' => $cpu, 'memory_bytes' => $memory];
    }

    /**
     * The deployment's pods - see `WorkloadPods`. kso's own are narrowed by their labels; a custom
     * resource's are asked for by namespace and picked out by name.
     *
     * @return list<array>
     */
    protected function fetch(Deployment $deployment): array {
        $isOurs = $deployment->findDeploymentSpecification()->workload_type !== \WorkloadTypes::CustomResource;
        $names = $isOurs ? null : array_flip(array_map(
            fn(array $pod) => $pod['metadata']['name'] ?? '',
            (new WorkloadPods($this->cluster))->of($deployment),
        ));

        $metrics = [];
        $resources = (new K8sPodMetrics($this->cluster))
            ->setNamespace($deployment->namespace)
            ->all($isOurs ? [
                'labelSelector' => urldecode(http_build_query(['app' => "{$deployment->name},role=app"])),
            ] : []);

        foreach ($resources as $resource) {
            $resource = $resource->toArray();
            if ($names === null || isset($names[$resource['metadata']['name'] ?? ''])) {
                $metrics[] = $resource;
            }
        }

        return $metrics;
    }

}
