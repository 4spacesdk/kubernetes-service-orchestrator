<?php namespace App\Libraries\Health\Diagnosis;

use App\Entities\Deployment;
use App\Libraries\Audit\Audit;
use App\Libraries\Kubernetes\DeploymentLogs;
use App\Libraries\Kubernetes\DeploymentMetrics;
use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\KubeHelper;
use Config\Database;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * Reads the `Evidence` for one deployment, when somebody asks why it is doing badly.
 *
 * **Only what the pods call for.** The pods and the namespace's events always; the registry's tags
 * and the pull secrets when an image cannot be pulled, metrics when a container ran out of memory,
 * the previous containers' logs when one has restarted. A healthy deployment costs two calls.
 *
 * The events are listed for the namespace and picked out here, by the names of the workload, its
 * ReplicaSets and its pods - php-k8s' own event filter narrows by kind only.
 *
 * Each extra is on its own: a registry or a metrics-server that does not answer leaves that
 * piece out, and the rules say they cannot tell. Only the pods are required.
 */
class EvidenceGatherer {

    public function __construct(
        private readonly ?KubernetesCluster $cluster = null,
    ) {
    }

    /**
     * @throws \Throwable when the pods cannot be read - there is nothing to diagnose without them.
     */
    public function gather(Deployment $deployment): Evidence {
        $cluster = $this->cluster ?? (new KubeAuth())->authenticate();
        $namespace = (string) $deployment->namespace;

        $pods = [];
        foreach ($cluster->getAllPods($namespace, ['labelSelector' => urldecode(http_build_query(['app' => "{$deployment->name},role=app"]))]) as $pod) {
            $pod = $pod->toArray();
            if (!isset($pod['metadata']['deletionTimestamp'])) {
                $pods[] = $pod;
            }
        }

        $containers = [];
        foreach ($pods as $pod) {
            $containers = [...$containers, ...($pod['status']['initContainerStatuses'] ?? []), ...($pod['status']['containerStatuses'] ?? [])];
        }
        $pullFails = (bool) array_filter($containers, fn(array $c) => in_array($c['state']['waiting']['reason'] ?? null, ['ErrImagePull', 'ImagePullBackOff'], true));
        $oomKilled = (bool) array_filter($containers, fn(array $c) => ($c['lastState']['terminated']['reason'] ?? $c['state']['terminated']['reason'] ?? null) === 'OOMKilled');
        $restarted = (bool) array_filter($containers, fn(array $c) => (int) ($c['restartCount'] ?? 0) > 0);

        [$imageTags, $imageTagsError] = $pullFails ? $this->imageTags($deployment) : [null, null];

        return new Evidence(
            version: (string) $deployment->version,
            pods: $pods,
            events: $this->events($cluster, $deployment, $pods),
            versionChange: self::LastVersionChange($deployment),
            lastMigration: self::LastMigration($deployment),
            imageTags: $imageTags,
            imageTagsError: $imageTagsError,
            pullSecrets: $pullFails ? $this->pullSecrets($cluster, $deployment) : [],
            metrics: $oomKilled ? (new DeploymentMetrics($cluster))->of($deployment) : null,
            lastDeployError: $deployment->last_deploy_error
                ? ['step' => (string) $deployment->last_deploy_error_step, 'error' => (string) $deployment->last_deploy_error, 'at' => (int) strtotime((string) $deployment->last_deploy_error_at)]
                : null,
            resources: [
                'cpu_request' => $deployment->cpu_request ?: null,
                'cpu_limit' => $deployment->cpu_limit ?: null,
                'memory_request' => $deployment->memory_request ?: null,
                'memory_limit' => $deployment->memory_limit ?: null,
            ],
            previousLogs: $restarted ? $this->previousLogs($deployment) : [],
        );
    }

