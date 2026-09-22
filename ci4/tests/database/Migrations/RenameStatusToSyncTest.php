<?php namespace App\Tests\Database\Migrations;

use App\Database\Migrations\RenameStatusToSync;
use App\DatabaseTestCase;
use App\Fixtures;

/**
 * The rename of the stored statuses: Active is Synced, Deploying is Out of sync, and the Error
 * nothing ever set is Out of sync too. Draft, Inactive and Paused are left as they are.
 *
 * The rows are written with the old words directly - the constants for them are gone - and
 * the migration is run on them the way spark would.
 */
class RenameStatusToSyncTest extends DatabaseTestCase {

    public function testTheOldStatusesAreRenamedAndTheOthersLeftAlone(): void {
        $deployments = [];
        foreach (['active' => 'synced', 'deploying' => 'out_of_sync', 'error' => 'out_of_sync', 'draft' => 'draft', 'inactive' => 'inactive'] as $old => $new) {
            $deployments[$old] = [Fixtures::deployment(['name' => "was-{$old}", 'status' => $old])->id, $new];
        }
        $workspaces = [];
        foreach (['active' => 'synced', 'deploying' => 'out_of_sync', 'error' => 'out_of_sync', 'paused' => 'paused'] as $old => $new) {
            $workspaces[$old] = [Fixtures::workspace(['name_system' => "was-{$old}", 'status' => $old])->id, $new];
        }

        $this->runTheMigration();

        foreach ($deployments as $old => [$id, $new]) {
            $this->assertSame($new, $this->statusIn('deployments', $id), "a deployment that was {$old}");
        }
        foreach ($workspaces as $old => [$id, $new]) {
            $this->assertSame($new, $this->statusIn('workspaces', $id), "a workspace that was {$old}");
        }
    }

    /**
     * It runs again on an installation that has it already - a migration rerun by hand - and
     * finds nothing to do.
     */
    public function testRunningItTwiceChangesNothingTheSecondTime(): void {
        $id = Fixtures::deployment(['status' => 'active'])->id;

        $this->runTheMigration();
        $this->runTheMigration();

        $this->assertSame(\DeploymentStatusTypes::Synced, $this->statusIn('deployments', $id));
    }

    private function runTheMigration(): void {
        // Required by path: a migration's file name starts with its date, so it cannot be autoloaded.
        require_once APPPATH . 'Database/Migrations/2026-09-22-200000_RenameStatusToSync.php';
        (new RenameStatusToSync())->up();
    }

    private function statusIn(string $table, int|string $id): string {
        return (string) $this->db->table($table)->where('id', $id)->get()->getRow('status');
    }

}
