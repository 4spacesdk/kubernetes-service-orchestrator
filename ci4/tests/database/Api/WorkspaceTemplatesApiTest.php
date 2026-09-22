<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\WorkspaceTemplate;
use App\Fixtures;
use App\Models\WorkspaceTemplateDeploymentSpecificationModel;
use App\Models\EnvironmentVariableModel;

/**
 * The workspace template - the template a workspace is onboarded from.
 *
 * Three of its four endpoints follow the wholesale-replacement pattern the rest of the API
 * uses. The fourth does not: `copy-to-deployments` reaches past the template and writes an
 * environment variable into **every deployment in every workspace on it**. It is the only
 * endpoint in the system that edits many customers' running configuration in one call, so
 * most of this file is about who it touches and who it leaves alone.
 *
 * The fixtures leave deployments in Draft, so the step triggers those writes emit stop at
 * the door and nothing reaches a cluster. See DeploymentsApiTest for that guard.
 */
class WorkspaceTemplatesApiTest extends ControllerTestCase {

    // <editor-fold desc="Specifications in the template">

    /**
     * The wire format is camelCase and nests the whole specification, rather than sending
     * an id - the mapping to columns is hand-written in the entity.
     */
    public function testSpecificationsAreAddedWithTheirDefaults(): void {
        $template = Fixtures::workspaceTemplate();
        $specification = Fixtures::deploymentSpecification(['name' => 'api']);

        $this->putValues("workspace-templates/{$template->id}/deployment-specifications", [
            [
                'deploymentSpecification' => ['id' => $specification->id],
                'defaultVersion' => '3.1.4',
                'defaultReplicas' => 3,
                'defaultCpuLimit' => 500,
                'defaultAutoUpdateEnabled' => true,
                'defaultAutoUpdateTagRegex' => 'v[0-9]+',
            ],
        ]);

        $rows = $this->specificationsOf($template);

        $this->assertCount(1, $rows);
        $this->assertSame((int) $specification->id, (int) $rows[0]->deployment_specification_id);
        $this->assertSame('3.1.4', $rows[0]->default_version);
        $this->assertSame(3, (int) $rows[0]->default_replicas);
        $this->assertSame(500, (int) $rows[0]->default_cpu_limit);
        $this->assertSame('v[0-9]+', $rows[0]->default_auto_update_tag_regex);
    }

    /**
     * Defaults are optional, and a deployment made from the template then falls back to
     * whatever the specification says.
     *
     * The entity writes null for what was left out, but the columns carry the defaults the
     * AddColumnDefaults migration gave them - so it comes back as an empty string and a
     * zero, not as null. Worth knowing before writing `?? 'fallback'` against these.
     */
    public function testOmittedDefaultsComeBackAsTheColumnDefaults(): void {
        $template = Fixtures::workspaceTemplate();
        $specification = Fixtures::deploymentSpecification(['name' => 'api']);

        $this->putValues("workspace-templates/{$template->id}/deployment-specifications", [
            ['deploymentSpecification' => ['id' => $specification->id]],
        ]);

        $row = $this->specificationsOf($template)[0];

        $this->assertSame('', $row->default_version);
        $this->assertSame(0, (int) $row->default_replicas);
    }

    /**
     * The warm-pod flag is not sent - it is derived from whether any schedule ids came
     * with the request. Sending none turns it off.
     */
    public function testTheWarmPodFlagFollowsWhetherSchedulesWereSent(): void {
        $template = Fixtures::workspaceTemplate();
        $schedule = Fixtures::minScaleSchedule();
        $withSchedules = Fixtures::deploymentSpecification(['name' => 'with']);
        $withoutSchedules = Fixtures::deploymentSpecification(['name' => 'without']);

        $this->putValues("workspace-templates/{$template->id}/deployment-specifications", [
            [
                'deploymentSpecification' => ['id' => $withSchedules->id],
                'defaultKnativeScheduledMinScaleIds' => [$schedule->id],
            ],
            [
                'deploymentSpecification' => ['id' => $withoutSchedules->id],
                'defaultKnativeScheduledMinScaleIds' => [],
            ],
        ]);

        $rows = $this->specificationsOf($template);

        $this->assertTrue((bool) $rows[0]->default_knative_scheduled_minscale_is_enabled);
        $this->assertFalse((bool) $rows[1]->default_knative_scheduled_minscale_is_enabled);
    }