    /**
     * @param list<array> $pods
     * @return list<array>
     */
    private function events(KubernetesCluster $cluster, Deployment $deployment, array $pods): array {
        $names = [(string) $deployment->name => true];
        foreach ($pods as $pod) {
            $names[$pod['metadata']['name'] ?? ''] = true;
            foreach ($pod['metadata']['ownerReferences'] ?? [] as $owner) {
                $names[$owner['name'] ?? ''] = true;
            }
        }

        try {
            $events = [];
            foreach ($cluster->getAllEvents((string) $deployment->namespace) as $event) {
                $event = $event->toArray();
                if (isset($names[$event['involvedObject']['name'] ?? ''])) {
                    $events[] = $event;
                }
            }
            return $events;
        } catch (\Throwable $e) {
            Data::debug('No events for', $deployment->name, ':', KubeHelper::PrintException($e));
            return [];
        }
    }

    /**
     * @return array{0: list<string>|null, 1: string|null}
     */
    private function imageTags(Deployment $deployment): array {
        $image = $deployment->findDeploymentSpecification()->container_image;
        if (!$image->exists()) {
            $image->find();
        }
        if (!$image->exists() || $image->getRegistryClient() === null) {
            return [null, null];
        }

        try {
            return [$image->getTags(), null];
        } catch (\Throwable $e) {
            return [null, $e->getMessage()];
        }
    }

    /**
     * @return array<string, bool|null>
     */
    private function pullSecrets(KubernetesCluster $cluster, Deployment $deployment): array {
        $image = $deployment->findDeploymentSpecification()->container_image;
        if (!$image->exists()) {
            $image->find();
        }
        if (!$image->exists()) {
            return [];
        }

        $secrets = [];
        foreach ($image->getPullSecretNames() as $name) {
            try {
                $cluster->getSecretByName($name, (string) $deployment->namespace);
                $secrets[$name] = true;
            } catch (KubernetesAPIException $e) {
                $secrets[$name] = $e->getCode() === 404 ? false : null;
            } catch (\Throwable) {
                $secrets[$name] = null;
            }
        }
        return $secrets;
    }

    /**
     * @return array<string, list<string>>
     */
    private function previousLogs(Deployment $deployment): array {
        try {
            $lines = (new DeploymentLogs((new KubeAuth())->streaming()))->recent($deployment, true);
        } catch (\Throwable $e) {
            Data::debug('No previous logs for', $deployment->name, ':', KubeHelper::PrintException($e));
            return [];
        }

        $byPod = [];
        foreach ($lines as $line) {
            $byPod[$line['pod']][] = $line['line'];
        }
        return $byPod;
    }

    /**
     * The last time the version was changed, from the audit trail - by a person or by auto update.
     *
     * @return array{from: string, to: string, at: int}|null
     */
    public static function LastVersionChange(Deployment $deployment): ?array {
        $rows = Database::connect()->table('audit_events')
            ->select('created, details')
            ->where('resource_type', Audit::TypeOf($deployment))
            ->where('resource_id', $deployment->id)
            ->where('action', Audit::Updated)
            ->like('details', '"version":[', 'both', null, true)
            ->orderBy('id', 'desc')
            ->limit(1)
            ->get()
            ->getResultArray();

        $changes = json_decode((string) ($rows[0]['details'] ?? ''), true)['changes']['version'] ?? null;
        if (!is_array($changes) || count($changes) !== 2) {
            return null;
        }

        return ['from' => (string) $changes[0], 'to' => (string) $changes[1], 'at' => (int) strtotime($rows[0]['created'])];
    }

    /**
     * @return array{id: int, status: string, image: string, log: string}|null
     */
    private static function LastMigration(Deployment $deployment): ?array {
        if (!$deployment->last_migration_job_id) {
            return null;
        }
        $job = $deployment->last_migration_job;
        if (!$job->exists()) {
            $job->find();
        }
        if (!$job->exists()) {
            return null;
        }

        return ['id' => (int) $job->id, 'status' => (string) $job->status, 'image' => (string) $job->image, 'log' => (string) $job->log];
    }

}
