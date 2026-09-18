<?php namespace App\Tests\Database\PostUpdateActions;

use App\DatabaseTestCase;
use App\Entities\Deployment;
use App\Entities\DeploymentSpecificationPostUpdateAction;
use App\Fixtures;
use App\Libraries\PostUpdateActions\PostUpdateActionHelper;
use App\Tests\Fakes\FakeIntegrations;

/**
 * Which post-update actions run after an auto update, in which order, and which are skipped.
 *
 * `PostUpdateActionPerformTest` covers what one action writes to Podio. This is the layer
 * above: the helper is what `AutoUpdate::rollout()` hands the deployment to, and it decides
 * the set. Three things can go wrong here and none of them shows up as an error - an action
 * from another specification running, the order being whatever the table felt like, or an
 * action whose conditions fail running anyway and commenting on a task it should not have
 * touched.
 */
class PostUpdateActionHelperTest extends DatabaseTestCase {

    public function tearDown(): void {
        FakeIntegrations::uninstall();

        parent::tearDown();
    }

    /**
     * The ordinary case, and the one that says the helper runs anything at all.
     */
    public function testAnActionOnTheSpecificationIsPerformed(): void {
        $fakes = $this->fakesWithCommit();
        $deployment = $this->deploymentWithActions(['Deployed to production']);

        (new PostUpdateActionHelper($deployment))->performAll();

        $this->assertCount(1, $fakes->podio()->comments);
        $this->assertSame('Deployed to production', $fakes->podio()->comments[0]['comment']);
    }

    /**
     * `position` is what the specification's editor drags actions around by, and it is the
     * only thing that decides the order - the rows are written here in the reverse of it on
     * purpose, so a query that forgot to sort would come out backwards.
     */
    public function testActionsRunInThePositionTheSpecificationGivesThem(): void {
        $fakes = $this->fakesWithCommit();
        $deployment = $this->deploymentWithActions(['third', 'second', 'first'], [3, 2, 1]);

        (new PostUpdateActionHelper($deployment))->performAll();

        $this->assertSame(
            ['first', 'second', 'third'],
            array_column($fakes->podio()->comments, 'comment')
        );
    }

    /**
     * An action whose conditions do not hold is dropped before anything is performed. The
     * condition reads a field out of the customer's Podio, so getting this wrong means
     * commenting on a task that was explicitly marked as not ready.
     */
    public function testAnActionWhoseConditionsFailIsNotPerformed(): void {
        $fakes = $this->fakesWithCommit();
        $deployment = Fixtures::deployableDeployment();

        $runs = $this->commentAction('runs');
        $blocked = $this->commentAction('blocked');
        $this->attach($deployment->deployment_specification_id, $runs->id, 1);
        $this->attach($deployment->deployment_specification_id, $blocked->id, 2);

        $reference = Fixtures::podioFieldReference([
            'podio_integration_id' => Fixtures::podioIntegration()->id,
            'field_id' => '99',
        ]);
        Fixtures::postUpdateActionCondition([
            'post_update_action_id' => $blocked->id,
            'podio_field_reference_id' => $reference->id,
            'value' => 'ready',
        ]);
        $fakes->podio()->fieldValues['99'] = 'not-ready';

        (new PostUpdateActionHelper($deployment))->performAll();

        $this->assertSame(['runs'], array_column($fakes->podio()->comments, 'comment'));
    }

    /**
     * Actions belong to a specification, not to the installation. A second specification's
     * actions must not follow a deployment that has nothing to do with them.
     */
    public function testAnActionOnAnotherSpecificationIsLeftAlone(): void {
        $fakes = $this->fakesWithCommit();
        $deployment = $this->deploymentWithActions(['ours']);

        $other = Fixtures::deploymentSpecification(['name' => 'other-spec']);
        $this->attach($other->id, $this->commentAction('theirs')->id, 1);

        (new PostUpdateActionHelper($deployment))->performAll();

        $this->assertSame(['ours'], array_column($fakes->podio()->comments, 'comment'));
    }

    /**
     * A specification with nothing attached is the normal state, and the helper runs for
     * every auto update regardless - so doing nothing has to be a quiet no-op rather than
     * an iteration over null.
     */
    public function testASpecificationWithNoActionsDoesNothing(): void {
        $fakes = $this->fakesWithCommit();
        $deployment = $this->deploymentWithActions([]);

        (new PostUpdateActionHelper($deployment))->performAll();

        $this->assertSame([], $fakes->podio()->comments);
    }

    /**
     * `${workspace.name}` resolves for a deployment handed over straight from the database,
     * which is how `AutoUpdate::rollout()` gets hold of one - it arrives with no relations
     * loaded, and an unloaded workspace substitutes an empty string into the comment.
     *
     * The load the constructor does for this is **redundant**: `PostUpdateAction::perform()`
     * repeats it line for line on its own first lines for the comment action, and the field
     * update action never looks at the workspace at all. Removing it from the constructor,
     * or inverting the `exists()` guard so it never runs, changes no answer anywhere - both
     * mutations survive this test and every other one here, and they are equivalent rather
     * than a gap. See the report.
     */
    public function testTheWorkspaceNameResolvesForADeploymentStraightFromTheDatabase(): void {
        $fakes = $this->fakesWithCommit();
        $deployment = $this->deploymentWithActions(['Released to ${workspace.name}']);

        $fresh = new Deployment();
        $fresh->find($deployment->id);
        $this->assertFalse($fresh->workspace->exists(), 'a row from the database carries no relations');

        (new PostUpdateActionHelper($fresh))->performAll();

        $deployment->workspace->find();
        $this->assertSame(
            'Released to ' . $deployment->workspace->namespace,
            $fakes->podio()->comments[0]['comment']
        );
        $this->assertNotSame('Released to ', $fakes->podio()->comments[0]['comment']);
    }

    // <editor-fold desc="Fixtures">

    private function fakesWithCommit(): FakeIntegrations {
        $fakes = FakeIntegrations::install();
        $fakes->shortSha = 'abc1234';
        $fakes->commitMessage = 'Fixes https://podio.com/acme/app/1/items/4217';

        return $fakes;
    }

    /**
     * A deployment whose specification has one comment action per text given.
     *
     * @param array<int, string> $comments
     * @param array<int, int> $positions written in the order given, so a missing sort shows
     */
    private function deploymentWithActions(array $comments, array $positions = []): Deployment {
        $deployment = Fixtures::deployableDeployment();

        foreach ($comments as $index => $comment) {
            $this->attach(
                $deployment->deployment_specification_id,
                $this->commentAction($comment)->id,
                $positions[$index] ?? $index + 1
            );
        }

        return $deployment;
    }

    private function commentAction(string $comment): \App\Entities\PostUpdateAction {
        return Fixtures::postUpdateAction([
            'type' => \PostUpdateActionTypes::Podio_AddComment,
            'podio_add_comment_integration_id' => Fixtures::podioIntegration()->id,
            'podio_add_comment_value' => $comment,
        ]);
    }

    private function attach(int $specificationId, int $actionId, int $position): void {
        $row = new DeploymentSpecificationPostUpdateAction();
        $row->deployment_specification_id = $specificationId;
        $row->post_update_action_id = $actionId;
        $row->position = $position;
        $row->save();
    }

    // </editor-fold>

}
