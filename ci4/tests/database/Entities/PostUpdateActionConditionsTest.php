<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\PostUpdateAction;
use App\Entities\PostUpdateActionCondition;
use App\Fixtures;
use App\Models\PostUpdateActionConditionModel;
use App\Tests\Fakes\FakeIntegrations;

/**
 * The gate in front of a post-update action.
 *
 * After a deployment is updated, each configured action asks its conditions whether it
 * should run. All of them have to agree, and the answer decides whether a comment is
 * written back to a customer's Podio item - so a gate that says yes too easily is an
 * action firing on the wrong release.
 *
 * With the version control and commit lookups faked, the condition's own refusals are
 * covered too - everything up to the point where it asks Podio for a field value. Podio is
 * reached through `\PodioClient` directly rather than through an abstraction, so that last
 * step still has no seam; see TEST-3 track C.
 *
 * `perform()` is in the same position: it calls Podio from its first lines.
 */
class PostUpdateActionConditionsTest extends DatabaseTestCase {

    public function tearDown(): void {
        FakeIntegrations::uninstall();

        parent::tearDown();
    }

    /**
     * An action with nothing to check runs. That is the common case - most actions are
     * configured without conditions - so it is worth being explicit that "no conditions"
     * means "always", not "never".
     */
    public function testAnActionWithoutConditionsAlwaysRuns(): void {
        $action = Fixtures::postUpdateAction();
        $deployment = Fixtures::deployableDeployment();

        $this->assertTrue($action->checkConditions($deployment));
    }

    /**
     * Every condition has to pass. A type the switch does not handle falls through to
     * `return false`, so an unknown condition blocks the action rather than being ignored -
     * which is the safe direction for something that writes to a customer's system.
     */
    public function testAnUnrecognisedConditionBlocksTheAction(): void {
        $action = Fixtures::postUpdateAction();
        Fixtures::postUpdateActionCondition([
            'post_update_action_id' => $action->id,
            'type' => 'a-type-that-does-not-exist',
        ]);
        $deployment = Fixtures::deployableDeployment();

        $this->assertFalse($action->checkConditions($deployment));
    }

    /**
     * One failing condition is enough, and it short-circuits - the conditions after it are
     * never asked. That matters because asking costs a call to the version control
     * provider.
     */
    public function testOneFailingConditionIsEnoughToBlock(): void {
        $action = Fixtures::postUpdateAction();
        foreach (['a-type-that-does-not-exist', 'another-unknown-type'] as $type) {
            Fixtures::postUpdateActionCondition([
                'post_update_action_id' => $action->id,
                'type' => $type,
            ]);
        }
        $deployment = Fixtures::deployableDeployment();

        $this->assertFalse($action->checkConditions($deployment));
    }

    /**
     * Conditions belong to one action. Another action's conditions must not gate this one -
     * they are found by a filter, and nothing else keeps them apart.
     */
    public function testAnotherActionsConditionsAreNotConsulted(): void {
        $mine = Fixtures::postUpdateAction(['name' => 'mine']);
        $other = Fixtures::postUpdateAction(['name' => 'other']);
        Fixtures::postUpdateActionCondition([
            'post_update_action_id' => $other->id,
            'type' => 'a-type-that-does-not-exist',
        ]);
        $deployment = Fixtures::deployableDeployment();

        $this->assertTrue($mine->checkConditions($deployment));
        $this->assertFalse($other->checkConditions($deployment));
    }

