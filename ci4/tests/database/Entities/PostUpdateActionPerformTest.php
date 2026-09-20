<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\Deployment;
use App\Entities\PostUpdateAction;
use App\Fixtures;
use App\Tests\Fakes\FakeIntegrations;

/**
 * What a post-update action actually writes back to a customer's Podio.
 *
 * This runs after a deployment is updated and is the one place kso reaches into a system
 * it does not own. Until Podio had an interface there was no way to look at it at all -
 * the client was built inside the method - so the whole of `perform()` was untested, and
 * with it the comment text a customer sees and which field gets set on their task.
 */
class PostUpdateActionPerformTest extends DatabaseTestCase {

    public function tearDown(): void {
        FakeIntegrations::uninstall();

        parent::tearDown();
    }

    // <editor-fold desc="Adding a comment">

    public function testACommentIsAddedToTheItemTheCommitMentions(): void {
        $fakes = $this->fakesWithCommit('Fixes https://podio.com/acme/app/1/items/4217');
        [$action, $deployment] = $this->commentAction('Deployed');

        $action->perform($deployment);

        $this->assertCount(1, $fakes->podio()->comments);
        $this->assertSame('4217', $fakes->podio()->comments[0]['itemId']);
    }

    /**
     * The comment text takes the same `${...}` placeholders the rest of the system uses,
     * though only two of them: the workspace and the commit url.
     */
    public function testTheCommentTextHasItsPlaceholdersFilledIn(): void {
        $fakes = $this->fakesWithCommit('Fixes https://podio.com/acme/app/1/items/4217');
        $fakes->commitUrl = 'https://github.com/team/app/commit/abc1234';
        [$action, $deployment] = $this->commentAction('Released to ${workspace.name} from ${commit.url}');

        // The two names differ on purpose: `${workspace.name}` resolves to the namespace,
        // not the readable name, and a fixture where they match would not say so.
        $deployment->workspace->find();
        $deployment->workspace->name_readable = 'Acme Industries';
        $deployment->workspace->namespace = 'acme-namespace';
        $deployment->workspace->save();

        $action->perform($deployment);

        $this->assertSame(
            'Released to acme-namespace from https://github.com/team/app/commit/abc1234',
            $fakes->podio()->comments[0]['comment']
        );
    }

    public function testAnUnknownPlaceholderIsLeftAsWritten(): void {
        $fakes = $this->fakesWithCommit('Fixes https://podio.com/acme/app/1/items/4217');
        [$action, $deployment] = $this->commentAction('Deployed ${deployment.name}');

        $action->perform($deployment);

        $this->assertSame('Deployed ${deployment.name}', $fakes->podio()->comments[0]['comment']);
    }

    /**
     * An image with no commit identification or version control set up - the default - used
     * to take the action down on a call to null, after the release had already finished.
     * The action is skipped instead.
     */
    public function testAnImageWithoutTheIntegrationsSkipsTheComment(): void {
        $fakes = $this->fakesWithCommit('Fixes https://podio.com/acme/app/1/items/4217');
        $fakes->shortSha = null;
        [$action, $deployment] = $this->commentAction('Deployed');

        $action->perform($deployment);

        $this->assertSame([], $fakes->podio()->comments);
    }

    public function testAnImageWithoutVersionControlSkipsTheFieldUpdate(): void {
        $fakes = $this->fakesWithCommit('Fixes https://podio.com/acme/app/1/items/4217');
        $fakes->commitMessage = null;
        [$action, $deployment] = $this->fieldUpdateAction('done');

        $action->perform($deployment);

        $this->assertSame([], $fakes->podio()->fieldUpdates);
    }

    /**
     * Today's behaviour, and separate from the guards above: a commit message with no task
     * url leaves the item id empty, and the id is pulled apart before anything is guarded.
     * The action dies rather than skipping quietly.
     */
    public function testACommitWithoutATaskUrlCrashes(): void {
        $this->fakesWithCommit('Tidy up the logging');
        [$action, $deployment] = $this->commentAction('Deployed');

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('Undefined array key 1');

        $action->perform($deployment);
    }

