<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\DeploymentPackage;
use App\Fixtures;
use App\Models\DeploymentPackageDeploymentSpecificationModel;
use App\Models\EnvironmentVariableModel;

/**
 * The deployment package - the template a workspace is onboarded from.
 *
 * Three of its four endpoints follow the wholesale-replacement pattern the rest of the API
 * uses. The fourth does not: `copy-to-deployments` reaches past the package and writes an
 * environment variable into **every deployment in every workspace on it**. It is the only
 * endpoint in the system that edits many customers' running configuration in one call, so
 * most of this file is about who it touches and who it leaves alone.
 *
 * The fixtures leave deployments in Draft, so the step triggers those writes emit stop at
 * the door and nothing reaches a cluster. See DeploymentsApiTest for that guard.
 */
class DeploymentPackagesApiTest extends ControllerTestCase {

    // <editor-fold desc="Specifications in the package">

    /**
     * The wire format is camelCase and nests the whole specification, rather than sending
     * an id - the mapping to columns is hand-written in the entity.
     */
    public function testSpecificationsAreAddedWithTheirDefaults(): void {
        $package = Fixtures::deploymentPackage();
        $specification = Fixtures::deploymentSpecification(['name' => 'api']);

        $this->putValues("deployment-packages/{$package->id}/deployment-specifications", [
            [
                'deploymentSpecification' => ['id' => $specification->id],
                'defaultVersion' => '3.1.4',
                'defaultReplicas' => 3,
                'defaultCpuLimit' => 500,
                'defaultAutoUpdateEnabled' => true,
                'defaultAutoUpdateTagRegex' => 'v[0-9]+',
            ],
        ]);

        $rows = $this->specificationsOf($package);

        $this->assertCount(1, $rows);
        $this->assertSame((int) $specification->id, (int) $rows[0]->deployment_specification_id);
        $this->assertSame('3.1.4', $rows[0]->default_version);
        $this->assertSame(3, (int) $rows[0]->default_replicas);
        $this->assertSame(500, (int) $rows[0]->default_cpu_limit);
        $this->assertSame('v[0-9]+', $rows[0]->default_auto_update_tag_regex);
    }

    /**
     * Defaults are optional, and a deployment made from the package then falls back to
     * whatever the specification says.
     *
     * The entity writes null for what was left out, but the columns carry the defaults the
     * AddColumnDefaults migration gave them - so it comes back as an empty string and a
     * zero, not as null. Worth knowing before writing `?? 'fallback'` against these.
     */
    public function testOmittedDefaultsComeBackAsTheColumnDefaults(): void {
        $package = Fixtures::deploymentPackage();
        $specification = Fixtures::deploymentSpecification(['name' => 'api']);

        $this->putValues("deployment-packages/{$package->id}/deployment-specifications", [
            ['deploymentSpecification' => ['id' => $specification->id]],
        ]);

        $row = $this->specificationsOf($package)[0];

        $this->assertSame('', $row->default_version);
        $this->assertSame(0, (int) $row->default_replicas);
    }

    /**
     * The warm-pod flag is not sent - it is derived from whether any schedule ids came
     * with the request. Sending none turns it off.
     */
    public function testTheWarmPodFlagFollowsWhetherSchedulesWereSent(): void {
        $package = Fixtures::deploymentPackage();
        $schedule = Fixtures::minScaleSchedule();
        $withSchedules = Fixtures::deploymentSpecification(['name' => 'with']);
        $withoutSchedules = Fixtures::deploymentSpecification(['name' => 'without']);

        $this->putValues("deployment-packages/{$package->id}/deployment-specifications", [
            [
                'deploymentSpecification' => ['id' => $withSchedules->id],
                'defaultKnativeScheduledMinScaleIds' => [$schedule->id],
            ],
            [
                'deploymentSpecification' => ['id' => $withoutSchedules->id],
                'defaultKnativeScheduledMinScaleIds' => [],
            ],
        ]);

        $rows = $this->specificationsOf($package);

        $this->assertTrue((bool) $rows[0]->default_knative_scheduled_minscale_is_enabled);
        $this->assertFalse((bool) $rows[1]->default_knative_scheduled_minscale_is_enabled);
    }