    /**
     * The same wholesale replacement the specification endpoints use: what arrives becomes
     * the whole set, and an empty set removes every condition - which turns a gated action
     * into one that always runs.
     */
    public function testUpdatingConditionsReplacesTheWholeSet(): void {
        $action = Fixtures::postUpdateAction();
        Fixtures::postUpdateActionCondition([
            'post_update_action_id' => $action->id,
            'value' => 'first',
        ]);
        Fixtures::postUpdateActionCondition([
            'post_update_action_id' => $action->id,
            'value' => 'second',
        ]);
        $this->assertCount(2, $this->conditionsOf($action));

        // Saved first, then linked - the same order the controller uses. `save($values)`
        // attaches existing rows; it does not write new ones.
        $only = Fixtures::postUpdateActionCondition(['value' => 'only-this-one']);

        $replacement = new PostUpdateActionCondition();
        $replacement->all = [$only];
        $action->updateConditions($replacement);

        $remaining = $this->conditionsOf($action);
        $this->assertCount(1, $remaining);
        $this->assertSame('only-this-one', $remaining[0]->value);
    }

    public function testAnEmptySetRemovesEveryConditionAndOpensTheGate(): void {
        $action = Fixtures::postUpdateAction();
        Fixtures::postUpdateActionCondition([
            'post_update_action_id' => $action->id,
            'type' => 'a-type-that-does-not-exist',
        ]);
        $deployment = Fixtures::deployableDeployment();
        $this->assertFalse($action->checkConditions($deployment));

        $action->updateConditions(new PostUpdateActionCondition());

        $this->assertCount(0, $this->conditionsOf($action));
        $this->assertTrue($action->checkConditions($deployment));
    }

    // <editor-fold desc="The condition's own refusals">

    /**
     * An image with no commit identification configured crashes the check.
     *
     * `getCommitIdentification()` returns null for an image that has none - which is the
     * default, and what every image starts as - and the result is used without asking.
     * The condition is careful about everything after this point and not about this. The
     * failure lands in the post-update run after a deployment, so a customer's release
     * finishes and the follow-up work dies. See FEAT-10.
     */
    public function testAnImageWithoutCommitIdentificationCrashesTheCheck(): void {
        $fakes = FakeIntegrations::install();
        $fakes->shortSha = null;
        $fakes->commitMessage = 'Fixes https://podio.com/x/items/42';

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('getCommitShortSha() on null');

        $this->checkPodioCondition();
    }

    /**
     * The same one line later: a sha, but no version control configured to ask about it.
     * See FEAT-10.
     */
    public function testAnImageWithoutVersionControlCrashesTheCheck(): void {
        $fakes = FakeIntegrations::install();
        $fakes->shortSha = 'abc1234';
        $fakes->commitMessage = null;

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('getCommitMessage() on null');

        $this->checkPodioCondition();
    }

    /**
     * The condition finds the task it should look at by pulling a url out of the commit
     * message. No url, nothing to look at.
     */
    public function testACommitMessageWithoutAUrlSaysNo(): void {
        $fakes = FakeIntegrations::install();
        $fakes->shortSha = 'abc1234';
        $fakes->commitMessage = 'Tidy up the logging';

        $this->assertFalse($this->checkPodioCondition());
    }

    /**
     * And two urls is as bad as none: it refuses rather than guessing which task the
     * commit belongs to.
     */
    public function testACommitMessageWithTwoUrlsSaysNo(): void {
        $fakes = FakeIntegrations::install();
        $fakes->shortSha = 'abc1234';
        $fakes->commitMessage = 'Fixes https://podio.com/x/items/42 and https://podio.com/x/items/43';

        $this->assertFalse($this->checkPodioCondition());
    }

    // </editor-fold>

    private function checkPodioCondition(): bool {
        $deployment = Fixtures::deployableDeployment();
        $condition = Fixtures::postUpdateActionCondition([
            'type' => \PostUpdateActionConditionTypes::PodioFieldEquals,
            'value' => 'ready',
        ]);

        return $condition->check($deployment);
    }

    /**
     * @return PostUpdateActionCondition[]
     */
    private function conditionsOf(PostUpdateAction $action): array {
        return (new PostUpdateActionConditionModel())
            ->where('post_update_action_id', $action->id)
            ->orderBy('id', 'asc')
            ->find()
            ->all ?? [];
    }

}
