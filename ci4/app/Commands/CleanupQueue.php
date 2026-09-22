<?php namespace App\Commands;

use App\Entities\CronJob;
use App\Libraries\Push\EventHandlers;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\Queue\Enums\Status;
use CodeIgniter\Queue\Models\QueueJobModel;
use Config\Database;
use DebugTool\Data;

/**
 * What the job queue leaves behind.
 *
 * **A job whose worker died.** A worker marks a job reserved when it takes it, and nothing
 * takes a reserved job again: when the pod goes while one runs, it stays reserved for ever and
 * never runs. One reserved for longer than any job takes is moved to the failed jobs, with the
 * reason, rather than put back: it may have got halfway - a rollout, a webhook that was sent -
 * and starting it again is a decision for a person (`spark queue:retry`).
 *
 * **Failed jobs**, which the queue keeps for a look and never removes. A month is enough to get
 * to one.
 */
class CleanupQueue extends BaseCommand {

    public $group = 'app';
    public $name = 'app:cleanup-queue';
    public $description = 'Fail jobs whose worker died, and remove old failed jobs';
    protected $arguments = [

    ];
    protected $options = [

    ];

    /** How long a job may be reserved before its worker is taken to be gone, in minutes. */
    public const int StaleAfterMinutes = 60;

    /** How long a failed job is kept, in days. */
    public const int RetentionDays = 30;

    public function run(array $params) {
        Data::debug(get_class($this), 'CleanupQueue');

        $job = new CronJob();
        $job->find(\CronJobIds::CleanupQueue);
        $job->last_run = date('Y-m-d H:i:s');
        $job->save();

        $failed = $this->failJobsWhoseWorkerDied();
        Data::debug('failed', $failed, 'jobs reserved for more than', self::StaleAfterMinutes, 'minutes');

        service('queue')->flush(self::RetentionDays * 24, EventHandlers::Queue);
        Data::debug('removed failed jobs older than', self::RetentionDays, 'days');

        $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
        $job->save();
    }

    /**
     * @return int how many
     */
    protected function failJobsWhoseWorkerDied(): int {
        $cutoff = Database::connect()->query('SELECT NOW() - INTERVAL ? MINUTE AS cutoff', [self::StaleAfterMinutes])->getRow()->cutoff;

        $stale = model(QueueJobModel::class)
            ->where('status', Status::RESERVED->value)
            ->where('changed_at <', $cutoff)
            ->findAll();

        $reason = new \RuntimeException(
            'The worker stopped while running this job - its pod went away - so it never finished. '
            . 'It was not started again by itself; `spark queue:retry` starts it again.'
        );
        foreach ($stale as $queueJob) {
            service('queue')->failed($queueJob, $reason, true);
        }

        return count($stale);
    }

}