    public function testSendingTheListAgainReplacesIt(): void {
        $package = Fixtures::deploymentPackage();
        $first = Fixtures::deploymentSpecification(['name' => 'first']);
        $second = Fixtures::deploymentSpecification(['name' => 'second']);

        $this->putValues("deployment-packages/{$package->id}/deployment-specifications", [
            ['deploymentSpecification' => ['id' => $first->id]],
            ['deploymentSpecification' => ['id' => $second->id]],
        ]);
        $this->assertCount(2, $this->specificationsOf($package));

        $this->putValues("deployment-packages/{$package->id}/deployment-specifications", [
            ['deploymentSpecification' => ['id' => $second->id]],
        ]);

        $rows = $this->specificationsOf($package);
        $this->assertCount(1, $rows);
        $this->assertSame((int) $second->id, (int) $rows[0]->deployment_specification_id);
    }

    /**
     * An empty list empties the package. A workspace onboarded afterwards gets no
     * deployments at all, and the call still answers OK.
     */
    public function testAnEmptyListEmptiesThePackage(): void {
        $package = Fixtures::deploymentPackage();
        $specification = Fixtures::deploymentSpecification(['name' => 'api']);
        $this->putValues("deployment-packages/{$package->id}/deployment-specifications", [
            ['deploymentSpecification' => ['id' => $specification->id]],
        ]);

        $body = $this->putValues("deployment-packages/{$package->id}/deployment-specifications", []);

        $this->assertSame('OK', $body['status']);
        $this->assertCount(0, $this->specificationsOf($package));
    }

    // </editor-fold>

    // <editor-fold desc="Variables on the package itself">

    /**
     * The package's own variables, which a workspace onboarded from it starts with. Not to
     * be confused with `copy-to-deployments` below: this writes one row per variable on the
     * package and touches nothing that is already running.
     */
    public function testEnvironmentVariablesAreStoredOnThePackage(): void {
        $package = Fixtures::deploymentPackage();

        $this->putValues("deployment-packages/{$package->id}/environment-variables", [
            ['name' => 'LOG_LEVEL', 'value' => 'debug'],
        ]);

        $row = db_connect()->table('deployment_package_environment_variables')
            ->where('deployment_package_id', $package->id)
            ->get()->getRowArray();
        $this->assertSame('LOG_LEVEL', $row['name']);
        $this->assertSame('debug', $row['value']);
    }

    /**
     * Wholesale replacement again - the list that arrives is the list there is.
     */
    public function testSendingTheVariableListAgainReplacesIt(): void {
        $package = Fixtures::deploymentPackage();

        $this->putValues("deployment-packages/{$package->id}/environment-variables", [
            ['name' => 'KEPT', 'value' => 'one'],
            ['name' => 'DROPPED', 'value' => 'two'],
        ]);
        $this->putValues("deployment-packages/{$package->id}/environment-variables", [
            ['name' => 'KEPT', 'value' => 'three'],
        ]);

        $rows = $this->variablesOn($package->id);

        $this->assertCount(1, $rows);
        $this->assertSame('KEPT', $rows[0]['name']);
        $this->assertSame('three', $rows[0]['value']);
    }

    /**
     * **The two endpoints on this controller disagree about unknown ids.** This one answers
     * OK and writes nothing; `copy-to-deployments` and `labels` refuse with an error. A
     * client cannot tell from the status whether the package it named exists. Pinned rather
     * than fixed, like the other update endpoints that answer OK for an unknown id.
     */
    public function testAnUnknownPackageIsRefusedByTheVariablesEndpoint(): void {
        $body = $this->putValues('deployment-packages/999999/environment-variables', [
            ['name' => 'LOG_LEVEL', 'value' => 'debug'],
        ]);

        $this->assertSame('unknown deployment package', $body['error'] ?? null);
        $this->assertCount(0, $this->variablesOn(999999));
    }

