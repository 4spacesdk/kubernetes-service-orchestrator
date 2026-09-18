<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\PostUpdateAction;
use App\Fixtures;
use App\Models\PodioFieldReferenceModel;
use App\Models\PostUpdateActionConditionModel;

/**
 * `PUT /post-update-actions/{id}/conditions`, the one endpoint on this controller.
 *
 * A post update action runs after a deployment is updated - it writes a comment back to
 * Podio, or sets a field on the task the commit mentioned. Its conditions decide whether
 * it runs at all, so an action whose conditions were saved wrong either stops firing or
 * fires on every deployment. Both are silent.
 *
 * Like the other list endpoints, **the list that arrives replaces the list that is
 * there**: the dialog sends the whole set every time, nothing is matched by id, and an
 * empty list means delete them all.
 *
 * The part that is not boilerplate is the Podio field reference. A condition points at
 * one field of one Podio integration, and that pointer is a row of its own rather than
 * two columns - shared between every condition and action that names the same field. The
 * controller therefore does not create one; it asks `PodioFieldReference::Create()` for
 * the existing one and only writes when there is none.
 */
class PostUpdateActionsApiTest extends ControllerTestCase {

    public function testAConditionArrivesWithItsTypeAndValueAndIsStoredAgainstTheAction(): void {
        $action = Fixtures::postUpdateAction();

        $this->putValues($action, [
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'ready'),
        ]);

