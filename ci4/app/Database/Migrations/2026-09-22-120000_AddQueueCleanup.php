<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * The job queue left two things behind that nothing took care of - see `CleanupQueue`.
 *
 * `changed_at` is written by MySQL on every change to a row, so for a reserved job it is when it
 * was reserved. codeigniter4/queue keeps no such time: `available_at` is when a job could first be
 * taken, which for a job that waited in a backlog is long before a worker took it.
 */
class AddQueueCleanup extends Migration {

    public function up() {
        $db = Database::connect();

        if (!$db->fieldExists('changed_at', 'queue_jobs')) {
            $db->query('ALTER TABLE queue_jobs ADD changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        }

        $alreadyThere = $db->table('cron_jobs')
            ->where('id', \CronJobIds::CleanupQueue)
            ->countAllResults() > 0;
        if (!$alreadyThere) {
            $db->table('cron_jobs')->insert([
                'id' => \CronJobIds::CleanupQueue,
                'name' => 'app:cleanup-queue',
                'schedule' => '15 * * * *',
                'command' => 'app:cleanup-queue',
                'duplicates' => 1,
            ]);
        }
    }

    public function down() {

    }

}
