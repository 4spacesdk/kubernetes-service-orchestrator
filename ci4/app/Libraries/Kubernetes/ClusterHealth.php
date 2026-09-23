<?php namespace App\Libraries\Kubernetes;

use App\Libraries\Health\HealthCheck;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sNodeMetrics;
use Config\Database;
use DebugTool\Data;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * The window behind the status bar's dot: the cluster's nodes, how kso's deployments and
 * workspaces are doing, and whether kso's own scheduler is keeping up.
 *
 * **Asked for when the window opens, never polled.** Three calls to the cluster - the nodes,
 * every pod (for what each node has been asked for), and metrics-server - and two counts from kso's
 * own database, which cost the cluster nothing.
 *
 * A node's figures are `kubectl describe node` in kso's units - millicores and bytes: what it has,
 * what pods may be scheduled onto (allocatable), what the pods on it have requested, and what it
 * uses right now. Requested against allocatable is what decides whether the next pod fits; usage
 * is only how busy it is.
 */
class ClusterHealth {

    /** Conditions whose being true means the kubelet is short of something and evicting. */
    private const array Pressures = ['MemoryPressure', 'DiskPressure', 'PIDPressure'];

    /** Minutes the scheduler may be quiet before it is behind. Its health check runs every minute. */
    public const int SchedulerBehindAfter = 3;

    public function __construct(
        private readonly KubernetesCluster $cluster,
    ) {
    }

    /**
     * @throws \Throwable when the nodes cannot be read - the window says so instead.
     */
    public function read(int $now): array {
        $nodes = [];
        foreach ($this->cluster->node()->all() as $node) {
            $nodes[] = $node->toArray();
        }

        $pods = [];
        foreach ($this->cluster->getAllPodsFromAllNamespaces([]) as $pod) {
            $pods[] = $pod->toArray();
        }

        $metrics = null;
        $metricsError = null;
        try {
            $metrics = [];
            foreach ((new K8sNodeMetrics($this->cluster))->all() as $nodeMetrics) {
                $metrics[] = $nodeMetrics->toArray();
            }
        } catch (\Throwable $e) {
            // No metrics-server, which is an installation without it, not a failure.
            $metrics = null;
            $metricsError = KubeHelper::PrintException($e);
            Data::debug('No node metrics:', $metricsError);
        }

        return [
            'nodes' => self::Nodes($nodes, $pods, $metrics, $now),
            'metrics_available' => $metrics !== null,
            'metrics_reason' => $metricsError,
            'deployments' => self::CountByHealth('deployments'),
            'workspaces' => self::CountByHealth('workspaces'),
            'scheduler' => self::Scheduler($now),
            'namespaces' => self::Namespaces($this->allNamespaces(), $pods, self::KsoNamespaces(), \App\Entities\System::InstallationId(), $now),
        ];
    }

    /** Kubernetes' own - there on every cluster, and nobody's to account for. */
    private const array KubernetesNamespaces = ['default', 'kube-system', 'kube-public', 'kube-node-lease'];

