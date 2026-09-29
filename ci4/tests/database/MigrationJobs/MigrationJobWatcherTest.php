<?php namespace App\Tests\Database\MigrationJobs;

use App\DatabaseTestCase;
use App\Entities\CronJob;
use App\Entities\Deployment;
use App\Entities\DeploymentSpecificationPostCommand;
use App\Entities\MigrationJob;
use App\Fixtures;
use App\Libraries\MigrationJobs\MigrationJobCluster;
use App\Libraries\MigrationJobs\MigrationJobWatcher;
use Config\Database;

/**
 * Following a migration job in the cluster: its container's state, its exit code and its log,
 * read from the Job and the pod the way the api server hands them over.
 *
 * The pod used to report all of this itself with curl, so an image without curl never reported,
 * and the exit code was curl's. Every test here hands the watcher a cluster of its own and one
 * migration job, and reads what became of the job's row.
 */
class MigrationJobWatcherTest extends DatabaseTestCase {

    private FakeMigrationJobCluster $cluster;

    public function setUp(): void {
        parent::setUp();

        $this->cluster = new FakeMigrationJobCluster();
        MigrationJobWatcher::$cluster = $this->cluster;
    }

    public function testARunningContainerStartsTheJob(): void {
        $job = $this->aJob();
        $this->cluster->pods = [$this->pod($job, ['running' => ['startedAt' => '2026-09-29T08:00:00Z']])];

        $this->assertFalse(MigrationJobWatcher::Check($job->id), 'it has not ended');

        $job = $this->reread($job);
        $this->assertSame(\MigrationJobStatusTypes::Started, $job->status);
        $this->assertSame(date('Y-m-d H:i:s', strtotime('2026-09-29T08:00:00Z')), $job->started);
    }

    public function testAnExitOfZeroWithAVerifiedLogCompletesTheJob(): void {
        $job = $this->aJob(verifiedBy: 'Done.');
        $this->cluster->pods = [$this->pod($job, $this->terminated(0))];
        $this->cluster->log = "Migrating...\nDone.\n";

        $this->assertTrue(MigrationJobWatcher::Check($job->id));

        $job = $this->reread($job);
        $this->assertSame(\MigrationJobStatusTypes::Completed, $job->status);
        $this->assertSame(0, (int) $job->exit_code);
        $this->assertSame("Migrating...\nDone.", $job->log);
        $this->assertSame(date('Y-m-d H:i:s', strtotime('2026-09-29T08:05:00Z')), $job->ended);
    }

    public function testAnExitOfZeroWithALogThatIsNotVerifiedFailsTheJob(): void {
        $job = $this->aJob(verifiedBy: 'Done.');
        $this->cluster->pods = [$this->pod($job, $this->terminated(0))];
        $this->cluster->log = "Migrating...\nNothing to do";

        MigrationJobWatcher::Check($job->id);

        $this->assertSame(\MigrationJobStatusTypes::Failed_LogVerification, $this->reread($job)->status);
    }

    /**
     * A migration that says it failed has failed, whatever its log ends with - and the post
     * commands are for a migration that worked. Had they run, they would have reached for the
     * cluster, which a test does not have, and the job would say `failed-post-commands`.
     */
    public function testAnExitThatIsNotZeroFailsTheJobAndRunsNoPostCommand(): void {
        $job = $this->aJob(verifiedBy: 'Done.');
        $postCommand = new DeploymentSpecificationPostCommand();
        $postCommand->deployment_specification_id = $this->deploymentOf($job)->deployment_specification_id;
        $postCommand->name = 'clear cache';
        $postCommand->command = 'php spark cache:clear';
        $postCommand->save();
        $this->cluster->pods = [$this->pod($job, $this->terminated(1))];
        $this->cluster->log = "Migrating...\nDone.";

        MigrationJobWatcher::Check($job->id);

        $job = $this->reread($job);
        $this->assertSame(\MigrationJobStatusTypes::Failed_ExitCode, $job->status);
        $this->assertSame(1, (int) $job->exit_code);
        $this->assertStringEndsWith('The migration exited with 1', $job->log);
    }