    public function testSendingTheListAgainReplacesIt(): void {
        $template = Fixtures::workspaceTemplate();
        $first = Fixtures::deploymentSpecification(['name' => 'first']);
        $second = Fixtures::deploymentSpecification(['name' => 'second']);

        $this->putValues("workspace-templates/{$template->id}/deployment-specifications", [
            ['deploymentSpecification' => ['id' => $first->id]],
            ['deploymentSpecification' => ['id' => $second->id]],
        ]);
        $this->assertCount(2, $this->specificationsOf($template));

        $this->putValues("workspace-templates/{$template->id}/deployment-specifications", [
            ['deploymentSpecification' => ['id' => $second->id]],
        ]);

        $rows = $this->specificationsOf($template);
        $this->assertCount(1, $rows);
        $this->assertSame((int) $second->id, (int) $rows[0]->deployment_specification_id);
    }

    /**
     * An empty list empties the template. A workspace onboarded afterwards gets no
     * deployments at all, and the call still answers OK.
     */
    public function testAnEmptyListEmptiesTheTemplate(): void {
        $template = Fixtures::workspaceTemplate();
        $specification = Fixtures::deploymentSpecification(['name' => 'api']);
        $this->putValues("workspace-templates/{$template->id}/deployment-specifications", [
            ['deploymentSpecification' => ['id' => $specification->id]],
        ]);

        $body = $this->putValues("workspace-templates/{$template->id}/deployment-specifications", []);

        $this->assertSame('OK', $body['status']);
        $this->assertCount(0, $this->specificationsOf($template));
    }

    // </editor-fold>

    // <editor-fold desc="Variables on the template itself">

    /**
     * The template's own variables, which a workspace onboarded from it starts with. Not to
     * be confused with `copy-to-deployments` below: this writes one row per variable on the
     * template and touches nothing that is already running.
     */
    public function testEnvironmentVariablesAreStoredOnTheTemplate(): void {
        $template = Fixtures::workspaceTemplate();

        $this->putValues("workspace-templates/{$template->id}/environment-variables", [
            ['name' => 'LOG_LEVEL', 'value' => 'debug'],
        ]);

        $row = $this->variablesOn($template->id)[0];
        $this->assertSame('LOG_LEVEL', $row['name']);
        $this->assertSame('debug', $row['value']);
    }

    /**
     * Wholesale replacement again - the list that arrives is the list there is.
     */
    public function testSendingTheVariableListAgainReplacesIt(): void {
        $template = Fixtures::workspaceTemplate();

        $this->putValues("workspace-templates/{$template->id}/environment-variables", [
            ['name' => 'KEPT', 'value' => 'one'],
            ['name' => 'DROPPED', 'value' => 'two'],
        ]);
        $this->putValues("workspace-templates/{$template->id}/environment-variables", [
            ['name' => 'KEPT', 'value' => 'three'],
        ]);

        $rows = $this->variablesOn($template->id);

        $this->assertCount(1, $rows);
        $this->assertSame('KEPT', $rows[0]['name']);
        $this->assertSame('three', $rows[0]['value']);
    }

    /**
     * **The two endpoints on this controller disagree about unknown ids.** This one answers
     * OK and writes nothing; `copy-to-deployments` and `labels` refuse with an error. A
     * client cannot tell from the status whether the template it named exists. Pinned rather
     * than fixed, like the other update endpoints that answer OK for an unknown id.
     */
    public function testAnUnknownTemplateIsRefusedByTheVariablesEndpoint(): void {
        $body = $this->putValues('workspace-templates/999999/environment-variables', [
            ['name' => 'LOG_LEVEL', 'value' => 'debug'],
        ]);

        $this->assertSame('unknown workspace template', $body['error'] ?? null);
        $this->assertCount(0, $this->variablesOn(999999));
    }