    /**
     * Every namespace, and whose it is: `kso` - a workspace or deployment of this kso's lives in it -
     * `theirs` - another kso's, by its mark - `kubernetes`, or `other`. Read-only: in kso a namespace
     * is a workspace's, not something of its own to take over.
     *
     * @param list<array> $namespaces
     * @param list<array> $pods Every pod in the cluster
     * @param array<string, array{workspace_id: ?int, workspace: ?string}> $kso namespace => the workspace in it
     * @return list<array{name: string, owner: string, pods: int, age_seconds: ?int, workspace_id: ?int, workspace: ?string}>
     */
    public static function Namespaces(array $namespaces, array $pods, array $kso, string $installationId, int $now): array {
        $podCounts = [];
        foreach ($pods as $pod) {
            $namespace = $pod['metadata']['namespace'] ?? '';
            $podCounts[$namespace] = ($podCounts[$namespace] ?? 0) + 1;
        }

        $rows = [];
        foreach ($namespaces as $namespace) {
            $name = (string) ($namespace['metadata']['name'] ?? '');
            $mark = $namespace['metadata']['annotations'][KubeHelper::InstallationAnnotation] ?? null;
            $created = strtotime((string) ($namespace['metadata']['creationTimestamp'] ?? '')) ?: null;

            $owner = match (true) {
                isset($kso[$name]) => 'kso',
                $mark !== null && $mark !== $installationId => 'theirs',
                in_array($name, self::KubernetesNamespaces, true) => 'kubernetes',
                default => 'other',
            };

            $rows[] = [
                'name' => $name,
                'owner' => $owner,
                'pods' => $podCounts[$name] ?? 0,
                'age_seconds' => $created === null ? null : $now - $created,
                'workspace_id' => $kso[$name]['workspace_id'] ?? null,
                'workspace' => $kso[$name]['workspace'] ?? null,
            ];
        }

        $order = ['kso' => 0, 'other' => 1, 'theirs' => 2, 'kubernetes' => 3];
        usort($rows, fn(array $a, array $b) => [$order[$a['owner']], $a['name']] <=> [$order[$b['owner']], $b['name']]);

        return $rows;
    }

    /**
     * @return list<array>
     */
    private function allNamespaces(): array {
        $namespaces = [];
        foreach ($this->cluster->namespace()->all() as $namespace) {
            $namespaces[] = $namespace->toArray();
        }
        return $namespaces;
    }

    /**
     * The namespaces this kso's workspaces and deployments live in.
     *
     * @return array<string, array{workspace_id: ?int, workspace: ?string}>
     */
    private static function KsoNamespaces(): array {
        $db = Database::connect();
        $kso = [];
        foreach ($db->table('workspaces')->select('id, name_readable, namespace')->where('deletion_id', null)->get()->getResultArray() as $row) {
            $kso[$row['namespace']] = ['workspace_id' => (int) $row['id'], 'workspace' => $row['name_readable']];
        }
        foreach ($db->table('deployments')->select('namespace')->where('deletion_id', null)->get()->getResultArray() as $row) {
            $kso[$row['namespace']] ??= ['workspace_id' => null, 'workspace' => null];
        }
        return $kso;
    }

    /**
     * @param list<array> $nodes
     * @param list<array> $pods Every pod in the cluster
     * @param list<array>|null $metrics Null when metrics-server could not be asked
     * @return list<array>
     */
    public static function Nodes(array $nodes, array $pods, ?array $metrics, int $now): array {
        $requested = [];
        foreach ($pods as $pod) {
            $node = $pod['spec']['nodeName'] ?? null;
            // A finished pod holds nothing: the scheduler does not count it either.
            if ($node === null || in_array($pod['status']['phase'] ?? '', ['Succeeded', 'Failed'], true)) {
                continue;
            }
            [$cpu, $memory] = self::PodRequests($pod);
            $requested[$node]['cpu'] = ($requested[$node]['cpu'] ?? 0) + $cpu;
            $requested[$node]['memory'] = ($requested[$node]['memory'] ?? 0) + $memory;
            $requested[$node]['pods'] = ($requested[$node]['pods'] ?? 0) + 1;
        }

        $usage = [];
        foreach ($metrics ?? [] as $nodeMetrics) {
            $usage[$nodeMetrics['metadata']['name'] ?? ''] = $nodeMetrics['usage'] ?? [];
        }

        return array_map(function (array $node) use ($requested, $usage, $metrics, $now) {
            $name = $node['metadata']['name'] ?? '';
            $status = $node['status'] ?? [];
            $conditions = [];
            foreach ($status['conditions'] ?? [] as $condition) {
                $conditions[$condition['type'] ?? ''] = $condition;
            }
            $created = strtotime((string) ($node['metadata']['creationTimestamp'] ?? '')) ?: null;

            return [
                'name' => $name,
                'ready' => ($conditions['Ready']['status'] ?? null) === 'True',
                'ready_reason' => ($conditions['Ready']['status'] ?? null) === 'True' ? null : ($conditions['Ready']['message'] ?? $conditions['Ready']['reason'] ?? null),
                'unschedulable' => (bool) ($node['spec']['unschedulable'] ?? false),
                'roles' => self::Roles($node['metadata']['labels'] ?? []),
                'kubelet_version' => $status['nodeInfo']['kubeletVersion'] ?? null,
                'age_seconds' => $created === null ? null : $now - $created,
                'pressures' => array_values(array_filter(
                    self::Pressures,
                    fn(string $type) => ($conditions[$type]['status'] ?? null) === 'True',
                )),
                'pods' => $requested[$name]['pods'] ?? 0,
                'pods_allocatable' => isset($status['allocatable']['pods']) ? (int) $status['allocatable']['pods'] : null,
                'cpu_capacity' => Quantity::Millicores($status['capacity']['cpu'] ?? null),
                'cpu_allocatable' => Quantity::Millicores($status['allocatable']['cpu'] ?? null),
                'cpu_requested' => $requested[$name]['cpu'] ?? 0,
                'cpu_usage' => $metrics === null ? null : Quantity::Millicores($usage[$name]['cpu'] ?? null),
                'memory_capacity' => Quantity::Bytes($status['capacity']['memory'] ?? null),
                'memory_allocatable' => Quantity::Bytes($status['allocatable']['memory'] ?? null),
                'memory_requested' => $requested[$name]['memory'] ?? 0,
                'memory_usage' => $metrics === null ? null : Quantity::Bytes($usage[$name]['memory'] ?? null),
            ];
        }, $nodes);
    }

