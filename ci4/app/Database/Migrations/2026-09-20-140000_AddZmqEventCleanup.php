<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * `zmq_events` had no cleanup at all. Every push event wrote a row, and the only code that
 * deleted one was a deduplication branch that could not be reached - see the note on
 * `Controllers\ZMQ` - so the table held every event since the installation was created.
 *
 * Nightly rather than by the minute: it is housekeeping, and a month-old row is not urgent.
 *
 * The row is written with its id rather than through `CronJob::find()` and `save()`, which
 * is how the earlier jobs were added. That pattern only ever worked because their ids
 * happened to be the next ones the table would hand out; a job added later gets whatever
 * `AUTO_INCREMENT` is at, and then `\CronJobIds::CleanupZmqEvents` names no row at all and
 * the command cannot find its own bookkeeping.
 */
class AddZmqEventCleanup extends Migration {

    public function up() {
        $db = Database::connect();

        $alreadyThere = $db->table('cron_jobs')
            ->where('id', \CronJobIds::CleanupZmqEvents)
            ->countAllResults() > 0;
        if ($alreadyThere) {
            return;
        }

        $db->table('cron_jobs')->insert([
            'id' => \CronJobIds::CleanupZmqEvents,
            'name' => 'app:cleanup-zmq-events',
            'schedule' => '30 3 * * *',
            'command' => 'app:cleanup-zmq-events',
            'duplicates' => 1,
        ]);
    }

    public function down() {

    }

}
