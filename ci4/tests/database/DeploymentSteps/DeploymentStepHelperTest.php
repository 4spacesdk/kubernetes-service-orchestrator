<?php namespace App\Tests\Database\DeploymentSteps;

use App\DatabaseTestCase;
use App\Fixtures;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;

/**
 * The two entry points every change to a workspace goes through on its way to the cluster.
 *
 * `EmitTrigger()` is what an endpoint calls after it has saved something: it asks the
 * specification which steps care about that particular change and runs their deploy
 * command. `ExecuteWorkspaceDeployCommand()` does the same for every deployment in a
 * workspace at once, which is what a change to the workspace itself means.
 *
 * Both stop at the first error and hand it back as a string. Nothing else reports it, so a
 * return value that goes missing is a deploy that silently did not happen - which is the
 * part checked here. The half that reaches the cluster is in `DeploymentStepsTest`.
 */
class DeploymentStepHelperTest extends DatabaseTestCase {

    /**
     * A draft deployment is one whose steps do not validate yet, and `EmitTrigger()` says
     * so rather than applying half of it.
     */
    public function testADraftDeploymentIsNotDeployed(): void {
        $deployment = Fixtures::deployableDeployment(['status' => \DeploymentStatusTypes::Draft]);

        $this->assertSame(
            'Deployment still in draft mode',
            DeploymentStepHelper::EmitTrigger(
                DeploymentStepTriggers::Deployment_Version_Updated,
                $deployment
            )
        );
    }

    /**
     * A workspace deploy runs every deployment in it, and the first one that refuses ends
     * the run - the caller gets that one error and the deployments after it are left alone.
     * Stopping is deliberate: the steps run in an order where a later one assumes the
     * earlier one worked.
     */
    public function testAWorkspaceDeployStopsAtTheFirstDeploymentThatRefuses(): void {
        $workspace = Fixtures::workspace();
        $specification = Fixtures::deploymentSpecification([
            'container_image_id' => Fixtures::containerImage()->id,
        ]);
        foreach (['first', 'second'] as $name) {
            Fixtures::deployment([
                'name' => $name,
                'workspace_id' => $workspace->id,
                'deployment_specification_id' => $specification->id,
                'status' => \DeploymentStatusTypes::Draft,
            ]);
        }

        $error = DeploymentStepHelper::ExecuteWorkspaceDeployCommand($workspace, [
            DeploymentStepTriggers::Deployment_Version_Updated,
            DeploymentStepTriggers::Deployment_Environment_Updated,
        ]);

        $this->assertSame('Deployment still in draft mode', $error);
    }

    /**
     * An empty workspace is not an error. Workspace level changes are emitted whether or
     * not anything has been created inside it yet.
     */
    public function testAWorkspaceWithNoDeploymentsIsNotAnError(): void {
        $workspace = Fixtures::workspace();

        $this->assertNull(DeploymentStepHelper::ExecuteWorkspaceDeployCommand(
            $workspace,
            [DeploymentStepTriggers::Deployment_Version_Updated]
        ));
    }

    /**
     * The identifier is what a stored row and an api request carry, so this map is the only
     * thing that turns one back into the step that knows what to do with it. An identifier
     * that maps to nothing has to come back as nothing rather than as some other step.
     */
    public function testAnUnknownIdentifierMapsToNoStep(): void {
        $this->assertNull(DeploymentStepHelper::GetStep('no-such-step'));
    }

}