    // </editor-fold>

    // <editor-fold desc="Labels">

    /**
     * Labels are shared rows joined to the template through a junction table, so the set is
     * the junction - a second call replaces it whole.
     */
    public function testLabelsReplaceTheWholeSet(): void {
        $template = Fixtures::workspaceTemplate();

        $this->putValues("workspace-templates/{$template->id}/labels", [
            ['name' => 'team', 'value' => 'platform'],
            ['name' => 'tier', 'value' => 'backend'],
        ]);
        $this->assertCount(2, $this->labelsOn($template->id));

        $this->putValues("workspace-templates/{$template->id}/labels", [
            ['name' => 'team', 'value' => 'infra'],
        ]);

        $rows = $this->labelsOn($template->id);
        $this->assertCount(1, $rows);
        $this->assertSame('team', $rows[0]['name']);
        $this->assertSame('infra', $rows[0]['value']);
    }

    /**
     * Unlike the variables endpoint next door, this one refuses an unknown template.
     */
    public function testAnUnknownTemplateIsRefusedByTheLabelsEndpoint(): void {
        $body = $this->putValues('workspace-templates/999999/labels', [
            ['name' => 'team', 'value' => 'platform'],
        ]);

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('unknown workspace template', $body['error']);
        $this->assertCount(0, $this->labelsOn(999999));
    }

    // </editor-fold>

    // <editor-fold desc="Copying a variable out to every deployment">

    /**
     * The fan-out. One call, and every deployment in every workspace on this template gets
     * the variable.
     */
    public function testAVariableIsCopiedToEveryDeploymentOnTheTemplate(): void {
        $template = Fixtures::workspaceTemplate();
        $first = $this->deploymentOnTemplate($template->id, 'first');
        $second = $this->deploymentOnTemplate($template->id, 'second');

        $body = $this->copyVariable($template->id, 'SHARED', 'from-the-template');

        $this->assertSame('OK', $body['status']);
        $this->assertSame('from-the-template', $this->variableOf($first->id, 'SHARED'));
        $this->assertSame('from-the-template', $this->variableOf($second->id, 'SHARED'));
    }

    /**
     * And nobody else. A workspace on another template is a different customer's
     * configuration, and the join through the workspace is the only thing keeping it out.
     */
    public function testDeploymentsOnAnotherTemplateAreNotTouched(): void {
        $template = Fixtures::workspaceTemplate(['name' => 'ours']);
        $otherTemplate = Fixtures::workspaceTemplate(['name' => 'theirs']);
        $ours = $this->deploymentOnTemplate($template->id, 'ours');
        $theirs = $this->deploymentOnTemplate($otherTemplate->id, 'theirs');

        $this->copyVariable($template->id, 'SHARED', 'from-the-template');

        $this->assertSame('from-the-template', $this->variableOf($ours->id, 'SHARED'));
        $this->assertNull($this->variableOf($theirs->id, 'SHARED'));
    }

    /**
     * Without `override` an existing value is left alone. That is what makes the endpoint
     * safe to use for filling a gap: a deployment someone has tuned by hand keeps its
     * value.
     */
    public function testAnExistingValueIsKeptWhenOverrideIsNotAsked(): void {
        $template = Fixtures::workspaceTemplate();
        $deployment = $this->deploymentOnTemplate($template->id, 'tuned');
        Fixtures::deploymentEnvironmentVariable([
            'deployment_id' => $deployment->id,
            'name' => 'SHARED',
            'value' => 'set-by-hand',
        ]);

        $this->copyVariable($template->id, 'SHARED', 'from-the-template');

        $this->assertSame('set-by-hand', $this->variableOf($deployment->id, 'SHARED'));
    }

