<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * The WAMP router and its subscriber are gone: pushes go to Centrifugo, and the events kso acts
 * on itself go through a job queue in this database. See `Libraries\Push\EventHandlers`.
 *
 * The queue's tables are codeigniter4/queue's own, created here rather than by the package's
 * migration, which `spark migrate` does not run - only the App namespace's. `payload` is a
 * MEDIUMTEXT instead of the package's TEXT: a job carries the event's data, and a workspace
 * with its deployments passes 64 KB.
 *
 * `zmq_events` was a log of what the subscriber received, and its nightly cleanup goes with it.
 */
class ReplacePushStack extends Migration {

    public function up() {
        $this->forge->addField([
            'id' => ['type' => 'bigint', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'queue' => ['type' => 'varchar', 'constraint' => 64, 'null' => false],
            'payload' => ['type' => 'mediumtext', 'null' => false],
            'priority' => ['type' => 'varchar', 'constraint' => 64, 'null' => false, 'default' => 'default'],
            'status' => ['type' => 'tinyint', 'unsigned' => true, 'null' => false, 'default' => 0],
            'attempts' => ['type' => 'tinyint', 'unsigned' => true, 'null' => false, 'default' => 0],
            'available_at' => ['type' => 'int', 'unsigned' => true, 'null' => false],
            'created_at' => ['type' => 'int', 'unsigned' => true, 'null' => false],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['queue', 'priority', 'status', 'available_at']);
        $this->forge->createTable('queue_jobs', true);

        $this->forge->addField([
            'id' => ['type' => 'bigint', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'connection' => ['type' => 'varchar', 'constraint' => 64, 'null' => false],
            'queue' => ['type' => 'varchar', 'constraint' => 64, 'null' => false],
            'payload' => ['type' => 'mediumtext', 'null' => false],
            'priority' => ['type' => 'varchar', 'constraint' => 64, 'null' => false, 'default' => 'default'],
            'exception' => ['type' => 'text', 'null' => false],
            'failed_at' => ['type' => 'int', 'unsigned' => true, 'null' => false],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['queue', 'priority']);
        $this->forge->createTable('queue_jobs_failed', true);

        $db = Database::connect();
        $db->table('cron_jobs')->where('id', \CronJobIds::CleanupZmqEvents)->delete();
        $this->forge->dropTable('zmq_events', true);
    }

    public function down() {

    }

}
