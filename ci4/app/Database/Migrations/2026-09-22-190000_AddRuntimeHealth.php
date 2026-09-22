<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

/**
 * Runtime health, beside the status - see `Libraries/Health`.
 *
 * All nullable: a deployment in Draft, or one whose workload kso cannot read the health of,
 * has none, and every row starts that way until the first run of `app:check-health` a minute
 * after the upgrade.
 *
 * * `health_severity` is what the lists sort by. The names sort alphabetically into nonsense.
 * * `health_changed_at` moves only when the health itself changes, not its reason - it is the
 *   "since when" the row shows and what the webhook waits on.
 * * `health_checked_at` moves on every run, so a stale one says the check has stopped.
 * * `health_notified` is the health the last webhook was sent for.
 */
class AddRuntimeHealth extends Migration {

    public function up() {
        Table::init('deployments')
            ->column('health', 'VARCHAR(27)')
            ->column('health_severity', ColumnTypes::INT_NULL)
            ->column('health_reason', ColumnTypes::VARCHAR_1023_NULL)
            ->column('health_changed_at', ColumnTypes::DATETIME)
            ->column('health_checked_at', ColumnTypes::DATETIME)
            ->column('health_notified', 'VARCHAR(27)')
            ->addIndex('health');

        Table::init('workspaces')
            ->column('health', 'VARCHAR(27)')
            ->column('health_severity', ColumnTypes::INT_NULL)
            ->column('health_reason', ColumnTypes::VARCHAR_1023_NULL)
            ->column('health_changed_at', ColumnTypes::DATETIME)
            ->addIndex('health');

        $db = Database::connect();
        $alreadyThere = $db->table('cron_jobs')
            ->where('id', \CronJobIds::CheckHealth)
            ->countAllResults() > 0;
        if (!$alreadyThere) {
            $db->table('cron_jobs')->insert([
                'id' => \CronJobIds::CheckHealth,
                'name' => 'app:check-health',
                'schedule' => '* * * * *',
                'command' => 'app:check-health',
                'duplicates' => 1,
            ]);
        }
    }

    public function down() {

    }

}
