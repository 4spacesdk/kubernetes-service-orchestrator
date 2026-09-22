<?php namespace App\Libraries\Kubernetes;

use DebugTool\Data;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * Every resource of the kinds kso deploys, listed once and held by path.
 *
 * `Deployment::checkStatus()` asks each of its steps whether its resource is in the cluster, and
 * a step answers with `exists()`, which is a GET. That is one call per step per deployment: the
 * scheduler could not afford to recompute the status, so it never did, and a status stayed wrong
 * until somebody deployed or pressed refresh.
 *
 * One list call per kind answers it for every deployment at once - twenty calls for an
 * installation of any size. `IndexedCluster` serves the steps' GETs from here.
 *
 * **A kind that is not in here is not answered from here.** A cluster without Contour, Istio or
 * Knative has no such CRD, and a custom resource can be any kind at all; those fall through to
 * the api server as before. Correct either way - only the cheap path is missed.
 */
class ClusterIndex {

    /**
     * The kinds kso itself deploys. A custom resource is whatever the operator wrote, and is not
     * here.
     *
     * **Secrets are deliberately not here.** Listing them would hold every secret in the cluster
     * in kso's memory for the length of a run, and `WorkloadSecret` reads a secret's contents
     * while a deployment's manifest is built - which happens inside a status check. Indexing
     * only their metadata would answer that read with an empty secret, quietly. They cost a
     * couple of GETs per deployment instead.
     */
    public const array Kinds = [
        \RenokiCo\PhpK8s\Kinds\K8sNamespace::class,
        \RenokiCo\PhpK8s\Kinds\K8sDeployment::class,
        \RenokiCo\PhpK8s\Kinds\K8sService::class,
        \RenokiCo\PhpK8s\Kinds\K8sIngress::class,
        \RenokiCo\PhpK8s\Kinds\K8sJob::class,
        \RenokiCo\PhpK8s\Kinds\K8sCronJob::class,
        \RenokiCo\PhpK8s\Kinds\K8sPersistentVolume::class,
        \RenokiCo\PhpK8s\Kinds\K8sPersistentVolumeClaim::class,
        \RenokiCo\PhpK8s\Kinds\K8sServiceAccount::class,
        \RenokiCo\PhpK8s\Kinds\K8sRole::class,
        \RenokiCo\PhpK8s\Kinds\K8sRoleBinding::class,
        \RenokiCo\PhpK8s\Kinds\K8sClusterRole::class,
        \RenokiCo\PhpK8s\Kinds\K8sClusterRoleBinding::class,
        CustomResourceDefinitions\K8sKNativeService::class,
        CustomResourceDefinitions\K8sContourHttpProxy::class,
        CustomResourceDefinitions\K8sHttpRoute::class,
        CustomResourceDefinitions\K8sIstioVirtualService::class,
        CustomResourceDefinitions\K8sGCPBackendPolicy::class,
        CustomResourceDefinitions\K8sHealthCheckPolicy::class,
    ];

    /**
     * How many deployments make the index worth building when something asks on the spot.
     *
     * The index is one list call per kind - twenty, whatever the cluster holds - and checking a
     * deployment step by step is six or seven calls. Measured against the development cluster
     * 2026-09-22: six deployments took 7.0s step by step and 4.1s through an index, of which
     * 3.8s was the index itself. Below five it is the slower way round, and the scheduler's own
     * run pays the price once a minute for everybody either way.
     */
    public const int WorthItFrom = 5;

    /**
     * `/api/v1` or `/apis/<group>/<version>`, then optionally a namespace, then the plural, then
     * optionally the name. Both the list path and a single resource's path are this shape.
     */
    private const string PathPattern = '#^(/api/v1|/apis/[^/]+/[^/]+)(?:/namespaces/([^/]+))?/([^/?]+)(?:/([^/?]+))?$#';

