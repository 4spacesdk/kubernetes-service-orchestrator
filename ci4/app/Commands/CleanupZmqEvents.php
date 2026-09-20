<?php namespace App\Commands;

use App\Entities\CronJob;
use App\Models\ZMQEventModel;
use CodeIgniter\CLI\BaseCommand;
use DebugTool\Data;

/**
 * Keep `zmq_events` to a window rather than to everything that ever happened.
 *
 * Every push event writes a row there - a deploy, a status change, an approved update -
 * and until this job existed nothing ever removed one. The only code that deleted a row
 * was a deduplication branch that could not be reached; see the note on `Controllers\ZMQ`.
 *
 * The rows are a log: nothing in kso reads them, and they are there for whoever is working
 * out why an event did or did not arrive. A month is long enough to cover an incident
 * somebody gets to on the following Monday, and short enough that the table stays small on
 * an installation that deploys all day.
 */
class CleanupZmqEvents extends BaseCommand {

    public $group = 'app';
    public $name = 'app:cleanup-zmq-events';
    public $description = 'Remove zmq events older than the retention window';
    protected $arguments = [

    ];
    protected $options = [

    ];

    /** How long a row is kept, in days. */
    public const int RetentionDays = 30;

    public function run(array $params) {
        Data::debug(get_class($this), 'CleanupZmqEvents');

        $job = new CronJob();
        $job->find(\CronJobIds::CleanupZmqEvents);
        $job->last_run = date('Y-m-d H:i:s');
        $job->save();

        $removed = $this->removeEventsOlderThanTheWindow();
        Data::debug('removed', $removed, 'events older than', self::RetentionDays, 'days');

        $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
        $job->save();
    }

    /**
     * Deleted straight through the builder rather than row by row through the ORM: on an
     * installation that has been running since before this job existed, the first run has
     * every event ever raised to get through, and loading them into entities to delete them
     * one at a time is how a cleanup job becomes the thing that needs cleaning up after.
     *
     * @return int how many rows went
     */
    protected function removeEventsOlderThanTheWindow(): int {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . self::RetentionDays . ' days'));

        $model = new ZMQEventModel();
        $model->builder()->where('created <', $cutoff)->delete();

        return $model->db->affectedRows();
    }

}