    /**
     * The Job gave up before the migration's container ended - its deadline, a failing init
     * container - so there is no exit code to read. It must not be left in `started` for ever.
     */
    public function testAJobThatFailsWithoutAnEndedContainerFailsWithItsReason(): void {
        $job = $this->aJob();
        $this->cluster->job = $this->k8sJob($job, [[
            'type' => 'Failed',
            'status' => 'True',
            'reason' => 'DeadlineExceeded',
            'message' => 'Job was active longer than specified deadline',
        ]]);

        $this->assertTrue(MigrationJobWatcher::Check($job->id));

        $job = $this->reread($job);
        $this->assertSame(\MigrationJobStatusTypes::Failed, $job->status);
        $this->assertStringContainsString('DeadlineExceeded', $job->log);
        $this->assertNotEmpty($job->ended);
    }

    public function testAnInitContainerThatFailedIsNamedWithItsLog(): void {
        $job = $this->aJob();
        $this->cluster->job = $this->k8sJob($job, [['type' => 'Failed', 'status' => 'True', 'reason' => 'BackoffLimitExceeded']]);
        $pod = $this->pod($job, ['waiting' => ['reason' => 'PodInitializing']]);
        $pod['status']['initContainerStatuses'] = [['name' => 'wait-for-db', 'state' => ['terminated' => ['exitCode' => 2]]]];
        $this->cluster->pods = [$pod];
        $this->cluster->log = 'database did not answer';

        MigrationJobWatcher::Check($job->id);

        $log = $this->reread($job)->log;
        $this->assertStringContainsString('Init container wait-for-db exited with 2', $log);
        $this->assertStringContainsString('database did not answer', $log);
    }

    /**
     * An image that cannot be pulled leaves the pod waiting until the Job's deadline, six hours
     * on. kso gives up well before, and stops the Job - or the migration would run the day the
     * image turned up, with nobody watching.
     */
    public function testAnImageThatCannotBePulledStopsTheJobAfterAWhile(): void {
        $job = $this->aJob(createdSecondsAgo: MigrationJobWatcher::PullFailingFor + 60);
        $this->cluster->job = $this->k8sJob($job);
        $this->cluster->pods = [$this->pod($job, ['waiting' => ['reason' => 'ImagePullBackOff', 'message' => 'Back-off pulling image "app:9.9"']])];

        $this->assertTrue(MigrationJobWatcher::Check($job->id));

        $job = $this->reread($job);
        $this->assertSame(\MigrationJobStatusTypes::Failed, $job->status);
        $this->assertStringContainsString('ImagePullBackOff: Back-off pulling image "app:9.9"', $job->log);
        $this->assertSame(1, $this->cluster->deleted);
    }

    public function testAnImageStillBeingPulledIsWaitedFor(): void {
        $job = $this->aJob();
        $this->cluster->job = $this->k8sJob($job);
        $this->cluster->pods = [$this->pod($job, ['waiting' => ['reason' => 'ErrImagePull']])];

        $this->assertFalse(MigrationJobWatcher::Check($job->id));

        $this->assertSame(\MigrationJobStatusTypes::Deploying, $this->reread($job)->status);
        $this->assertSame(0, $this->cluster->deleted);
    }

    public function testAJobWhosePodIsGoneFailsAfterAWhile(): void {
        $gone = $this->aJob(createdSecondsAgo: MigrationJobWatcher::NoPodFor + 60);
        $justCreated = $this->aJob();

        $this->assertTrue(MigrationJobWatcher::Check($gone->id));
        $this->assertFalse(MigrationJobWatcher::Check($justCreated->id), 'its pod may not be there yet');

        $this->assertSame(\MigrationJobStatusTypes::Failed, $this->reread($gone)->status);
        $this->assertStringContainsString('gone', $this->reread($gone)->log);
        $this->assertSame(\MigrationJobStatusTypes::Deploying, $this->reread($justCreated)->status);
    }