    // </editor-fold>

    // <editor-fold desc="Updating a field">

    public function testAFieldIsUpdatedOnTheItemTheCommitMentions(): void {
        $fakes = $this->fakesWithCommit('Fixes https://podio.com/acme/app/1/items/4217');
        [$action, $deployment] = $this->fieldUpdateAction('done');

        $action->perform($deployment);

        $update = $fakes->podio()->fieldUpdates[0];
        $this->assertSame('4217', $update['itemId']);
        $this->assertSame('99', $update['fieldId'], 'the field the reference names');
        $this->assertSame('done', $update['value']);
    }

    /**
     * A category field is set by option id, and Podio wants that as a number. A numeric
     * value is therefore sent as an integer and anything else as text - the one piece of
     * translation in this method, and invisible until it is wrong.
     */
    public function testANumericValueIsSentAsANumberAndTextAsText(): void {
        $fakes = $this->fakesWithCommit('Fixes https://podio.com/acme/app/1/items/4217');
        [$numeric, $deployment] = $this->fieldUpdateAction('7');
        $numeric->perform($deployment);

        [$text, $otherDeployment] = $this->fieldUpdateAction('done');
        $text->perform($otherDeployment);

        $this->assertSame(7, $fakes->podio()->fieldUpdates[0]['value']);
        $this->assertSame('done', $fakes->podio()->fieldUpdates[1]['value']);
    }

    // </editor-fold>

    /**
     * The last branch of the condition, which needed Podio to be reachable at all. The
     * field value is compared loosely, so the stored string matches the option id.
     */
    public function testAConditionComparesTheFieldValueItReadsBack(): void {
        $fakes = $this->fakesWithCommit('Fixes https://podio.com/acme/app/1/items/4217');
        $integration = Fixtures::podioIntegration();
        $reference = Fixtures::podioFieldReference(['podio_integration_id' => $integration->id, 'field_id' => '99']);
        $deployment = Fixtures::deployableDeployment();

        $matching = Fixtures::postUpdateActionCondition([
            'podio_field_reference_id' => $reference->id,
            'value' => 'ready',
        ]);
        $fakes->podio()->fieldValues['99'] = 'ready';
        $this->assertTrue($matching->check($deployment));

        $fakes->podio()->fieldValues['99'] = 'not-ready';
        $this->assertFalse($matching->check($deployment));
    }

    // <editor-fold desc="Fixtures">

    private function fakesWithCommit(string $message): FakeIntegrations {
        $fakes = FakeIntegrations::install();
        $fakes->shortSha = 'abc1234';
        $fakes->commitMessage = $message;

        return $fakes;
    }

    /**
     * @return array{0: PostUpdateAction, 1: Deployment}
     */
    private function commentAction(string $comment): array {
        $integration = Fixtures::podioIntegration();
        $action = Fixtures::postUpdateAction([
            'type' => \PostUpdateActionTypes::Podio_AddComment,
            'podio_add_comment_integration_id' => $integration->id,
            'podio_add_comment_value' => $comment,
        ]);

        return [$action, Fixtures::deployableDeployment()];
    }

    /**
     * @return array{0: PostUpdateAction, 1: Deployment}
     */
    private function fieldUpdateAction(string $value): array {
        $integration = Fixtures::podioIntegration();
        $reference = Fixtures::podioFieldReference([
            'podio_integration_id' => $integration->id,
            'field_id' => '99',
        ]);
        $action = Fixtures::postUpdateAction([
            'type' => \PostUpdateActionTypes::Podio_FieldUpdate,
            'podio_field_update_field_reference_id' => $reference->id,
            'podio_field_update_value' => $value,
        ]);

        return [$action, Fixtures::deployableDeployment()];
    }

    // </editor-fold>

}