    public function testAnExistingValueIsReplacedWhenOverrideIsAsked(): void {
        $template = Fixtures::workspaceTemplate();
        $deployment = $this->deploymentOnTemplate($template->id, 'tuned');
        Fixtures::deploymentEnvironmentVariable([
            'deployment_id' => $deployment->id,
            'name' => 'SHARED',
            'value' => 'set-by-hand',
        ]);

        $this->copyVariable($template->id, 'SHARED', 'from-the-template', 'true');

        $this->assertSame('from-the-template', $this->variableOf($deployment->id, 'SHARED'));
    }

    /**
     * Only `1` and a lowercase `true` count as yes. `TRUE`, `yes` and `on` are all read as
     * no, and the call still answers OK - so a client that sends the wrong spelling is
     * told its overwrite worked when it did not happen. Pinned rather than fixed, like the
     * other endpoints that answer OK when nothing was done.
     */
    public function testOverrideOnlyUnderstandsOneAndLowercaseTrue(): void {
        $template = Fixtures::workspaceTemplate();

        // One deployment, reused. Building a workspace and a deployment per spelling made
        // this the slowest test in the suite by a wide margin, and it proved nothing the
        // reset below does not.
        $deployment = $this->deploymentOnTemplate($template->id, 'tried');
        Fixtures::deploymentEnvironmentVariable([
            'deployment_id' => $deployment->id,
            'name' => 'SHARED',
            'value' => 'set-by-hand',
        ]);

        foreach (['1' => true, 'true' => true, 'TRUE' => false, 'yes' => false, 'on' => false] as $spelling => $expected) {
            // Straight to the table. Resetting through the entity does not work: the
            // controller has changed the row behind it, so assigning the value it already
            // holds in memory is not a change and nothing is written.
            $this->db->table('environment_variables')
                ->where('deployment_id', $deployment->id)
                ->where('name', 'SHARED')
                ->update(['value' => 'set-by-hand']);

            $this->copyVariable($template->id, 'SHARED', 'from-the-template', (string) $spelling);

            $this->assertSame(
                $expected ? 'from-the-template' : 'set-by-hand',
                $this->variableOf($deployment->id, 'SHARED'),
                "override={$spelling}"
            );
        }
    }

    /**
     * Other variables on the same deployment are left as they are - only the named one is
     * written.
     */
    public function testOtherVariablesOnTheSameDeploymentAreLeftAlone(): void {
        $template = Fixtures::workspaceTemplate();
        $deployment = $this->deploymentOnTemplate($template->id, 'api');
        Fixtures::deploymentEnvironmentVariable([
            'deployment_id' => $deployment->id,
            'name' => 'UNRELATED',
            'value' => 'keep-me',
        ]);

        $this->copyVariable($template->id, 'SHARED', 'from-the-template', 'true');

        $this->assertSame('keep-me', $this->variableOf($deployment->id, 'UNRELATED'));
        $this->assertSame('from-the-template', $this->variableOf($deployment->id, 'SHARED'));
    }

    /**
     * The value comes from the template, never from the request: a secret one is not shown to
     * the UI, and a value in a url ends up in logs.
     */
    public function testAVariableTheTemplateDoesNotHaveIsRefused(): void {
        $template = Fixtures::workspaceTemplate();
        $deployment = $this->deploymentOnTemplate($template->id, 'api');

        $response = $this->signedIn()->put(
            "workspace-templates/{$template->id}/environment-variables/copy-to-deployments?name=MISSING&value=from-the-url"
        );
        $body = json_decode((string) $response->response()->getBody(), true);

        $this->assertSame('unknown environment variable', $body['error']);
        $this->assertNull($this->variableOf($deployment->id, 'MISSING'));
    }

