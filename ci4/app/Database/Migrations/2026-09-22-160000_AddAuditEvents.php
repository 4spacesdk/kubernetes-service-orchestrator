<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

/**
 * The audit trail: who changed what, and how - see `App\Libraries\Audit\Audit`. Kept for 90
 * days by `app:cleanup-audit-events`.
 */
class AddAuditEvents extends Migration {

    public function up() {
        Table::init('audit_events')
            ->create('id', 'BIGINT')
            ->column('created', ColumnTypes::DATETIME)
            ->column('user_id', ColumnTypes::INT_NULL)
            ->column('client_id', 'VARCHAR(80)')
            ->column('ip_address', 'VARCHAR(45)')
            ->column('source', ColumnTypes::VARCHAR_27)
            ->column('action', ColumnTypes::VARCHAR_63)
            ->column('resource_type', 'VARCHAR(63)')
            ->column('resource_id', ColumnTypes::INT_NULL)
            ->column('resource_name', 'VARCHAR(255)')
            ->column('details', 'MEDIUMTEXT')
            ->addIndex('resource_type_resource_id', 'resource_type', 'resource_id')
            ->addIndex('user_id')
            ->addIndex('created');

        $db = Database::connect();
        $alreadyThere = $db->table('cron_jobs')
            ->where('id', \CronJobIds::CleanupAuditEvents)
            ->countAllResults() > 0;
        if (!$alreadyThere) {
            $db->table('cron_jobs')->insert([
                'id' => \CronJobIds::CleanupAuditEvents,
                'name' => 'app:cleanup-audit-events',
                'schedule' => '50 3 * * *',
                'command' => 'app:cleanup-audit-events',
                'duplicates' => 1,
            ]);
        }
    }

    public function down() {

    }

}