    /**
     * A rerun replaces the Job, and both are named after the deployment. The earlier run's pod
     * says nothing about this one.
     */
    public function testThePodOfAnotherRunIsNotThisJobs(): void {
        $job = $this->aJob();
        $other = $this->aJob();
        $this->cluster->job = $this->k8sJob($other);
        $this->cluster->pods = [$this->pod($other, $this->terminated(0))];

        $this->assertFalse(MigrationJobWatcher::Check($job->id));

        $this->assertSame(\MigrationJobStatusTypes::Deploying, $this->reread($job)->status);
    }

    /**
     * The watcher started with the job and the cron job's run land on the same job. The second
     * look finds it ended and leaves it be - reading the log again, and above all running the
     * post commands again, is what the lock and the check under it are for.
     */
    public function testTwoLooksAtAnEndedJobSettleItOnce(): void {
        $job = $this->aJob(verifiedBy: 'Done.');
        $this->cluster->pods = [$this->pod($job, $this->terminated(0))];
        $this->cluster->log = 'Done.';

        $this->assertTrue(MigrationJobWatcher::Check($job->id));
        $this->assertTrue(MigrationJobWatcher::Check($job->id));

        $this->assertSame(1, $this->cluster->logsRead);
        $this->assertSame(\MigrationJobStatusTypes::Completed, $this->reread($job)->status);
    }

    public function testAJobAnotherProcessIsLookingAtIsLeftToIt(): void {
        $job = $this->aJob();
        $this->cluster->pods = [$this->pod($job, $this->terminated(0))];
        $other = Database::connect(null, false);
        $other->query('SELECT GET_LOCK(?, 0)', [MigrationJobWatcher::LockName($job->id)]);

        try {
            $this->assertFalse(MigrationJobWatcher::Check($job->id));
        } finally {
            $other->query('SELECT RELEASE_LOCK(?)', [MigrationJobWatcher::LockName($job->id)]);
            $other->close();
        }

        $this->assertSame(0, $this->cluster->logsRead);
        $this->assertSame(\MigrationJobStatusTypes::Deploying, $this->reread($job)->status);
    }

    public function testWatchingLooksUntilTheJobHasEnded(): void {
        $job = $this->aJob(verifiedBy: 'Done.');
        $this->cluster->pods = [$this->pod($job, ['running' => ['startedAt' => '2026-09-29T08:00:00Z']])];
        $this->cluster->log = 'Done.';
        $naps = 0;
        MigrationJobWatcher::$sleep = function (int $seconds) use (&$naps, $job): void {
            // It ends while the watcher waits.
            if (++$naps === 2) {
                $this->cluster->pods = [$this->pod($job, $this->terminated(0))];
            }
        };

        $this->assertTrue(MigrationJobWatcher::Watch($job->id));

        $this->assertSame(2, $naps);
        $this->assertSame(\MigrationJobStatusTypes::Completed, $this->reread($job)->status);
    }

    // <editor-fold desc="The safety net">

    /**
     * A watcher goes with its kso pod, and a release is when kso's pods are replaced. The cron
     * job looks at every job that has not ended.
     */
    public function testTheSafetyNetEndsAStartedJob(): void {
        $job = $this->aJob(verifiedBy: 'Done.');
        $job->updateStatus(\MigrationJobStatusTypes::Started);
        $this->cluster->pods = [$this->pod($job, $this->terminated(0))];
        $this->cluster->log = 'Done.';

        command('app:watch-migration-job');

        $this->assertSame(\MigrationJobStatusTypes::Completed, $this->reread($job)->status);
    }

