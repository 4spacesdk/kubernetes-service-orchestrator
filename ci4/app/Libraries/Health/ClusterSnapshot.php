<?php namespace App\Libraries\Health;

use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sKNativeService;
use App\Libraries\Kubernetes\KubeHelper;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * What the cluster says about kso's workloads, read once and held as plain arrays.
 *
 * **Three calls per run, however many deployments there are**: the Deployments and the pods
 * in every namespace, and the Knative Services when there are any to look at. A call per row
 * is what this is here to avoid - a list of a hundred deployments would otherwise be three
 * hundred round trips a minute.
 *
 * The Deployments are fetched without a selector because kso puts `app` and `role` on the pod
 * template only, not on the Deployment itself; they are matched on namespace and name. The
 * pods do carry them, so those are narrowed to `role=app` - which is also what the Knative
 * pods carry, from the template kso gives the Service.
 *
 * Plain arrays rather than php-k8s objects, so `HealthEvaluator` can be handed a snapshot
 * built in a test (`FromArrays`) with no cluster and no library in between.
 */
class ClusterSnapshot {

    /**
     * @param array<string, array> $deployments "namespace/name" => the Deployment
     * @param array<string, array> $knativeServices "namespace/name" => the Knative Service
     * @param array<string, list<array>> $pods "namespace/app" => its pods
     * @param string|null $knativeError Why the Knative Services could not be read, if they could not.
     */
    private function __construct(
        private readonly array $deployments,
        private readonly array $knativeServices,
        private readonly array $pods,
        public readonly ?string $knativeError,
    ) {
    }

    /**
     * @param bool $withKnative Whether to ask for Knative Services. Only when some deployment
     *   is one: on a cluster without Knative the call fails, and it is not a failure there.
     * @throws \Throwable when the cluster cannot be read at all - the caller leaves every
     *   stored health alone rather than write down a guess.
     */
    public static function Fetch(KubernetesCluster $cluster, bool $withKnative): ClusterSnapshot {
        $deployments = [];
        foreach ($cluster->getAllDeploymentsFromAllNamespaces([]) as $deployment) {
            $deployments[] = $deployment->toArray();
        }

        $pods = [];
        foreach ($cluster->getAllPodsFromAllNamespaces(['labelSelector' => 'role=app']) as $pod) {
            $pods[] = $pod->toArray();
        }

        $knativeServices = [];
        $knativeError = null;
        if ($withKnative) {
            try {
                foreach ((new K8sKNativeService($cluster))->allNamespaces([]) as $service) {
                    $knativeServices[] = $service->toArray();
                }
            } catch (\Throwable $e) {
                $knativeError = KubeHelper::PrintException($e);
            }
        }

        return self::FromArrays($deployments, $pods, $knativeServices, $knativeError);
    }

    /**
     * @param list<array> $deployments
     * @param list<array> $pods
     * @param list<array> $knativeServices
     */
    public static function FromArrays(array $deployments, array $pods, array $knativeServices = [], ?string $knativeError = null): ClusterSnapshot {
        $podsByApp = [];
        foreach ($pods as $pod) {
            $app = $pod['metadata']['labels']['app'] ?? null;
            if ($app === null) {
                continue;
            }
            $podsByApp[self::Key($pod['metadata']['namespace'] ?? '', $app)][] = $pod;
        }

        return new ClusterSnapshot(
            self::ByName($deployments),
            self::ByName($knativeServices),
            $podsByApp,
            $knativeError,
        );
    }

    public function deployment(string $namespace, string $name): ?array {
        return $this->deployments[self::Key($namespace, $name)] ?? null;
    }

    public function knativeService(string $namespace, string $name): ?array {
        return $this->knativeServices[self::Key($namespace, $name)] ?? null;
    }

    /**
     * The pods of one workload - every pod with its `app` label in the namespace, the old
     * ReplicaSet's as well as the new one's, since a rollout that is stuck is exactly the
     * case where both are there. Pods on their way out are left out: a terminating pod is not
     * a problem, it is the fix to one.
     *
     * @return list<array>
     */
    public function pods(string $namespace, string $app): array {
        return array_values(array_filter(
            $this->pods[self::Key($namespace, $app)] ?? [],
            fn(array $pod) => !isset($pod['metadata']['deletionTimestamp']),
        ));
    }

    /**
     * @param list<array> $resources
     * @return array<string, array>
     */
    private static function ByName(array $resources): array {
        $byName = [];
        foreach ($resources as $resource) {
            $byName[self::Key($resource['metadata']['namespace'] ?? '', $resource['metadata']['name'] ?? '')] = $resource;
        }
        return $byName;
    }

    private static function Key(string $namespace, string $name): string {
        return "{$namespace}/{$name}";
    }

}
