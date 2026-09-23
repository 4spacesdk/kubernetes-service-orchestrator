<?php namespace App\Libraries\Kubernetes;

use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\CustomResourceStep;
use DebugTool\Data;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * The pods of one deployment - what the pod list, the logs, the metrics and the diagnosis look at.
 *
 * **kso's own workloads carry `app` and `role`** on their pod template, so a label selector finds
 * them. **A custom resource's pods are the operator's**, labelled however it likes - a
 * RabbitmqCluster's say `app.kubernetes.io/name`, not `app`. They are found by who owns them
 * instead: the pod belongs to a StatefulSet, which belongs to the custom resource. Every operator
 * that follows Kubernetes' own garbage collection writes those `ownerReferences`, so no operator
 * has to be known by name.
 *
 * A custom resource that makes no pods - a queue definition, a certificate - has none, which is
 * the right answer.
 */
class WorkloadPods {

    /** How far up from a pod its owner is looked for: pod → ReplicaSet → Deployment → the resource. */
    private const int MaxDepth = 4;

    public function __construct(
        private readonly KubernetesCluster $cluster,
    ) {
    }

    /**
     * Not those on their way out.
     *
     * @return list<array> The pods as the cluster has them
     * @throws \Throwable when the pods cannot be listed
     */
    public function of(Deployment $deployment): array {
        $namespace = (string) $deployment->namespace;

        if ($deployment->findDeploymentSpecification()->workload_type !== \WorkloadTypes::CustomResource) {
            return self::Live($this->list($namespace, urldecode(http_build_query(['app' => "{$deployment->name},role=app"]))));
        }

        $resource = (new CustomResourceStep())->findInTheCluster($deployment);
        $uid = $resource['metadata']['uid'] ?? null;
        if ($uid === null) {
            return [];
        }

        return self::Live(self::OwnedBy($uid, $this->list($namespace), fn(array $owner) => $this->owner($owner, $namespace)));
    }

    /**
     * The pods that $uid owns, directly or through what it made.
     *
     * @param list<array> $pods
     * @param \Closure(array $ownerReference): ?array $lookUp The owner a reference points to, null
     *   when it cannot be read
     * @return list<array>
     */
    public static function OwnedBy(string $uid, array $pods, \Closure $lookUp): array {
        /** @var array<string, list<array>> $ownersOf uid => the owner references of that object */
        $ownersOf = [];

        $isOwned = function (array $references, int $depth) use (&$isOwned, &$ownersOf, $uid, $lookUp): bool {
            foreach ($references as $reference) {
                $referenceUid = $reference['uid'] ?? '';
                if ($referenceUid === $uid) {
                    return true;
                }
                if ($depth >= self::MaxDepth || $referenceUid === '') {
                    continue;
                }
                if (!array_key_exists($referenceUid, $ownersOf)) {
                    $ownersOf[$referenceUid] = $lookUp($reference)['metadata']['ownerReferences'] ?? [];
                }
                if ($isOwned($ownersOf[$referenceUid], $depth + 1)) {
                    return true;
                }
            }
            return false;
        };

        return array_values(array_filter(
            $pods,
            fn(array $pod) => $isOwned($pod['metadata']['ownerReferences'] ?? [], 1),
        ));
    }

    /**
     * @return list<array>
     */
    private function list(string $namespace, ?string $labelSelector = null): array {
        $pods = [];
        foreach ($this->cluster->getAllPods($namespace, $labelSelector === null ? [] : ['labelSelector' => $labelSelector]) as $pod) {
            $pods[] = $pod->toArray();
        }
        return $pods;
    }

    /**
     * Whatever kind the reference names - a StatefulSet, a ReplicaSet, or the operator's own - read
     * by its path, since php-k8s knows only some of them. `apps/v1` + `StatefulSet` is
     * `/apis/apps/v1/namespaces/…/statefulsets/…`; the plural is the kind in lower case with an
     * `s`, which is what Kubernetes' own kinds and nearly every operator's are.
     */
    private function owner(array $reference, string $namespace): ?array {
        $apiVersion = (string) ($reference['apiVersion'] ?? '');
        $plural = strtolower((string) ($reference['kind'] ?? '')) . 's';
        $prefix = str_contains($apiVersion, '/') ? "/apis/{$apiVersion}" : "/api/{$apiVersion}";

        try {
            $response = $this->cluster->call('GET', "{$prefix}/namespaces/{$namespace}/{$plural}/" . rawurlencode((string) ($reference['name'] ?? '')));
            return json_decode((string) $response->getBody(), true) ?: null;
        } catch (\Throwable $e) {
            Data::debug('Could not read the owner', $reference['kind'] ?? '', $reference['name'] ?? '', ':', KubeHelper::PrintException($e));
            return null;
        }
    }

    /**
     * @param list<array> $pods
     * @return list<array>
     */
    private static function Live(array $pods): array {
        return array_values(array_filter($pods, fn(array $pod) => !isset($pod['metadata']['deletionTimestamp'])));
    }

}