        $conditions = $this->conditions($action);
        $this->assertCount(1, $conditions);
        $this->assertSame(\PostUpdateActionConditionTypes::PodioFieldEquals, $conditions[0]->type);
        $this->assertSame('ready', $conditions[0]->value);
    }

    /**
     * The field reference is written on the way in, so a condition can name a Podio field
     * the installation has never referred to before. Without this the dialog would have to
     * create the reference in a call of its own and hand back an id.
     */
    public function testNamingAPodioFieldForTheFirstTimeCreatesTheReferenceItPointsAt(): void {
        $action = Fixtures::postUpdateAction();
        $integration = Fixtures::podioIntegration();

        $this->putValues($action, [
            $this->condition(
                \PostUpdateActionConditionTypes::PodioFieldEquals,
                'ready',
                ['podio_integration_id' => $integration->id, 'field_id' => 'status-42']
            ),
        ]);

        $reference = $this->conditions($action)[0]->podio_field_reference->find();
        $this->assertSame('status-42', $reference->field_id);
        $this->assertSame((int) $integration->id, (int) $reference->podio_integration_id);
    }

    /**
     * **The reference is looked up, not created blindly.** Two conditions on the same field
     * share one row, and so does an action saved twice.
     *
     * A reference that was created per save would multiply on every save of the dialog, and
     * because `PodioFieldReference::Create()` returns the *first* match, later saves would
     * start pointing at rows nothing else uses - leaving conditions attached to a reference
     * the Podio integrations page no longer lists.
     */
    public function testTwoConditionsOnTheSamePodioFieldShareOneReference(): void {
        $action = Fixtures::postUpdateAction();
        $integration = Fixtures::podioIntegration();
        $field = ['podio_integration_id' => $integration->id, 'field_id' => 'status-42'];

        $this->putValues($action, [
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'ready', $field),
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'released', $field),
        ]);
        $both = $this->conditions($action);
        $this->assertSame(
            (int) $both[0]->podio_field_reference_id,
            (int) $both[1]->podio_field_reference_id
        );

        // Saved again, the way the dialog does on every edit.
        $this->putValues($action, [
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'ready', $field),
        ]);

        $this->assertSame(1, $this->referencesTo($integration->id, 'status-42'));
    }

    /**
     * A different field on the same integration is a different reference. The lookup keys
     * on both columns, and one that keyed on the integration alone would quietly point
     * every condition in an installation at whichever field was named first.
     */
    public function testADifferentFieldOnTheSameIntegrationGetsItsOwnReference(): void {
        $action = Fixtures::postUpdateAction();
        $integration = Fixtures::podioIntegration();

        $this->putValues($action, [
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'ready', [
                'podio_integration_id' => $integration->id, 'field_id' => 'status-42',
            ]),
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'yes', [
                'podio_integration_id' => $integration->id, 'field_id' => 'approved-7',
            ]),
        ]);

        $this->assertSame(1, $this->referencesTo($integration->id, 'status-42'));
        $this->assertSame(1, $this->referencesTo($integration->id, 'approved-7'));

        $conditions = $this->conditions($action);
        $this->assertNotSame(
            (int) $conditions[0]->podio_field_reference_id,
            (int) $conditions[1]->podio_field_reference_id
        );
    }

    /**
     * A condition type that is not about a Podio field sends no reference at all, and must
     * not get one. The dialog sends the key as null rather than leaving it out.
     */
    public function testAConditionWithoutAPodioFieldIsStoredWithoutAReference(): void {
        $action = Fixtures::postUpdateAction();

        $this->putValues($action, [
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'ready'),
        ]);

        $this->assertNull($this->conditions($action)[0]->podio_field_reference_id);
        $this->assertSame(0, (new PodioFieldReferenceModel())->find()->count());
    }

    public function testASecondCallReplacesTheConditionsRatherThanAddingToThem(): void {
        $action = Fixtures::postUpdateAction();

        $this->putValues($action, [
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'ready'),
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'obsolete'),
        ]);
        $this->putValues($action, [
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'released'),
        ]);

        $conditions = $this->conditions($action);
        $this->assertCount(1, $conditions);
        $this->assertSame('released', $conditions[0]->value);
    }

    /**
     * The end of the same rule, and the one that changes what the product does: an action
     * with no conditions runs on **every** deployment. Sending an empty list therefore has
     * to be taken literally, and it is answered with OK.
     */
    public function testAnEmptyListRemovesEveryConditionAndTheActionThenRunsUnconditionally(): void {
        $action = Fixtures::postUpdateAction();
        $this->putValues($action, [
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'ready'),
        ]);

        $body = $this->putValues($action, []);

        $this->assertSame('OK', $body['status']);
        $this->assertCount(0, $this->conditions($action));
    }

    /**
     * The delete that precedes the write is scoped to one action, so saving one action's
     * conditions must not touch another's. Both actions are usually attached to the same
     * deployment specification, and an action that lost its conditions starts firing on
     * every deployment.
     */
    public function testSavingOneActionLeavesAnotherActionsConditionsAlone(): void {
        $mine = Fixtures::postUpdateAction(['name' => 'comment on podio']);
        $other = Fixtures::postUpdateAction(['name' => 'set field on podio']);
        $this->putValues($other, [
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'keep-me'),
        ]);

        $this->putValues($mine, [
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'ready'),
        ]);

        $conditions = $this->conditions($other);
        $this->assertCount(1, $conditions);
        $this->assertSame('keep-me', $conditions[0]->value);
    }

    /**
     * An unknown id is answered with OK and nothing is written - the same shape as the
     * specification endpoints, and the same objection: a dialog saving against an action
     * somebody else deleted is told it worked. See FEAT-9.
     *
     * The rows are the half that would not be noticed. The controller saves each condition
     * and each new field reference before the action is asked to take them, so a guard one
     * line further down would leave both behind, attached to nothing.
     */
    public function testAnUnknownActionReportsSuccessAndWritesNeitherConditionNorReference(): void {
        $integration = Fixtures::podioIntegration();

        $body = $this->putTo('post-update-actions/999999/conditions', [
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'orphan', [
                'podio_integration_id' => $integration->id, 'field_id' => 'status-42',
            ]),
        ]);

        $this->assertSame('OK', $body['status']);
        $this->assertSame(
            0,
            (new PostUpdateActionConditionModel())->where('value', 'orphan')->find()->count()
        );
        $this->assertSame(0, $this->referencesTo($integration->id, 'status-42'));
    }

    /**
     * The endpoint answers with the action it just saved, which is what the dialog redraws
     * from.
     */
    public function testTheAnswerCarriesTheActionThatWasSaved(): void {
        $action = Fixtures::postUpdateAction(['name' => 'comment on podio']);

        $body = $this->putValues($action, [
            $this->condition(\PostUpdateActionConditionTypes::PodioFieldEquals, 'ready'),
        ]);

        $this->assertSame((int) $action->id, (int) $body['resource']['id']);
        $this->assertSame('comment on podio', $body['resource']['name']);
    }

    // <editor-fold desc="Helpers">

    /**
     * One condition as the dialog sends it. `podio_field_reference` is always present, null
     * when the condition does not name a field.
     *
     * @param array<string, mixed>|null $fieldReference
     * @return array<string, mixed>
     */
    private function condition(string $type, string $value, ?array $fieldReference = null): array {
        return [
            'type' => $type,
            'value' => $value,
            'podio_field_reference' => $fieldReference,
        ];
    }

    /**
     * @param array<array<string, mixed>> $values
     * @return array<string, mixed> the decoded response
     */
    private function putValues(PostUpdateAction $action, array $values): array {
        return $this->putTo("post-update-actions/{$action->id}/conditions", $values);
    }

    /**
     * @param array<array<string, mixed>> $values
     * @return array<string, mixed> the decoded response
     */
    private function putTo(string $path, array $values): array {
        $response = $this->withBodyFormat('json')->signedIn()->put($path, ['values' => $values]);

        return json_decode((string) $response->response()->getBody(), true);
    }

    /**
     * @return \App\Entities\PostUpdateActionCondition[]
     */
    private function conditions(PostUpdateAction $action): array {
        return (new PostUpdateActionConditionModel())
            ->where('post_update_action_id', $action->id)
            ->orderBy('id', 'asc')
            ->find()
            ->all ?? [];
    }

    /**
     * How many reference rows point at one field of one integration.
     */
    private function referencesTo(int $integrationId, string $fieldId): int {
        return (new PodioFieldReferenceModel())
            ->where('podio_integration_id', $integrationId)
            ->where('field_id', $fieldId)
            ->find()
            ->count();
    }

    // </editor-fold>

}
