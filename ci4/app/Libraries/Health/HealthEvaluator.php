<?php namespace App\Libraries\Health;

/**
 * Whether a workload is doing well right now, from what the cluster says about it.
 *
 * The rules lean on Kubernetes' own signals rather than numbers made up here - the rollout's
 * generations and replica counts, its progress deadline, the reason a container is waiting -
 * so a threshold is only invented where Kubernetes has none (restarts, unschedulable pods).
 * There are no settings: fixed rules are easier to explain, and a setting can come later.
 *
 * Pure: everything it needs is in the `Workload`, the `ClusterSnapshot` and the time it is
 * given, so every rule is tested in the unit suite.
 */
class HealthEvaluator {

    /**
     * A container waiting for one of these will not get better on its own. `ContainerCreating`
     * and `PodInitializing` are the ordinary waits and are not on the list.
     */
    private const array BadWaitingReasons = [
        'CrashLoopBackOff',
        'ImagePullBackOff',
        'ErrImagePull',
        'InvalidImageName',
        'CreateContainerConfigError',
        'CreateContainerError',
        'RunContainerError',
    ];

    /** Seconds a pod may wait for a node before it counts. A new node takes a minute or two. */
    public const int UnschedulableGrace = 300;

    /** Seconds a restart is remembered. */
    public const int RestartWindow = 3600;

    /**
     * How many restarts in a container's life make a recent one a problem rather than a blip.
     *
     * Kubernetes keeps a lifetime count and the time of the last termination only, so "three
     * in the last hour" cannot be read off a pod. What can: the last restart was within the
     * hour *and* it was not the first or second. A container that crashed three times last
     * month and once today is counted, which is the price of not keeping a history.
     */
    public const int RestartsThatCount = 3;

    /**
     * Conditions whose being true is the bad news. Kubernetes has both polarities, and the name is
     * the only thing that says which - `Ready: False` is ordinary, `Degraded: True` is not.
     */
    private const array FailureConditions = ['Degraded', 'Failed', 'Error', 'ReconcileError', 'Stalled'];

    /** Conditions whose being false is the bad news: the operator says it could not do its work. */
    private const array MustBeTrueConditions = ['ReconcileSuccess', 'Reconciled', 'Synced'];

    /**
     * Conditions that say whether it is up yet. False or Unknown is "not yet", not "broken" -
     * Argo CD's own check for a RabbitmqCluster reads `AllReplicasReady: False` as Progressing,
     * and so does this.
     */
    private const array ReadinessConditions = ['Ready', 'Available', 'ClusterAvailable', 'AllReplicasReady', 'Initialized', 'Established'];

    /**
     * Seconds a custom resource may be on its way before "not yet" becomes "not going to".
     *
     * Argo CD leaves such a resource Progressing for as long as it likes, which is honest and
     * useless: a RabbitmqCluster that has said `AllReplicasReady: False` since yesterday is not
     * progressing towards anything. The condition carries the time it last changed, so this costs
     * nothing to ask.
     */
    public const int CustomResourceGrace = 600;

    /**
     * Null for a workload kso cannot read the health of: a DaemonSet is not deployed by kso yet,
     * and a custom resource that says nothing about itself says nothing.
     */
    public static function Evaluate(Workload $workload, ClusterSnapshot $snapshot, int $now): ?HealthResult {
        if ($workload->suspendedBecause !== null) {
            return new HealthResult(\HealthStatusTypes::Suspended, $workload->suspendedBecause);
        }

        return match ($workload->workloadType) {
            \WorkloadTypes::Deployment => self::OfDeployment($workload, $snapshot, $now),
            \WorkloadTypes::KNativeService => self::OfKnativeService($workload, $snapshot, $now),
            \WorkloadTypes::CustomResource => self::OfCustomResource($workload, $now),
            default => null,
        };
    }

