<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

/**
 * Every attempt at the sign-in form and the second-factor code, kept for 90 days.
 *
 * It is both the audit log and the count behind refusing a username after too many
 * failures: the count is read from here rather than kept beside it, so the two cannot
 * disagree, and it survives a restart and is shared by every pod.
 *
 * `username` is as typed. Somebody who types their password into that field leaves it
 * here in plain text - the usual price of an audit log that says who was tried.
 */
class AddSignInAttempts extends Migration {

    public function up() {
        Table::init('sign_in_attempts')
            ->create()
            ->column('username', ColumnTypes::VARCHAR_255)
            ->column('user_id', ColumnTypes::INT_NULL)
            ->column('step', ColumnTypes::VARCHAR_27)
            ->column('succeeded', ColumnTypes::BOOL_0)
            ->column('refused', ColumnTypes::BOOL_0)
            ->column('ip_address', 'VARCHAR(45)')
            ->column('user_agent', ColumnTypes::VARCHAR_511)
            ->column('created', ColumnTypes::DATETIME)
            ->addIndex('username_step_created', 'username', 'step', 'created')
            ->addIndex('created');

        $db = Database::connect();
        $alreadyThere = $db->table('cron_jobs')
            ->where('id', \CronJobIds::CleanupSignInAttempts)
            ->countAllResults() > 0;
        if (!$alreadyThere) {
            $db->table('cron_jobs')->insert([
                'id' => \CronJobIds::CleanupSignInAttempts,
                'name' => 'app:cleanup-sign-in-attempts',
                'schedule' => '40 3 * * *',
                'command' => 'app:cleanup-sign-in-attempts',
                'duplicates' => 1,
            ]);
        }
    }

    public function down() {

    }

}