    /**
     * What a pod asks of its node, as the scheduler adds it up: its containers together, or its
     * largest init container if that is more - they run one at a time, before the rest.
     *
     * @return array{0: int, 1: int} [millicores, bytes]
     */
    private static function PodRequests(array $pod): array {
        $sum = fn(array $containers, string $resource, \Closure $parse) => array_sum(array_map(
            fn(array $container) => $parse($container['resources']['requests'][$resource] ?? null) ?? 0,
            $containers,
        ));
        $max = fn(array $containers, string $resource, \Closure $parse) => max([0, ...array_map(
            fn(array $container) => $parse($container['resources']['requests'][$resource] ?? null) ?? 0,
            $containers,
        )]);

        $containers = $pod['spec']['containers'] ?? [];
        $init = $pod['spec']['initContainers'] ?? [];
        $millicores = fn(?string $q) => Quantity::Millicores($q);
        $bytes = fn(?string $q) => Quantity::Bytes($q);

        return [
            max($sum($containers, 'cpu', $millicores), $max($init, 'cpu', $millicores)),
            max($sum($containers, 'memory', $bytes), $max($init, 'memory', $bytes)),
        ];
    }

    /**
     * @return list<string>
     */
    private static function Roles(array $labels): array {
        $roles = [];
        foreach (array_keys($labels) as $label) {
            if (str_starts_with($label, 'node-role.kubernetes.io/')) {
                $roles[] = substr($label, strlen('node-role.kubernetes.io/'));
            }
        }
        return $roles;
    }

    /**
     * @return array<string, int> health => how many; `none` for those with no health
     */
    private static function CountByHealth(string $table): array {
        $rows = Database::connect()->table($table)
            ->select('health, COUNT(*) AS count')
            ->where('deletion_id', null)
            ->groupBy('health')
            ->get()
            ->getResultArray();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['health'] ?? 'none'] = (int) $row['count'];
        }
        return $counts;
    }

    /**
     * kso's own cron runner, judged by the job that runs every minute: runtime health.
     *
     * @return array{last_run: ?string, health_checked_at: ?string, behind: bool}
     */
    private static function Scheduler(int $now): array {
        $lastRun = Database::connect()->table('cron_jobs')
            ->select('last_run')
            ->where('id', \CronJobIds::CheckHealth)
            ->get()
            ->getRow()
            ->last_run ?? null;

        $at = $lastRun ? strtotime((string) $lastRun) : false;

        return [
            'last_run' => $lastRun,
            'health_checked_at' => HealthCheck::LastCheckedAt(),
            'behind' => $at === false || $now - $at >= self::SchedulerBehindAfter * 60,
        ];
    }

}
