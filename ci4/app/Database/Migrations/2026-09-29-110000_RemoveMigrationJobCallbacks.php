<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * The two public routes a migration job's pod reported itself on with curl, and the token it
 * proved itself with. kso reads a job from the cluster now (`MigrationJobWatcher`), so nothing
 * calls them - and they were the only migration job routes open without a sign-in.
 *
 * A job started before the upgrade and still running would have no one to report to, and is
 * left in Started: rerun it.
 */
class RemoveMigrationJobCallbacks extends Migration {

    public function up() {
        $db = Database::connect();
        $db->table('api_routes')
            ->where('method', 'put')
            ->whereIn('to', [
                'App\Controllers\MigrationJobs::setStarted/$1',
                'App\Controllers\MigrationJobs::setEnded/$1',
            ])
            ->delete();

        if ($db->fieldExists('callback_token_hash', 'migration_jobs')) {
            $db->query('ALTER TABLE migration_jobs DROP COLUMN callback_token_hash');
        }
    }

    public function down() {

    }

}