    // </editor-fold>

    // <editor-fold desc="Labels">

    /**
     * Labels are shared rows joined to the package through a junction table, so the set is
     * the junction - a second call replaces it whole.
     */
    public function testLabelsReplaceTheWholeSet(): void {
        $package = Fixtures::deploymentPackage();

        $this->putValues("deployment-packages/{$package->id}/labels", [
            ['name' => 'team', 'value' => 'platform'],
            ['name' => 'tier', 'value' => 'backend'],
        ]);
        $this->assertCount(2, $this->labelsOn($package->id));

        $this->putValues("deployment-packages/{$package->id}/labels", [
            ['name' => 'team', 'value' => 'infra'],
        ]);

        $rows = $this->labelsOn($package->id);
        $this->assertCount(1, $rows);
        $this->assertSame('team', $rows[0]['name']);
        $this->assertSame('infra', $rows[0]['value']);
    }

    /**
     * Unlike the variables endpoint next door, this one refuses an unknown package.
     */
    public function testAnUnknownPackageIsRefusedByTheLabelsEndpoint(): void {
        $body = $this->putValues('deployment-packages/999999/labels', [
            ['name' => 'team', 'value' => 'platform'],
        ]);

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('unknown deployment package', $body['error']);
        $this->assertCount(0, $this->labelsOn(999999));
    }

    // </editor-fold>

    // <editor-fold desc="Copying a variable out to every deployment">

    /**
     * The fan-out. One call, and every deployment in every workspace on this package gets
     * the variable.
     */
    public function testAVariableIsCopiedToEveryDeploymentOnThePackage(): void {
        $package = Fixtures::deploymentPackage();
        $first = $this->deploymentOnPackage($package->id, 'first');
        $second = $this->deploymentOnPackage($package->id, 'second');

        $body = $this->copyVariable($package->id, 'SHARED', 'from-the-package');

        $this->assertSame('OK', $body['status']);
        $this->assertSame('from-the-package', $this->variableOf($first->id, 'SHARED'));
        $this->assertSame('from-the-package', $this->variableOf($second->id, 'SHARED'));
    }

    /**
     * And nobody else. A workspace on another package is a different customer's
     * configuration, and the join through the workspace is the only thing keeping it out.
     */
    public function testDeploymentsOnAnotherPackageAreNotTouched(): void {
        $package = Fixtures::deploymentPackage(['name' => 'ours']);
        $otherPackage = Fixtures::deploymentPackage(['name' => 'theirs']);
        $ours = $this->deploymentOnPackage($package->id, 'ours');
        $theirs = $this->deploymentOnPackage($otherPackage->id, 'theirs');

        $this->copyVariable($package->id, 'SHARED', 'from-the-package');

        $this->assertSame('from-the-package', $this->variableOf($ours->id, 'SHARED'));
        $this->assertNull($this->variableOf($theirs->id, 'SHARED'));
    }

    /**
     * Without `override` an existing value is left alone. That is what makes the endpoint
     * safe to use for filling a gap: a deployment someone has tuned by hand keeps its
     * value.
     */
    public function testAnExistingValueIsKeptWhenOverrideIsNotAsked(): void {
        $package = Fixtures::deploymentPackage();
        $deployment = $this->deploymentOnPackage($package->id, 'tuned');
        Fixtures::deploymentEnvironmentVariable([
            'deployment_id' => $deployment->id,
            'name' => 'SHARED',
            'value' => 'set-by-hand',
        ]);

        $this->copyVariable($package->id, 'SHARED', 'from-the-package');

        $this->assertSame('set-by-hand', $this->variableOf($deployment->id, 'SHARED'));
    }

