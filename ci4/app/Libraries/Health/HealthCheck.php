<?php namespace App\Libraries\Health;

use App\Entities\Deployment;
use App\Entities\Workspace;
use App\Libraries\Kubernetes\ClusterIndex;
use App\Libraries\Kubernetes\IndexedCluster;
use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Push\ChangeEvent;
use App\Libraries\Push\Events;
use App\Libraries\Push\Publisher;
use App\Models\DeploymentModel;
use Config\Database;
use DebugTool\Data;

/**
 * Works out every deployment's health and writes it down.
 *
 * **Run by the scheduler once a minute** (`app:check-health`) against one `ClusterSnapshot`,
 * and **followed more closely after a deploy**: `StartFollowing()` queues a look at that one
 * deployment every ten seconds for as long as it is Progressing, so a rollout can be watched
 * without waiting for the minute. Each look queues the next; nothing sits in the worker.
 *
 * **A cluster that does not answer changes nothing.** The snapshot throws, and every stored
 * health stays as it was with a `health_checked_at` that grows old - the web app shows it as
 * stale. A timeout is not an answer, and it must not overwrite one.
 *
 * **The status is never touched.** Auto update picks deployments by status; health is beside it.
 *
 * **Webhooks wait for a new health to hold** for `SettleFor` seconds, so a rollout that passes
 * through Progressing, or a pod that restarts once, does not wake anybody. The first health a
 * deployment ever gets is only sent if it is a bad one - otherwise the upgrade that brings this
 * in would announce every deployment in the installation as healthy.
 */
class HealthCheck {

    /** Seconds between two looks at a deployment that is rolling out. */
    public const int FollowEvery = 10;

    /** Seconds after a deploy that it is followed at most; the minute run takes it from there. */
    public const int FollowFor = 900;

    /** Seconds a new health has to hold before the webhook is sent. */
    public const int SettleFor = 120;

    /**
     * Where the snapshot comes from. The test suite puts a closure here that hands back a
     * snapshot built from arrays; the real one reads the cluster.
     *
     * @var null|\Closure(bool $withKnative): ClusterSnapshot
     */
    public static ?\Closure $snapshots = null;

    /**
     * The cluster the statuses are recomputed through - the same seam as `$snapshots`.
     * The real one authenticates and then lists every kind kso deploys; the test suite hands
     * back one wired to an index built from arrays.
     *
     * @var null|\Closure(): IndexedCluster
     */
    public static ?\Closure $indexedClusters = null;

    private const string LockName = 'kso-health';

    /**
     * @return array{checked: int, changed: int, notified: int}
     * @throws \Throwable when the cluster cannot be read - nothing has been written then.
     */
    public static function Run(?int $onlyDeploymentId = null, ?int $now = null): array {
        return self::Locked(fn() => self::RunLocked($onlyDeploymentId, $now ?? time()));
    }

