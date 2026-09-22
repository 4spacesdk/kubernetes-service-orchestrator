<?php namespace App\Tests\Database\Entities;

use App\Entities\Workspace;
use App\Fixtures;
use App\DatabaseTestCase;

/**
 * Workspace::checkStatus() derives a workspace status from the statuses of its deployments.
 *
 * It reads the deployments from the database rather than taking them as an argument, so
 * these are database tests rather than plain unit tests.
 */
class WorkspaceCheckStatusTest extends DatabaseTestCase {

    public function testAllSyncedMakesTheWorkspaceSynced(): void {
        $workspace = $this->workspaceWith([
            \DeploymentStatusTypes::Synced,
            \DeploymentStatusTypes::Synced,
        ]);

        $workspace->checkStatus();

        $this->assertSame(\WorkspaceStatusTypes::Synced, $workspace->status);
    }

    /**
     * There is no Error any more: nothing ever set it on a deployment, so a
     * workspace was only ever Error by hand. One deployment out of sync is the whole
     * workspace out of sync.
     */
    public function testOneOutOfSyncMakesTheWorkspaceOutOfSync(): void {
        $workspace = $this->workspaceWith([
            \DeploymentStatusTypes::Synced,
            \DeploymentStatusTypes::OutOfSync,
        ]);

        $workspace->checkStatus();

        $this->assertSame(\WorkspaceStatusTypes::OutOfSync, $workspace->status);
    }

    /**
     * A terminated workspace keeps its status while its deployments are inactive. This is
     * the only path that holds a pause today.
     */
    public function testInactiveStaysInactiveWhileItsDeploymentsAreInactive(): void {
        $workspace = $this->workspaceWith(
            [\DeploymentStatusTypes::Inactive, \DeploymentStatusTypes::Inactive],
            \WorkspaceStatusTypes::Inactive
        );

        $workspace->checkStatus();

        $this->assertSame(\WorkspaceStatusTypes::Inactive, $workspace->status);
    }

    /**
     * A workspace that was switched off stays switched off when there is nothing left to
     * derive a status from.
     *
     * It used to lose it: the branch that preserves Inactive asked for `anyDraft`, which
     * nothing sets when there are no deployments to look at, so every other branch fell
     * through to the Draft this starts from. A terminated workspace therefore reappeared in
     * the default list on its own - and `terminate()` itself ends by calling this, so the
     * status the endpoint wrote was never the one that stuck.
     */
    public function testInactiveWithoutDeploymentsStaysInactive(): void {
        $workspace = $this->workspaceWith([], \WorkspaceStatusTypes::Inactive);

        $workspace->checkStatus();

        $this->assertSame(\WorkspaceStatusTypes::Inactive, $workspace->status);
    }

    /**
     * And the other side of that line: a workspace nobody has deployed yet has no
     * deployments either, and it is Draft rather than switched off. The old status is what
     * tells the two apart.
     */
    public function testDraftWithoutDeploymentsStaysDraft(): void {
        $workspace = $this->workspaceWith([], \WorkspaceStatusTypes::Draft);

        $workspace->checkStatus();

        $this->assertSame(\WorkspaceStatusTypes::Draft, $workspace->status);
    }

    /**
     * Deploying a single deployment inside a switched-off workspace pulls the whole
     * workspace out of Inactive, and it does not find its way back.
     *
     * That is what the remembered pause is for: `is_paused` is a decision, and
     * `checkStatus()` returns before any of this when it is set. Without the flag, a status
     * derived from the deployments can always be moved by one of them.
     */
    public function testDeployingOneDeploymentTakesAPausedWorkspaceOutOfInactive(): void {
        $workspace = $this->workspaceWith(
            [\DeploymentStatusTypes::Inactive, \DeploymentStatusTypes::OutOfSync],
            \WorkspaceStatusTypes::Inactive
        );

        $workspace->checkStatus();

        $this->assertSame(\WorkspaceStatusTypes::OutOfSync, $workspace->status);
    }

    /**
     * @param string[] $deploymentStatuses
     */
    private function workspaceWith(array $deploymentStatuses, string $status = \WorkspaceStatusTypes::Draft): Workspace {
        $workspace = Fixtures::workspace(['status' => $status]);

        foreach ($deploymentStatuses as $index => $deploymentStatus) {
            Fixtures::deployment([
                'workspace_id' => $workspace->id,
                'name' => "deployment-{$index}",
                'status' => $deploymentStatus,
            ]);
        }

        return $workspace;
    }


}