    public function testAnExistingValueIsReplacedWhenOverrideIsAsked(): void {
        $package = Fixtures::deploymentPackage();
        $deployment = $this->deploymentOnPackage($package->id, 'tuned');
        Fixtures::deploymentEnvironmentVariable([
            'deployment_id' => $deployment->id,
            'name' => 'SHARED',
            'value' => 'set-by-hand',
        ]);

        $this->copyVariable($package->id, 'SHARED', 'from-the-package', 'true');

        $this->assertSame('from-the-package', $this->variableOf($deployment->id, 'SHARED'));
    }

    /**
     * Only `1` and a lowercase `true` count as yes. `TRUE`, `yes` and `on` are all read as
     * no, and the call still answers OK - so a client that sends the wrong spelling is
     * told its overwrite worked when it did not happen. Pinned rather than fixed, like the
     * other endpoints that answer OK when nothing was done.
     */
    public function testOverrideOnlyUnderstandsOneAndLowercaseTrue(): void {
        $package = Fixtures::deploymentPackage();

        // One deployment, reused. Building a workspace and a deployment per spelling made
        // this the slowest test in the suite by a wide margin, and it proved nothing the
        // reset below does not.
        $deployment = $this->deploymentOnPackage($package->id, 'tried');
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

            $this->copyVariable($package->id, 'SHARED', 'from-the-package', (string) $spelling);

            $this->assertSame(
                $expected ? 'from-the-package' : 'set-by-hand',
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
        $package = Fixtures::deploymentPackage();
        $deployment = $this->deploymentOnPackage($package->id, 'api');
        Fixtures::deploymentEnvironmentVariable([
            'deployment_id' => $deployment->id,
            'name' => 'UNRELATED',
            'value' => 'keep-me',
        ]);

        $this->copyVariable($package->id, 'SHARED', 'from-the-package', 'true');

        $this->assertSame('keep-me', $this->variableOf($deployment->id, 'UNRELATED'));
        $this->assertSame('from-the-package', $this->variableOf($deployment->id, 'SHARED'));
    }

    /**
     * Unlike the update endpoints, this one refuses an unknown package rather than
     * answering OK - it is one of the few that does.
     */
    public function testAnUnknownPackageIsRefused(): void {
        $body = $this->copyVariable(999999, 'SHARED', 'anything');

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('unknown deployment package', $body['error']);
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
     * @return array<string, mixed>
     */
    private function copyVariable(int $packageId, string $name, string $value, ?string $override = null): array {
        $query = http_build_query(array_filter([
            'name' => $name,
            'value' => $value,
            'override' => $override,
        ], static fn ($v) => $v !== null));

        $response = $this->signedIn()->put(
            "deployment-packages/{$packageId}/environment-variables/copy-to-deployments?{$query}"
        );

        return json_decode((string) $response->response()->getBody(), true);
    }

    private function deploymentOnPackage(int $packageId, string $name): \App\Entities\Deployment {
        $workspace = Fixtures::workspace([
            'deployment_package_id' => $packageId,
            'namespace' => $name,
            'subdomain' => $name,
        ]);

        return Fixtures::deployment(['workspace_id' => $workspace->id, 'name' => $name]);
    }

    /**
     * @return \App\Entities\DeploymentPackageDeploymentSpecification[]
     */
    private function specificationsOf(DeploymentPackage $package): array {
        return (new DeploymentPackageDeploymentSpecificationModel())
            ->where('deployment_package_id', $package->id)
            ->orderBy('id', 'asc')
            ->find()
            ->all ?? [];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function variablesOn(int $packageId): array {
        return db_connect()->table('deployment_package_environment_variables')
            ->where('deployment_package_id', $packageId)
            ->orderBy('id', 'asc')
            ->get()->getResultArray();
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function labelsOn(int $packageId): array {
        return db_connect()->table('deployment_packages_labels')
            ->select('labels.name, labels.value')
            ->join('labels', 'labels.id = deployment_packages_labels.label_id')
            ->where('deployment_packages_labels.deployment_package_id', $packageId)
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