    public function testASecretVariableIsCopiedAsASecret(): void {
        $template = Fixtures::workspaceTemplate();
        $deployment = $this->deploymentOnTemplate($template->id, 'api');

        $this->copyVariable($template->id, 'API_TOKEN', 'token-value', null, true);

        $variable = (new EnvironmentVariableModel())->where('deployment_id', $deployment->id)->where('name', 'API_TOKEN')->find();
        $this->assertSame('token-value', $variable->value);
        $this->assertTrue((bool) $variable->is_secret);
    }

    /**
     * Unlike the update endpoints, this one refuses an unknown template rather than
     * answering OK - it is one of the few that does.
     */
    public function testAnUnknownTemplateIsRefused(): void {
        $body = $this->copyVariable(999999, 'SHARED', 'anything');

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('unknown workspace template', $body['error']);
    }

    // </editor-fold>

    // <editor-fold desc="Helpers">

    /**
     * @param array<array<string, mixed>> $values
     * @return array<string, mixed>
     */
    private function putValues(string $path, array $values): array {
        $response = $this->withBodyFormat('json')->signedIn()->put($path, ['values' => $values]);

        return json_decode((string) $response->response()->getBody(), true);
    }

    /**
     * Gives the template the variable, then copies it out. The value is the template's own:
     * the endpoint is not sent one.
     *
     * @return array<string, mixed>
     */
    private function copyVariable(int $templateId, string $name, string $value, ?string $override = null, bool $isSecret = false): array {
        if ((new \App\Entities\WorkspaceTemplate())->find($templateId)->exists()) {
            $this->db->table('workspace_template_environment_variables')
                ->where('workspace_template_id', $templateId)
                ->where('name', $name)
                ->delete();
            $variable = \App\Entities\WorkspaceTemplateEnvironmentVariable::Create($name, $value, $isSecret);
            $variable->workspace_template_id = $templateId;
            $variable->save();
        }

        $query = http_build_query(array_filter([
            'name' => $name,
            'override' => $override,
        ], static fn ($v) => $v !== null));

        $response = $this->signedIn()->put(
            "workspace-templates/{$templateId}/environment-variables/copy-to-deployments?{$query}"
        );

        return json_decode((string) $response->response()->getBody(), true);
    }

    private function deploymentOnTemplate(int $templateId, string $name): \App\Entities\Deployment {
        $workspace = Fixtures::workspace([
            'workspace_template_id' => $templateId,
            'namespace' => $name,
            'subdomain' => $name,
        ]);

        return Fixtures::deployment(['workspace_id' => $workspace->id, 'name' => $name]);
    }

    /**
     * @return \App\Entities\WorkspaceTemplateDeploymentSpecification[]
     */
    private function specificationsOf(WorkspaceTemplate $template): array {
        return (new WorkspaceTemplateDeploymentSpecificationModel())
            ->where('workspace_template_id', $template->id)
            ->orderBy('id', 'asc')
            ->find()
            ->all ?? [];
    }

    /**
     * The rows as stored, with the value decrypted - every value is encrypted where it is
     * stored.
     *
     * @return array<array<string, mixed>>
     */
    private function variablesOn(int $templateId): array {
        return array_map(
            static fn (array $row) => ['value' => \App\Libraries\Crypt::Decrypt($row['value'])] + $row,
            db_connect()->table('workspace_template_environment_variables')
                ->where('workspace_template_id', $templateId)
                ->orderBy('id', 'asc')
                ->get()->getResultArray()
        );
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function labelsOn(int $templateId): array {
        return db_connect()->table('labels_workspace_templates')
            ->select('labels.name, labels.value')
            ->join('labels', 'labels.id = labels_workspace_templates.label_id')
            ->where('labels_workspace_templates.workspace_template_id', $templateId)
            ->orderBy('labels.name', 'asc')
            ->get()->getResultArray();
    }

    private function variableOf(int $deploymentId, string $name): ?string {
        $variable = (new EnvironmentVariableModel())
            ->where('deployment_id', $deploymentId)
            ->where('name', $name)
            ->find();

        return $variable->exists() ? $variable->value : null;
    }

    // </editor-fold>

}