    /**
     * @return array{checked: int, changed: int, notified: int}
     */
    private static function RunLocked(?int $onlyDeploymentId, int $now): array {
        $model = new DeploymentModel();
        if ($onlyDeploymentId !== null) {
            $model->where('id', $onlyDeploymentId);
        }
        /** @var Deployment $deployments */
        $deployments = $model->find();

        // The status first, and for every deployment at once off one set of lists: health is
        // worked out from the status (a Draft has none), so it has to be the status as of now.
        // Only in the whole run - a single deployment is cheaper asked for step by step
        // than it is to list every kind in the cluster for, and the deploy it is being followed
        // through has just checked its own status anyway.
        $statusesChanged = 0;
        $anythingToCheck = false;
        foreach ($deployments as $deployment) {
            $anythingToCheck = $anythingToCheck || $deployment->status !== \DeploymentStatusTypes::Inactive;
        }
        if ($onlyDeploymentId === null && $anythingToCheck) {
            $statusesChanged = self::RecomputeStatuses($deployments);
        }

        $workloads = [];
        $needsTheCluster = false;
        $withKnative = false;
        foreach ($deployments as $deployment) {
            $workloadType = $deployment->status === \DeploymentStatusTypes::Draft
                ? null
                : $deployment->findDeploymentSpecification()->workload_type;
            $workload = $workloadType ? Workload::Of($deployment, $workloadType) : null;
            $workloads[$deployment->id] = $workload;

            if ($workload && $workload->suspendedBecause === null) {
                $needsTheCluster = true;
                $withKnative = $withKnative || $workloadType === \WorkloadTypes::KNativeService;
            }
        }

        // Read before anything is written, so a failure leaves every row as it was.
        $snapshot = $needsTheCluster ? self::Snapshot($withKnative) : ClusterSnapshot::FromArrays([], []);

        $summary = ['checked' => 0, 'changed' => 0, 'notified' => 0, 'status' => $statusesChanged];
        $workspaceIds = [];
        foreach ($deployments as $deployment) {
            $workload = $workloads[$deployment->id];
            $result = $workload ? HealthEvaluator::Evaluate($workload, $snapshot, $now) : null;

            $summary['checked'] += $result ? 1 : 0;
            if (self::Store($deployment, $result, $now)) {
                $summary['changed']++;
                Data::debug($deployment->namespace . '/' . $deployment->name, 'is now', $deployment->health ?? 'without health', $deployment->health_reason ?? '');
            }
            if (self::Notify($deployment, $now)) {
                $summary['notified']++;
            }
            if ($deployment->workspace_id) {
                $workspaceIds[$deployment->workspace_id] = true;
            }
        }

        foreach (array_keys($workspaceIds) as $workspaceId) {
            $workspace = new Workspace();
            $workspace->find($workspaceId);
            if ($workspace->exists()) {
                // The deployments' statuses were recomputed above without the cascade, so this
                // is where a workspace is told to add them up again.
                $workspace->checkStatus();
                $workspace->updateHealth($now);
            }
        }

        if ($summary['changed'] > 0) {
            Publisher::getInstance()->send(Events::Deployments_Changed_Health(), (new ChangeEvent(null, $summary))->toArray());
        }

        return $summary;
    }

    /**
     * Ask every deployment to check its own status, with the cluster answering from one index
     * instead of a call per step. `checkStatus()` and the steps are untouched: `KubeAuth` hands
     * them the indexed cluster while this runs.
     *
     * A kind the index could not read - a CRD this cluster does not have, or a custom resource,
     * which can be any kind at all - falls through to the api server, so the answer is the same
     * either way.
     *
     * @param \App\Entities\Deployment $deployments
     * @return int how many statuses changed
     */
    private static function RecomputeStatuses($deployments): int {
        $cluster = self::IndexedCluster();

        $before = [];
        foreach ($deployments as $deployment) {
            $before[$deployment->id] = $deployment->status;
        }

        KubeAuth::Using($cluster, function () use ($deployments) {
            foreach ($deployments as $deployment) {
                // Without the cascade: the workspaces add their deployments up once each,
                // afterwards, rather than once per deployment that changed.
                $deployment->checkStatus(false);
            }
        });

        $changed = 0;
        foreach ($deployments as $deployment) {
            if ($deployment->status !== $before[$deployment->id]) {
                $changed++;
                Data::debug($deployment->namespace . '/' . $deployment->name, 'is now', $deployment->status, '(was', $before[$deployment->id] . ')');
            }
        }

        Data::debug('status:', $cluster->served, 'answered from the index,', $cluster->passedOn, 'asked of the cluster');

        return $changed;
    }

    /**
     * A cluster that answers what one round of lists found. Authenticated first and used for the
     * lists themselves, so a cluster that cannot be reached throws here - before a single status
     * is written - rather than reading as a cluster with nothing in it.
     *
     * @throws \Throwable
     */
    private static function IndexedCluster(): IndexedCluster {
        if (self::$indexedClusters) {
            return (self::$indexedClusters)();
        }

        $cluster = (new KubeAuth())->indexed(ClusterIndex::Of([]));
        return $cluster->useIndex(ClusterIndex::Fetch($cluster));
    }

