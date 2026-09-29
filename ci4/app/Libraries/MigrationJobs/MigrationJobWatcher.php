<?php namespace App\Libraries\MigrationJobs;

use App\Entities\Deployment;
use App\Entities\MigrationJob;
use App\Libraries\Kubernetes\KubeHelper;
use App\Models\MigrationJobModel;
use Config\Database;
use DebugTool\Data;

/**
 * Follows a migration job in the cluster: when its container runs, when it ends, its exit code
 * and its log.
 *
 * The pod used to report all of that itself, with curl, to two endpoints of kso's that had to be
 * public for it - so an image without curl never reported at all, and the pod's exit code was
 * curl's rather than the migration's. kso reads pods and their logs for the health check and the
 * log view already, and nothing is asked of the image now.
 *
 * Started for each new job (`app:watch-migration-job <id>`, in the background), and looked at once
 * a minute by the cron job for any that has not ended - the watcher goes with its kso pod, and a
 * release is when kso's pods are replaced. Each look takes a lock of the job's own, and a job that
 * has ended is left alone, so the two never settle one job twice.
 */
class MigrationJobWatcher {

    /** Seconds between two looks while watching. */
    public const int Interval = 3;

    /** How long the watcher started with a job keeps at it. The cron job goes on after. */
    public const int WatchFor = 1800;

    /** How long a job may be without a pod of its own before it is taken to be gone. */
    public const int NoPodFor = 120;

    /** How long an image may fail to be pulled before kso gives up on it and stops the job. */
    public const int PullFailingFor = 600;

    /**
     * How far back the cron job looks. Kubernetes stops a migration after its
     * `activeDeadlineSeconds` (6 hours, `MigrationJobStep`), so an older job cannot still be
     * running - and further back are the jobs from before kso watched them, which it never
     * learned the end of.
     */
    public const int SweepWithin = 21600 + 3600;

    /** A waiting container that will not get further by itself. */
    private const array CannotStartReasons = [
        'ErrImagePull', 'ImagePullBackOff', 'InvalidImageName', 'ErrImageNeverPull',
        'CreateContainerConfigError', 'CreateContainerError',
    ];

    public static ?MigrationJobCluster $cluster = null;

    /**
     * How the watcher waits between looks. The test suite puts a recorder here.
     *
     * @var null|\Closure(int): void
     */
    public static ?\Closure $sleep = null;

