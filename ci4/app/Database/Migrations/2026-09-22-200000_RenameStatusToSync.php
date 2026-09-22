<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * The status says what it measures.
 *
 * `Deployment::checkStatus()` asks whether each of a deployment's resources is in the cluster,
 * and that is all. "Active" is now Synced, and "Deploying" - which a deployment also said, for
 * good, when a resource had been deleted with kubectl - is Out of sync. Whether the workload is
 * doing well is its health, beside it.
 *
 * Error goes. Nothing ever set it on a deployment, and a workspace was only Error when one of
 * its deployments was. A row that has it anyway - written by hand - becomes Out of sync, which
 * is what the next status check makes of anything that is not all there.
 *
 * The audit trail keeps the old words for the changes made under them.
 */
class RenameStatusToSync extends Migration {

    private const array Renamed = [
        'active' => 'synced',
        'deploying' => 'out_of_sync',
        'error' => 'out_of_sync',
    ];

    public function up() {
        $db = Database::connect();
        foreach (['deployments', 'workspaces'] as $table) {
            foreach (self::Renamed as $from => $to) {
                $db->table($table)->where('status', $from)->update(['status' => $to]);
            }
        }
    }

    public function down() {

    }

}