    private static function OfDeployment(Workload $workload, ClusterSnapshot $snapshot, int $now): HealthResult {
        $deployment = $snapshot->deployment($workload->namespace, $workload->name);
        if ($deployment === null) {
            return new HealthResult(\HealthStatusTypes::Missing, 'The Deployment is not in the cluster');
        }

        if ($deployment['spec']['paused'] ?? false) {
            return new HealthResult(\HealthStatusTypes::Suspended, 'The rollout is paused in the cluster');
        }

        $status = $deployment['status'] ?? [];
        $desired = (int) ($deployment['spec']['replicas'] ?? 1);
        $generation = (int) ($deployment['metadata']['generation'] ?? 0);
        $observed = (int) ($status['observedGeneration'] ?? 0);
        $total = (int) ($status['replicas'] ?? 0);
        $updated = (int) ($status['updatedReplicas'] ?? 0);
        $available = (int) ($status['availableReplicas'] ?? 0);

        [$degraded, $notes] = self::PodProblems($snapshot->pods($workload->namespace, $workload->name), $now);

        // Kubernetes' own verdict on a rollout that has stopped moving - `progressDeadlineSeconds`
        // on the Deployment, ten minutes unless somebody set it.
        $progressing = self::Condition($status, 'Progressing');
        if (($progressing['reason'] ?? null) === 'ProgressDeadlineExceeded') {
            array_unshift($degraded, 'The rollout has stopped making progress');
        }

        $migration = self::Migration($workload);
        if ($migration === \HealthStatusTypes::Degraded) {
            $degraded[] = "The migration for {$workload->version} failed";
        }

        if ($degraded) {
            return HealthResult::Because(\HealthStatusTypes::Degraded, $degraded);
        }

        if ($migration === \HealthStatusTypes::Progressing) {
            return new HealthResult(\HealthStatusTypes::Progressing, "Migrating to {$workload->version}");
        }

        // Finished is Kubernetes' word, not a sum: the controller marks `Progressing` with
        // `NewReplicaSetAvailable` when the new ReplicaSet is up, and leaves it there. The
        // counts alone cannot tell a rollout waiting for its last pod from a finished one
        // whose pod has since lost its readiness - the first is Progressing, the second is
        // not going to finish anything and is Degraded. Without the condition (it is always
        // there on a real cluster) the counts decide, as Argo CD reads them.
        $countsSayDone = $observed >= $generation && $updated >= $desired && $total <= $updated;
        $rolledOut = $countsSayDone && match ($progressing['reason'] ?? null) {
            'NewReplicaSetAvailable' => true,
            null => $available >= $updated,
            default => false,
        };
        if (!$rolledOut) {
            return new HealthResult(\HealthStatusTypes::Progressing, "Rolling out: {$updated}/{$desired} updated, {$available} available");
        }

        if ($available < $desired) {
            return HealthResult::Because(\HealthStatusTypes::Degraded, ["{$available}/{$desired} ready", ...$notes]);
        }

        return HealthResult::Because(\HealthStatusTypes::Healthy, $notes);
    }

    /**
     * A Knative Service reports on itself through its `Ready` condition, the way Argo CD and
     * Flux read it. Scaled to zero it has no pods and is Ready - that is it working, not idle.
     */
    private static function OfKnativeService(Workload $workload, ClusterSnapshot $snapshot, int $now): HealthResult {
        if ($snapshot->knativeError !== null) {
            return new HealthResult(\HealthStatusTypes::Unknown, "The Knative Services could not be read: {$snapshot->knativeError}");
        }

        $service = $snapshot->knativeService($workload->namespace, $workload->name);
        if ($service === null) {
            return new HealthResult(\HealthStatusTypes::Missing, 'The Knative Service is not in the cluster');
        }

        $status = $service['status'] ?? [];
        $pods = $snapshot->pods($workload->namespace, $workload->name);
        [$degraded, $notes] = self::PodProblems($pods, $now);

        $ready = self::Condition($status, 'Ready');
        if (($ready['status'] ?? null) === 'False') {
            array_unshift($degraded, self::Described($ready, 'Not ready'));
        }

        $migration = self::Migration($workload);
        if ($migration === \HealthStatusTypes::Degraded) {
            $degraded[] = "The migration for {$workload->version} failed";
        }

        if ($degraded) {
            return HealthResult::Because(\HealthStatusTypes::Degraded, $degraded);
        }

        if ($migration === \HealthStatusTypes::Progressing) {
            return new HealthResult(\HealthStatusTypes::Progressing, "Migrating to {$workload->version}");
        }

        $generation = (int) ($service['metadata']['generation'] ?? 0);
        $observed = (int) ($status['observedGeneration'] ?? 0);
        if ($observed < $generation || ($ready['status'] ?? null) !== 'True') {
            return new HealthResult(\HealthStatusTypes::Progressing, $ready ? self::Described($ready, 'Rolling out') : 'Rolling out');
        }

        if (!$pods) {
            $notes[] = 'Scaled to zero';
        }

        return HealthResult::Because(\HealthStatusTypes::Healthy, $notes);
    }