    /**
     * Past its deadline a job cannot still be running - and further back are the jobs from
     * before kso watched them, which it would only mark failed now.
     */
    public function testTheSafetyNetLeavesJobsOlderThanTheirDeadline(): void {
        $old = $this->aJob(createdSecondsAgo: MigrationJobWatcher::SweepWithin + 60);
        $recent = $this->aJob();

        $this->assertSame(1, MigrationJobWatcher::Sweep());

        $this->assertSame(\MigrationJobStatusTypes::Deploying, $this->reread($old)->status);
        $this->assertSame(\MigrationJobStatusTypes::Deploying, $this->reread($recent)->status);
    }

    public function testTheSafetyNetRunsEveryMinute(): void {
        $cronJob = new CronJob();
        $cronJob->find(\CronJobIds::WatchMigrationJobs);

        $this->assertSame('app:watch-migration-job', $cronJob->command);
        $this->assertSame('* * * * *', $cronJob->schedule);
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    private function aJob(?string $verifiedBy = null, int $createdSecondsAgo = 0): MigrationJob {
        $deployment = Fixtures::deployableDeployment([], [
            'enable_database' => true,
            'database_migration_command' => 'php spark migrate',
            'database_migration_verification_type' => \MigrationVerificationTypes::EndsWith,
            'database_migration_verification_value' => $verifiedBy ?? 'Done.',
        ]);

        $job = new MigrationJob();
        $job->deployment_id = $deployment->id;
        $job->status = \MigrationJobStatusTypes::Deploying;
        $job->save();
        if ($createdSecondsAgo > 0) {
            $this->db->table('migration_jobs')
                ->where('id', $job->id)
                ->update(['created' => date('Y-m-d H:i:s', time() - $createdSecondsAgo)]);
        }

        return $this->reread($job);
    }

    private function reread(MigrationJob $job): MigrationJob {
        $fresh = new MigrationJob();
        $fresh->find($job->id);

        return $fresh;
    }

    private function deploymentOf(MigrationJob $job): Deployment {
        $deployment = new Deployment();
        $deployment->find($job->deployment_id);

        return $deployment;
    }

    /**
     * @return array<string, mixed>
     */
    private function terminated(int $exitCode): array {
        return ['terminated' => [
            'exitCode' => $exitCode,
            'reason' => $exitCode === 0 ? 'Completed' : 'Error',
            'startedAt' => '2026-09-29T08:00:00Z',
            'finishedAt' => '2026-09-29T08:05:00Z',
        ]];
    }

    /**
     * The migration's pod, running this job - `MIGRATION_JOB_ID` is how the watcher tells it.
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function pod(MigrationJob $job, array $state): array {
        $name = (string) $this->deploymentOf($job)->name;

        return [
            'metadata' => ['name' => "{$name}-abcde", 'creationTimestamp' => '2026-09-29T07:59:00Z'],
            'spec' => ['containers' => [['name' => $name, 'env' => [['name' => 'MIGRATION_JOB_ID', 'value' => (string) $job->id]]]]],
            'status' => ['containerStatuses' => [['name' => $name, 'state' => $state]]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $conditions
     * @return array<string, mixed>
     */
    private function k8sJob(MigrationJob $job, array $conditions = []): array {
        return [
            'spec' => ['template' => ['spec' => ['containers' => [['env' => [['name' => 'MIGRATION_JOB_ID', 'value' => (string) $job->id]]]]]]],
            'status' => ['conditions' => $conditions],
        ];
    }

    // </editor-fold>

}

/**
 * The cluster as the test sets it up: one Job, its pods, and one log for whatever is asked.
 */
class FakeMigrationJobCluster implements MigrationJobCluster {

    public ?array $job = null;
    /** @var list<array> */
    public array $pods = [];
    public string $log = '';
    public int $logsRead = 0;
    public int $deleted = 0;

    public function job(Deployment $deployment): ?array {
        return $this->job;
    }

    public function pods(Deployment $deployment): array {
        return $this->pods;
    }

    public function log(Deployment $deployment, string $pod, string $container): string {
        $this->logsRead++;

        return $this->log;
    }

    public function deleteJob(Deployment $deployment): void {
        $this->deleted++;
    }

}