    /**
     * After a deploy: look again in ten seconds, and keep looking while it rolls out.
     */
    public static function StartFollowing(int $deploymentId): void {
        self::QueueFollow($deploymentId, time() + self::FollowFor);
    }

    public static function Follow(int $deploymentId, int $until): void {
        if ($deploymentId <= 0) {
            return;
        }

        try {
            self::Run($deploymentId);
        } catch (\Throwable $e) {
            // The minute run has the same problem and will say so; a rollout followed into a
            // cluster that has gone away is not followed further.
            Data::debug('Could not follow deployment', $deploymentId, ':', $e->getMessage());
            return;
        }

        $deployment = new Deployment();
        $deployment->find($deploymentId);
        if ($deployment->exists() && $deployment->health === \HealthStatusTypes::Progressing && time() < $until) {
            self::QueueFollow($deploymentId, $until);
        }
    }

    /**
     * The newest time any deployment was checked - how the status bar tells a check that has
     * stopped from one that has nothing to report. Null when nothing has been checked.
     */
    public static function LastCheckedAt(): ?string {
        $row = Database::connect()->table('deployments')->selectMax('health_checked_at', 'at')->get()->getRow();
        return $row->at ?? null;
    }

    private static function Snapshot(bool $withKnative): ClusterSnapshot {
        if (self::$snapshots) {
            return (self::$snapshots)($withKnative);
        }
        return ClusterSnapshot::Fetch((new KubeAuth())->authenticate(), $withKnative);
    }

    /**
     * @return bool Whether the health itself changed - not only its reason.
     */
    private static function Store(Deployment $deployment, ?HealthResult $result, int $now): bool {
        $health = $result?->health;
        $reason = $result?->reason;
        $at = date('Y-m-d H:i:s', $now);

        $healthChanged = $deployment->health !== $health;
        $reasonChanged = ($deployment->health_reason ?? '') !== ($reason ?? '');

        if ($healthChanged) {
            $deployment->health = $health;
            $deployment->health_severity = \HealthStatusTypes::Severity($health);
            $deployment->health_changed_at = $health === null ? null : $at;
        }
        if ($reasonChanged) {
            $deployment->health_reason = $reason;
        }
        $deployment->health_checked_at = $health === null ? null : $at;
        $deployment->save();

        if ($healthChanged || $reasonChanged) {
            Publisher::getInstance()->send(
                Events::Deployment_Changed_Health($deployment->id),
                (new ChangeEvent(null, $deployment->toArray()))->toArray()
            );
        }

        return $healthChanged;
    }

    /**
     * @return bool Whether a webhook was sent.
     */
    private static function Notify(Deployment $deployment, int $now): bool {
        $health = $deployment->health;
        $notified = $deployment->health_notified;
        if ($health === null || $health === $notified) {
            return false;
        }
        if ($now - strtotime((string) $deployment->health_changed_at) < self::SettleFor) {
            return false;
        }

        $deployment->health_notified = $health;
        $deployment->save();

        if ($notified === null && !\HealthStatusTypes::IsBad($health)) {
            return false;
        }

        Publisher::getInstance()->send(
            Events::Deployment_Health_Settled(),
            (new ChangeEvent(null, [...$deployment->toArray(), 'previous_health' => $notified]))->toArray()
        );
        return true;
    }

    private static function QueueFollow(int $deploymentId, int $until): void {
        Publisher::getInstance()->send(
            Events::Deployment_Health_Follow(),
            (new ChangeEvent(null, ['deployment_id' => $deploymentId, 'until' => $until]))->toArray()
        );
    }

    /**
     * The minute run and a rollout being followed can land on the same row at once, and the
     * webhook is decided from what is stored - two writers could both find it unsent. Taken
     * before the rows are read, so the second one reads what the first one wrote.
     */
    private static function Locked(\Closure $work): mixed {
        $db = Database::connect();
        $db->query('SELECT GET_LOCK(?, 30)', [self::LockName]);
        try {
            return $work();
        } finally {
            $db->query('SELECT RELEASE_LOCK(?)', [self::LockName]);
        }
    }

}