    /**
     * A custom resource is whatever the operator wrote, so the only one who can say how it is
     * doing is the operator - through the conditions it puts on the resource.
     *
     * Argo CD writes a script per CRD; kso reads the conditions by convention instead, which is
     * the part every operator that reports anything has in common. What it cannot do is know that
     * `ClusterAvailable` outranks `AllReplicasReady` on a RabbitmqCluster - so it says which
     * condition decided, and leaves the judgement to the person reading it.
     *
     * Null when there is nothing to read: a ConfigMap has no conditions, and no health is a
     * truer answer than a guess.
     */
    private static function OfCustomResource(Workload $workload, int $now): ?HealthResult {
        $resource = $workload->customResource;
        if ($resource === null) {
            return new HealthResult(\HealthStatusTypes::Missing, 'The custom resource is not in the cluster');
        }

        // The operator's own way of being switched off, and the one Argo CD's RabbitmqCluster
        // check reads as Suspended.
        if (($resource['spec']['replicas'] ?? null) === 0) {
            return new HealthResult(\HealthStatusTypes::Suspended, 'Scaled to zero');
        }

        $conditions = $resource['status']['conditions'] ?? [];
        if (!$conditions) {
            return null;
        }

        $progressing = [];
        foreach ($conditions as $condition) {
            $type = $condition['type'] ?? '';
            $status = $condition['status'] ?? '';

            if (in_array($type, self::FailureConditions, true) && $status === 'True') {
                return new HealthResult(\HealthStatusTypes::Degraded, self::Described($condition, $type));
            }
            if (in_array($type, self::MustBeTrueConditions, true) && $status === 'False') {
                return new HealthResult(\HealthStatusTypes::Degraded, self::Described($condition, "{$type} is false"));
            }
            if (in_array($type, self::ReadinessConditions, true) && $status !== 'True') {
                $since = $now - self::Time($condition['lastTransitionTime'] ?? null, $now);
                $described = self::Described($condition, "{$type}: {$status}");
                if ($since >= self::CustomResourceGrace) {
                    return new HealthResult(\HealthStatusTypes::Degraded, $described . ', ' . self::For($since));
                }
                $progressing[] = $described;
            }
        }

        if ($progressing) {
            return HealthResult::Because(\HealthStatusTypes::Progressing, $progressing);
        }

        // Every condition kso knows is true - and if it knew none of them, it has been told
        // nothing it understands.
        $known = array_filter(
            $conditions,
            fn(array $condition) => in_array($condition['type'] ?? '', [...self::ReadinessConditions, ...self::MustBeTrueConditions], true)
        );

        return $known ? new HealthResult(\HealthStatusTypes::Healthy) : null;
    }

