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

    public function testAllActiveMakesTheWorkspaceActive(): void {
        $workspace = $this->workspaceWith([
            \DeploymentStatusTypes::Active,
            \DeploymentStatusTypes::Active,
        ]);

        $workspace->checkStatus();

        $this->assertSame(\WorkspaceStatusTypes::Active, $workspace->status);
    }

    public function testOneErrorMakesTheWorkspaceError(): void {
        $workspace = $this->workspaceWith([
            \DeploymentStatusTypes::Active,
            \DeploymentStatusTypes::Error,
        ]);

        $workspace->checkStatus();

        $this->assertSame(\WorkspaceStatusTypes::Error, $workspace->status);
    }

    public function testErrorWinsOverDeploying(): void {
        $workspace = $this->workspaceWith([
            \DeploymentStatusTypes::Deploying,
            \DeploymentStatusTypes::Error,
        ]);

        $workspace->checkStatus();

        $this->assertSame(\WorkspaceStatusTypes::Error, $workspace->status);
    }

    public function testOneDeployingMakesTheWorkspaceDeploying(): void {
        $workspace = $this->workspaceWith([
            \DeploymentStatusTypes::Active,
            \DeploymentStatusTypes::Deploying,
        ]);

        $workspace->checkStatus();

        $this->assertSame(\WorkspaceStatusTypes::Deploying, $workspace->status);
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
     * Documents PAUSE-2, case 1, as it behaves today.
     *
     * A terminated workspace with no deployments left loses its status: the branch that
     * preserves Inactive requires anyDraft, which nothing sets when there are no
     * deployments to look at. The workspace reappears in the default list on its own.
     *
     * Change this assertion when pause becomes something the system is told rather than
     * something it infers.
     */
    public function testInactiveWithoutDeploymentsFallsBackToDraft(): void {
        $workspace = $this->workspaceWith([], \WorkspaceStatusTypes::Inactive);

        $workspace->checkStatus();

        $this->assertSame(\WorkspaceStatusTypes::Draft, $workspace->status);
    }

    /**
     * Documents PAUSE-2, case 2, as it behaves today.
     *
     * Deploying a single deployment inside a paused workspace pulls the whole workspace
     * out of Inactive, and it does not find its way back.
     */
    public function testDeployingOneDeploymentTakesAPausedWorkspaceOutOfInactive(): void {
        $workspace = $this->workspaceWith(
            [\DeploymentStatusTypes::Inactive, \DeploymentStatusTypes::Deploying],
            \WorkspaceStatusTypes::Inactive
        );

        $workspace->checkStatus();

        $this->assertSame(\WorkspaceStatusTypes::Deploying, $workspace->status);
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
