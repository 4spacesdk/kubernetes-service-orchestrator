<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

/**
 * A migration job is read from the cluster rather than reported by its pod - see
 * `MigrationJobWatcher`. `exit_code` is the migration's own, null until it has ended.
 *
 * The cron row is the safety net: `app:watch-migration-job` without an id looks once a minute
 * at every job that has not ended, for the one whose watcher went away with its kso pod.
 */
class AddMigrationJobExitCode extends Migration {

    public function up() {
        Table::init('migration_jobs')
            ->column('exit_code', ColumnTypes::INT_NULL);

        $db = Database::connect();
        $alreadyThere = $db->table('cron_jobs')
            ->where('id', \CronJobIds::WatchMigrationJobs)
            ->countAllResults() > 0;
        if (!$alreadyThere) {
            $db->table('cron_jobs')->insert([
                'id' => \CronJobIds::WatchMigrationJobs,
                'name' => 'app:watch-migration-job',
                'schedule' => '* * * * *',
                'command' => 'app:watch-migration-job',
                'duplicates' => 1,
            ]);
        }
    }

    public function down() {

    }

}