    /**
     * What is wrong with a workload's pods, and what is worth knowing without being wrong.
     *
     * @param list<array> $pods
     * @return array{0: list<string>, 1: list<string>} [degraded, notes]
     */
    private static function PodProblems(array $pods, int $now): array {
        $degraded = [];
        $notes = [];

        foreach ($pods as $pod) {
            $podName = $pod['metadata']['name'] ?? '';
            $status = $pod['status'] ?? [];

            $scheduled = self::Condition($status, 'PodScheduled');
            if (($scheduled['status'] ?? null) === 'False'
                && ($scheduled['reason'] ?? null) === 'Unschedulable'
                && $now - self::Time($scheduled['lastTransitionTime'] ?? null, $now) >= self::UnschedulableGrace) {
                $degraded[] = "Unschedulable ({$podName})";
            }

            $containers = [...($status['initContainerStatuses'] ?? []), ...($status['containerStatuses'] ?? [])];
            foreach ($containers as $container) {
                $waitingFor = $container['state']['waiting']['reason'] ?? null;
                if (in_array($waitingFor, self::BadWaitingReasons, true)) {
                    $degraded[] = "{$waitingFor} ({$podName})";
                    continue;
                }

                $terminated = $container['lastState']['terminated'] ?? null;
                if (!$terminated || !isset($terminated['finishedAt'])) {
                    continue;
                }
                $secondsAgo = $now - self::Time($terminated['finishedAt'], $now);
                if ($secondsAgo > self::RestartWindow) {
                    continue;
                }
                $ago = self::Ago($secondsAgo);
                $restarts = (int) ($container['restartCount'] ?? 0);
                if (($terminated['reason'] ?? null) === 'OOMKilled') {
                    $degraded[] = "OOMKilled {$ago} ({$podName})";
                } else if ($restarts >= self::RestartsThatCount) {
                    $degraded[] = "Restarted {$restarts} times, last {$ago} ({$podName})";
                } else {
                    $notes[] = "Restarted {$ago} ({$podName})";
                }
            }
        }

        return [$degraded, $notes];
    }

    /**
     * The last migration job, if it was for the version the deployment is on now. A migration
     * that failed for an earlier version was fixed by the deploy that came after it.
     */
    private static function Migration(Workload $workload): ?string {
        $migration = $workload->lastMigration;
        if ($migration === null || $workload->version === '' || !str_ends_with($migration['image'], ":{$workload->version}")) {
            return null;
        }

        return match ($migration['status']) {
            \MigrationJobStatusTypes::Failed_LogVerification,
            \MigrationJobStatusTypes::Failed_PostCommands => \HealthStatusTypes::Degraded,
            \MigrationJobStatusTypes::Deploying,
            \MigrationJobStatusTypes::Started => \HealthStatusTypes::Progressing,
            default => null,
        };
    }

    private static function Condition(array $status, string $type): ?array {
        foreach ($status['conditions'] ?? [] as $condition) {
            if (($condition['type'] ?? null) === $type) {
                return $condition;
            }
        }
        return null;
    }

    private static function Described(array $condition, string $fallback): string {
        $reason = $condition['reason'] ?? '';
        $message = $condition['message'] ?? '';
        if ($reason !== '' && $message !== '') {
            return "{$reason}: {$message}";
        }
        return $reason !== '' ? $reason : ($message !== '' ? $message : $fallback);
    }

    /** A time the cluster wrote, or now when there is none - which makes it count as just now. */
    private static function Time(?string $timestamp, int $now): int {
        $time = $timestamp ? strtotime($timestamp) : false;
        return $time === false ? $now : $time;
    }

    private static function Ago(int $seconds): string {
        $minutes = intdiv(max(0, $seconds), 60);
        return $minutes < 1 ? 'just now' : "{$minutes} min ago";
    }

    /** How long it has been that way, rather than when it started. */
    private static function For(int $seconds): string {
        $minutes = intdiv(max(0, $seconds), 60);
        if ($minutes < 90) {
            return "for {$minutes} minutes";
        }
        $hours = intdiv($minutes, 60);
        return $hours < 48 ? "for {$hours} hours" : 'for ' . intdiv($hours, 24) . ' days';
    }

}