    /**
     * @param array<string, array<string, array>> $items "group|plural" => "namespace/name" => the resource
     * @param array<string, string> $unreadable "group|plural" => why, for the log
     */
    private function __construct(
        private readonly array $items,
        public readonly array $unreadable,
    ) {
    }

    /**
     * @param list<class-string> $kinds
     */
    public static function Fetch(KubernetesCluster $cluster, array $kinds = self::Kinds): ClusterIndex {
        $items = [];
        $unreadable = [];

        foreach ($kinds as $kind) {
            /** @var \RenokiCo\PhpK8s\Kinds\K8sResource $probe */
            $probe = new $kind($cluster);
            $key = self::KeyOfPath($probe->allResourcesPath(false));
            if ($key === null) {
                continue;
            }

            try {
                $found = [];
                foreach ($probe->allNamespaces([]) as $resource) {
                    $attributes = $resource->toArray();
                    $namespace = $attributes['metadata']['namespace'] ?? '';
                    $name = $attributes['metadata']['name'] ?? '';
                    $found["{$namespace}/{$name}"] = $attributes;
                }
                $items[$key] = $found;
            } catch (\Throwable $e) {
                // A CRD the cluster does not have, or a kind kso may not list. Left out, so
                // those steps ask the api server themselves.
                $unreadable[$key] = KubeHelper::PrintException($e);
            }
        }

        if ($unreadable) {
            Data::debug('Not indexed:', implode(', ', array_keys($unreadable)));
        }

        return new ClusterIndex($items, $unreadable);
    }

    /**
     * An index of the kinds given, holding the resources given - what `Fetch()` would have made
     * of a cluster with exactly those in it. A kind with no resources is still *covered*: it was
     * looked for and there was nothing, which is what tells a step its resource is gone.
     *
     * @param array<class-string, array<string, array>> $resourcesByKind kind => "namespace/name" => the resource
     * @param list<class-string> $kinds the kinds that were looked at; the keys above by default
     */
    public static function Of(array $resourcesByKind, ?array $kinds = null): ClusterIndex {
        $items = [];
        foreach ($kinds ?? array_keys($resourcesByKind) as $kind) {
            $key = self::KeyFor($kind);
            if ($key !== null) {
                $items[$key] = $resourcesByKind[$kind] ?? [];
            }
        }
        return new ClusterIndex($items, []);
    }

    /**
     * The key a kind's resources are held under - `/apis/apps/v1|deployments` for a Deployment.
     */
    public static function KeyFor(string $kind): ?string {
        /** @var \RenokiCo\PhpK8s\Kinds\K8sResource $probe */
        $probe = new $kind();
        return self::KeyOfPath($probe->allResourcesPath(false));
    }

    /**
     * What the api server would answer for a GET of one resource: the resource, `false` when the
     * kind is indexed and it is not there, and null when the kind is not indexed at all - which
     * is the caller's cue to ask the cluster.
     *
     * @return array|false|null
     */
    public function answerFor(string $path): array|false|null {
        if (!preg_match(self::PathPattern, $path, $parts)) {
            return null;
        }
        [, $group, $namespace, $plural] = $parts;
        $name = $parts[4] ?? '';
        if ($name === '') {
            // A list, not one resource. Lists are not served from here: the caller asked for
            // everything of a kind in a namespace, which is not what was indexed.
            return null;
        }

        $key = "{$group}|{$plural}";
        if (!array_key_exists($key, $this->items)) {
            return null;
        }

        return $this->items[$key]["{$namespace}/{$name}"] ?? false;
    }

    public function covers(string $path): bool {
        return $this->answerFor($path) !== null;
    }

    public function count(): int {
        return array_sum(array_map('count', $this->items));
    }

    private static function KeyOfPath(string $path): ?string {
        if (!preg_match(self::PathPattern, $path, $parts)) {
            return null;
        }
        return "{$parts[1]}|{$parts[3]}";
    }

}