    /**
     * Looks until the job has ended or `WatchFor` has passed.
     *
     * @return bool Whether it has ended
     */
    public static function Watch(int $migrationJobId): bool {
        $until = time() + self::WatchFor;
        while (true) {
            try {
                if (self::Check($migrationJobId)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // The cluster not answering once is not the migration failing. The next look,
                // or the cron job's, tries again.
                Data::debug("migration job {$migrationJobId}:", KubeHelper::PrintException($e));
            }
            if (time() >= $until) {
                return false;
            }
            (self::$sleep ?? sleep(...))(self::Interval);
        }
    }

    /**
     * One look at every job that has not ended - the cron job's run.
     *
     * @return int How many were looked at
     */
    public static function Sweep(): int {
        /** @var MigrationJob $jobs */
        $jobs = (new MigrationJobModel())
            ->whereIn('status', [\MigrationJobStatusTypes::Deploying, \MigrationJobStatusTypes::Started])
            ->where('created >=', date('Y-m-d H:i:s', time() - self::SweepWithin))
            ->find();

        $count = 0;
        foreach ($jobs as $job) {
            $count++;
            try {
                self::Check((int) $job->id);
            } catch (\Throwable $e) {
                Data::debug("migration job {$job->id}:", KubeHelper::PrintException($e));
            }
        }

        return $count;
    }

    /**
     * One look at one job, under its lock.
     *
     * @return bool Whether it has ended - false too while another process has the job
     */
    public static function Check(int $migrationJobId): bool {
        if (!self::TakeLock($migrationJobId)) {
            return false;
        }
        try {
            // Read under the lock, so a job the other process has just ended is seen as ended.
            $job = new MigrationJob();
            $job->find($migrationJobId);
            if (!$job->exists() || \MigrationJobStatusTypes::IsFinished($job->status)) {
                return true;
            }

            $deployment = new Deployment();
            $deployment->find($job->deployment_id);
            if (!$deployment->exists()) {
                self::Fail($job, 'Its deployment is gone');
                return true;
            }

            return self::Look($job, $deployment);
        } finally {
            self::ReleaseLock($migrationJobId);
        }
    }

    private static function Look(MigrationJob $job, Deployment $deployment): bool {
        $cluster = self::$cluster ?? new KubernetesMigrationJobCluster();
        $id = (int) $job->id;

        // The Job and pod are named after the deployment, so a rerun replaces them. Which run a
        // pod is of is in its environment.
        $k8sJob = $cluster->job($deployment);
        $isOurs = $k8sJob !== null && self::RunOf($k8sJob['spec']['template']['spec'] ?? []) === $id;
        $pod = self::NewestPodOf($id, $cluster->pods($deployment));

        $container = $pod === null ? [] : self::ContainerStatus($pod, (string) $deployment->name);
        if (isset($container['state']['terminated'])) {
            self::Ended($job, $deployment, $pod, $container['state']['terminated'], $cluster);
            return true;
        }
        if (isset($container['state']['running'])) {
            self::Started($job, $container['state']['running']['startedAt'] ?? null);
        }

        if ($isOurs && ($failed = self::FailedCondition($k8sJob)) !== null) {
            self::Fail($job, self::Explain($failed, $pod, $deployment, $cluster));
            return true;
        }

        $age = time() - (int) strtotime_((string) $job->created);

        $waiting = $pod === null ? null : self::CannotStart($pod);
        if ($waiting !== null && $age > self::PullFailingFor) {
            // Stopped, or the pod would run the migration the day the image turns up, with
            // nobody watching.
            if ($isOurs) {
                $cluster->deleteJob($deployment);
            }
            self::Fail($job, "It could not start: {$waiting}. The job was stopped");
            return true;
        }

        if ($pod === null && !$isOurs && $age > self::NoPodFor) {
            self::Fail($job, 'Its job is gone before it ended - deleted, or replaced by a newer run');
            return true;
        }

        return false;
    }

    private static function Started(MigrationJob $job, ?string $startedAt): void {
        if ($job->status !== \MigrationJobStatusTypes::Deploying) {
            return;
        }
        $job->started = self::LocalTime($startedAt);
        $job->save();
        $job->updateStatus(\MigrationJobStatusTypes::Started);
    }

    private static function Ended(MigrationJob $job, Deployment $deployment, array $pod, array $terminated, MigrationJobCluster $cluster): void {
        if (!$job->started) {
            $job->started = self::LocalTime($terminated['startedAt'] ?? null);
        }
        $job->ended = self::LocalTime($terminated['finishedAt'] ?? null);
        $job->exit_code = (int) ($terminated['exitCode'] ?? 0);

        try {
            $job->log = trim($cluster->log($deployment, (string) ($pod['metadata']['name'] ?? ''), (string) $deployment->name));
        } catch (\Throwable $e) {
            $job->log = 'The log could not be read: ' . KubeHelper::PrintException($e);
        }

        if ($job->exit_code !== 0) {
            $reason = (string) ($terminated['reason'] ?? '');
            $job->log = trim($job->log . "\nThe migration exited with {$job->exit_code}" . ($reason !== '' && $reason !== 'Error' ? " ({$reason})" : ''));
            $job->save();
            // Not verified: a migration that says it failed has, whatever its log ends with - and
            // the post commands are for a migration that worked.
            $job->updateStatus(\MigrationJobStatusTypes::Failed_ExitCode);
            return;
        }

        $job->save();
        $job->validateLog();
    }

    private static function Fail(MigrationJob $job, string $reason): void {
        $job->log = trim($job->log . "\n" . $reason);
        $job->ended = date('Y-m-d H:i:s');
        $job->save();
        $job->updateStatus(\MigrationJobStatusTypes::Failed);
    }

    /**
     * The Job's own reason for giving up - the deadline, or its pod failing before the migration
     * container ran.
     */
    private static function FailedCondition(array $k8sJob): ?array {
        foreach ($k8sJob['status']['conditions'] ?? [] as $condition) {
            if (($condition['type'] ?? '') === 'Failed' && ($condition['status'] ?? '') === 'True') {
                return $condition;
            }
        }
        return null;
    }

    private static function Explain(array $failed, ?array $pod, Deployment $deployment, MigrationJobCluster $cluster): string {
        $lines = [trim('The job failed: ' . ($failed['reason'] ?? '') . ' ' . ($failed['message'] ?? ''))];

        // An init container that failed is why the migration never ran, and its log is why.
        foreach ($pod['status']['initContainerStatuses'] ?? [] as $init) {
            $exitCode = $init['state']['terminated']['exitCode'] ?? null;
            if ($exitCode === null || (int) $exitCode === 0) {
                continue;
            }
            $lines[] = "Init container {$init['name']} exited with {$exitCode}";
            try {
                $lines[] = trim($cluster->log($deployment, (string) ($pod['metadata']['name'] ?? ''), (string) $init['name']));
            } catch (\Throwable) {
                // The reason above says enough without it.
            }
        }

        $waiting = $pod === null ? null : self::CannotStart($pod);
        if ($waiting !== null) {
            $lines[] = "It could not start: {$waiting}";
        }

        return implode("\n", array_filter($lines, fn(string $line) => $line !== ''));
    }

    /**
     * "ImagePullBackOff: …" when a container of the pod waits for something that will not come
     * by itself, null otherwise.
     */
    private static function CannotStart(array $pod): ?string {
        $statuses = array_merge($pod['status']['initContainerStatuses'] ?? [], $pod['status']['containerStatuses'] ?? []);
        foreach ($statuses as $status) {
            $reason = $status['state']['waiting']['reason'] ?? null;
            if (in_array($reason, self::CannotStartReasons, true)) {
                return trim("{$reason}: " . ($status['state']['waiting']['message'] ?? ''), ': ');
            }
        }
        return null;
    }

    private static function ContainerStatus(array $pod, string $name): array {
        $statuses = $pod['status']['containerStatuses'] ?? [];
        foreach ($statuses as $status) {
            if (($status['name'] ?? '') === $name) {
                return $status;
            }
        }
        return $statuses[0] ?? [];
    }

    /**
     * @param list<array> $pods
     */
    private static function NewestPodOf(int $migrationJobId, array $pods): ?array {
        $newest = null;
        foreach ($pods as $pod) {
            if (self::RunOf($pod['spec'] ?? []) !== $migrationJobId) {
                continue;
            }
            if ($newest === null || strcmp($pod['metadata']['creationTimestamp'] ?? '', $newest['metadata']['creationTimestamp'] ?? '') > 0) {
                $newest = $pod;
            }
        }
        return $newest;
    }

    /**
     * The migration job id a pod spec runs, from the `MIGRATION_JOB_ID` the step puts in it.
     */
    private static function RunOf(array $podSpec): ?int {
        foreach ($podSpec['containers'] ?? [] as $container) {
            foreach ($container['env'] ?? [] as $env) {
                if (($env['name'] ?? '') === 'MIGRATION_JOB_ID' && isset($env['value'])) {
                    return (int) $env['value'];
                }
            }
        }
        return null;
    }

    /**
     * The cluster's RFC 3339 in UTC, as kso stores a time.
     */
    private static function LocalTime(?string $time): string {
        return date('Y-m-d H:i:s', $time ? (int) strtotime_($time) : time());
    }

    public static function LockName(int $migrationJobId): string {
        return "kso-migration-job-{$migrationJobId}";
    }

    private static function TakeLock(int $migrationJobId): bool {
        $row = Database::connect()->query('SELECT GET_LOCK(?, 0) AS taken', [self::LockName($migrationJobId)])->getRow();
        return (int) ($row->taken ?? 0) === 1;
    }

    private static function ReleaseLock(int $migrationJobId): void {
        Database::connect()->query('SELECT RELEASE_LOCK(?)', [self::LockName($migrationJobId)]);
    }

}
