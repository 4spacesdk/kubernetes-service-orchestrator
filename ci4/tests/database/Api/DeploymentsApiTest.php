<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Deployments endpoints, which is where the frontend does most of its work.
 *
 * Almost none of it is plain REST. There are sixteen custom endpoints, one per field or
 * group of fields the UI can change, and they share a shape worth knowing:
 *
 *     $item->find($id);
 *     if ($item->exists()) { ...change it... }
 *     $this->_setResource($item);
 *     $this->success();
 *
 * A request for an id that does not exist therefore answers **200 with an empty
 * resource** rather than 404, and changes nothing. The caller is told it worked. Several
 * tests below pin that, because it is consistent enough to be a decision rather than an
 * oversight - which does not make it right.
 *
 * **Nothing here touches a cluster - but not for the reason it first appeared.** An
 * earlier version of this note said the Draft check in `EmitTrigger()` was enough. It is
 * not: `checkStatus()` runs on the line before it and asks every step for its status,
 * which a step answers by calling the cluster. These tests were reaching a real one, and
 * the only reason nobody noticed is that they do not assert on what came back.
 *
 * `DatabaseTestCase` now clears `KUBERNETES_AUTH` for every test that is not an
 * integration test, so there is nothing to reach. The Draft check still matters - it is
 * what stops a deploy from being attempted - and the test below holds it in place.
 */
class DeploymentsApiTest extends ControllerTestCase {

    // <editor-fold desc="Creating">

    public function testADeploymentIsCreatedFromASpecification(): void {
        $specification = Fixtures::deploymentSpecification(['name' => 'api-spec']);
        $workspace = Fixtures::workspace();

        $body = $this->decode($this->signedIn()->post(
            'deployments/create?' . http_build_query([
                'deploymentSpecificationId' => $specification->id,
                'workspaceId' => $workspace->id,
                'name' => 'from-the-api',
                'version' => '2.0.0',
            ])
        ));

        $this->assertSame('OK', $body['status']);
        $this->assertSame('from-the-api', $body['resource']['name']);
        $this->assertSame('2.0.0', $body['resource']['version']);
        $this->assertSame((int) $workspace->id, (int) $body['resource']['workspace_id']);
    }

    /**
     * The specification is the one argument `create` checks, and the endpoint says so
     * rather than building a deployment out of nothing.
     */
    public function testCreatingFailsOnAnUnknownSpecification(): void {
        $body = $this->decode($this->signedIn()->post('deployments/create?deploymentSpecificationId=999999'));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('unknown deployment specification', $body['error']);
    }

    /**
     * `create` has two halves. With a workspace it delegates to the workspace, which knows
     * the package and its defaults; without one it prepares the deployment itself, and then
     * the namespace has to come from the request because there is nothing to inherit it
     * from. That is the standalone deployment - one that belongs to no customer.
     */
    public function testADeploymentIsCreatedWithoutAWorkspace(): void {
        $specification = Fixtures::deploymentSpecification(['name' => 'standalone-spec']);

        $body = $this->decode($this->signedIn()->post(
            'deployments/create?' . http_build_query([
                'deploymentSpecificationId' => $specification->id,
                'namespace' => 'infra',
                'name' => 'no-workspace',
                'version' => '4.5.6',
            ])
        ));

        $this->assertSame('OK', $body['status']);
        $this->assertSame('infra', $body['resource']['namespace']);
        $this->assertSame('4.5.6', $body['resource']['version']);
        $this->assertNull($body['resource']['workspace_id'], 'it belongs to no workspace');
        $this->assertSame(\DeploymentStatusTypes::Draft, $body['resource']['status']);

        // The resource in the response is the prepared entity, which carries these values
        // whether or not it was written - so the row is what proves the save happened.
        $this->assertSame(1, db_connect()->table('deployments')
            ->where('namespace', 'infra')
            ->where('name', 'no-workspace')
            ->where('deletion_id IS NULL')
            ->countAllResults());
    }

    /**
     * Everything `Prepare` refuses arrives as an exception and is turned into the same
     * failure shape. Without a workspace there is no namespace to inherit, so leaving it
     * out is the first rule it trips over.
     */
    public function testCreatingWithoutANamespaceIsRefused(): void {
        $specification = Fixtures::deploymentSpecification(['name' => 'needs-a-namespace']);

        $body = $this->decode($this->signedIn()->post(
            'deployments/create?' . http_build_query([
                'deploymentSpecificationId' => $specification->id,
                'name' => 'nowhere',
            ])
        ));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('Namespace missing', $body['error']);
    }

    /**
     * A namespace and a name together are what identifies a deployment, so the second one
     * is refused rather than written - the only uniqueness rule on this controller.
     */
    public function testTheSameNameCannotBeUsedTwiceInOneNamespace(): void {
        $specification = Fixtures::deploymentSpecification(['name' => 'twice']);
        Fixtures::deployment(['namespace' => 'infra', 'name' => 'taken']);

        $body = $this->decode($this->signedIn()->post(
            'deployments/create?' . http_build_query([
                'deploymentSpecificationId' => $specification->id,
                'namespace' => 'infra',
                'name' => 'taken',
            ])
        ));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('Deployment already exists', $body['error']);
    }

    /**
     * `POST /deployments` and `PUT /deployments/{id}` are in the route table and reach
     * methods with empty bodies - the plain REST create and update, which this controller
     * does not implement. They answer **200 with no body at all**: not the usual envelope
     * with a status in it, not an error, nothing. `success()` is never called, so there is
     * nothing for the response to be built from.
     *
     * A caller that used them - and they are generated into the API client like every other
     * route - would be told the write succeeded by the status code and find nothing written.
     * The same two methods on `Workspaces` are marked `@codeCoverageIgnore`; these are not,
     * so they are pinned here instead.
     */
    #[DataProvider('theEmptyRestEndpoints')]
    public function testThePlainRestWriteEndpointsDoNothing(string $method, string $path): void {
        $before = db_connect()->table('deployments')->countAllResults();

        $response = $this->signedIn()->$method($path)->response();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame($before, db_connect()->table('deployments')->countAllResults());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function theEmptyRestEndpoints(): array {
        return [
            'post' => ['post', 'deployments'],
            'put' => ['put', 'deployments/1'],
        ];
    }

    // </editor-fold>

    // <editor-fold desc="Changing one field at a time">

    public function testTheVersionIsUpdated(): void {
        $deployment = Fixtures::deployment(['version' => '1.0.0']);

        $body = $this->decode($this->signedIn()->put("deployments/{$deployment->id}/version?value=1.2.3"));

        $this->assertSame('1.2.3', $body['resource']['version']);
        $this->assertSame('1.2.3', $this->reload($deployment)->version);
    }

    /**
     * The guard that keeps this suite off a cluster, asserted where it actually lives.
     *
     * Every update endpoint emits a step trigger, and `EmitTrigger()` returns at its first
     * line while the deployment is in Draft. The fixtures leave deployments there, so no
     * test in this suite reaches a cluster. Checking the status afterwards would not prove
     * it - a deployment with no workspace fails on its first step and stays Draft either
     * way - so the guard is read from its own return value.
     */
    public function testTriggersStopAtTheDoorForADraftDeployment(): void {
        $deployment = Fixtures::deployableDeployment(['status' => \DeploymentStatusTypes::Draft]);

        $result = DeploymentStepHelper::EmitTrigger(
            DeploymentStepTriggers::Deployment_Version_Updated,
            $deployment
        );

        $this->assertSame('Deployment still in draft mode', $result);
    }

    public function testResourceManagementIsStoredAsGiven(): void {
        $deployment = Fixtures::deployment();

        $this->signedIn()->put("deployments/{$deployment->id}/resourceManagement?" . http_build_query([
            'cpuLimit' => 500, 'cpuRequest' => 100,
            'memoryLimit' => 512, 'memoryRequest' => 128,
            'replicas' => 3,
            'knativeConcurrencyLimitSoft' => 50, 'knativeConcurrencyLimitHard' => 10,
        ]));

        $saved = $this->reload($deployment);

        $this->assertSame(500, (int) $saved->cpu_limit);
        $this->assertSame(128, (int) $saved->memory_request);
        $this->assertSame(3, (int) $saved->replicas);
        $this->assertSame(10, (int) $saved->knative_concurrency_limit_hard);
    }

    /**
     * A deployment in Draft is not rolled out, and that is not a failure: the version is
     * saved and the answer is OK.
     */
    public function testADraftDeploymentsVersionIsSavedWithoutAnError(): void {
        $deployment = Fixtures::deployment(['version' => '1.0.0', 'status' => \DeploymentStatusTypes::Draft]);

        $body = $this->decode($this->signedIn()->put("deployments/{$deployment->id}/version?value=1.2.3"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame('1.2.3', $this->reload($deployment)->version);
    }

    public function testTheVersionOfAnUnknownDeploymentIsRefused(): void {
        $body = $this->decode($this->signedIn()->put('deployments/999999/version?value=1.2.3'));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('unknown deployment', $body['error']);
    }

    /**
     * Today's behaviour on the other update endpoints: an id that does not exist is
     * answered with OK and an empty resource. Nothing was changed and the caller is not
     * told. The version endpoint no longer does this.
     */
    public function testUpdatingAnUnknownDeploymentIsRefused(): void {
        $body = $this->decode($this->signedIn()->put('deployments/999999/image-pull-policy?value=Always'));

        $this->assertSame('unknown deployment', $body['error'] ?? null);
    }

    /**
     * A pattern that cannot compile used to be caught, written to the debug log, and
     * reported as a success - so the deployment kept the pattern it had and nobody was
     * told. It is refused now, and the stored one is left alone.
     */
    public function testAPatternThatCannotCompileIsRefusedAndNothingIsStored(): void {
        $deployment = Fixtures::deployment(['auto_update_tag_regex' => 'v[0-9]+']);

        $body = $this->decode($this->signedIn()->put(
            "deployments/{$deployment->id}/updateManagement?" . http_build_query([
                'enabled' => 'true',
                'tagRegex' => '[unterminated',
                'requireApproval' => 'false',
            ])
        ));

        $this->assertStringContainsString('not a valid regular expression', $body['error'] ?? '');
        $this->assertSame('v[0-9]+', $this->reload($deployment)->auto_update_tag_regex);
    }

    // </editor-fold>

    // <editor-fold desc="Sets that are replaced wholesale">

    /**
     * The list endpoints do not merge - they delete what is there and write what arrived.
     * Sending a shorter list is how you remove one.
     */
    public function testEnvironmentVariablesReplaceTheWholeSet(): void {
        $deployment = Fixtures::deployment();

        $this->putValues("deployments/{$deployment->id}/environment-variables", [
            ['name' => 'FIRST', 'value' => '1'],
            ['name' => 'SECOND', 'value' => '2'],
        ]);
        $this->assertSame(['FIRST', 'SECOND'], $this->environmentVariableNames($deployment));

        $this->putValues("deployments/{$deployment->id}/environment-variables", [
            ['name' => 'SECOND', 'value' => 'changed'],
        ]);
        $this->assertSame(['SECOND'], $this->environmentVariableNames($deployment));
    }

    /**
     * The same rule taken to its end: an empty list clears everything. Worth knowing,
     * because a client that sends `values: []` by mistake wipes the configuration and is
     * told it worked.
     */
    public function testAnEmptyListClearsEveryEnvironmentVariable(): void {
        $deployment = Fixtures::deployment();
        $this->putValues("deployments/{$deployment->id}/environment-variables", [
            ['name' => 'ONLY', 'value' => '1'],
        ]);

        $this->putValues("deployments/{$deployment->id}/environment-variables", []);

        $this->assertSame([], $this->environmentVariableNames($deployment));
    }

    // </editor-fold>

    // <editor-fold desc="Reading">

    public function testMigrationJobsAreListedForThisDeploymentOnly(): void {
        $mine = Fixtures::deployment(['name' => 'mine']);
        $other = Fixtures::deployment(['name' => 'other']);
        $this->migrationJob($mine->id, 'my-log');
        $this->migrationJob($other->id, 'not-my-log');

        $body = $this->decode($this->signedIn()->get("deployments/{$mine->id}/migration-jobs"));

        $this->assertSame(1, $body['count']);
        $this->assertSame('my-log', $body['resources'][0]['log']);
    }

    /**
     * This endpoint hands back the specification with the resolved step list attached,
     * which is what the deployment dialog draws its step rows from. It is also the one
     * place the step selection reaches the API.
     */
    public function testTheSpecificationEndpointCarriesTheResolvedStepList(): void {
        $deployment = Fixtures::deployableDeployment();

        $resource = $this->decode(
            $this->signedIn()->get("deployments/{$deployment->id}/deployment-specification")
        )['resource'];

        $this->assertSame((int) $deployment->deployment_specification_id, (int) $resource['id']);
        $this->assertNotEmpty($resource['deploymentSteps']);
        $this->assertContains(
            'Namespace',
            array_column($resource['deploymentSteps'], 'name')
        );
    }

    /**
     * The only endpoint on this controller that refuses an unknown id. Everything else
     * answers OK.
     */
    public function testTheSpecificationEndpointRejectsAnUnknownDeployment(): void {
        $body = $this->decode($this->signedIn()->get('deployments/999999/deployment-specification'));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('unknown deployment', $body['error']);
    }

    // </editor-fold>


    // <editor-fold desc="The single-field endpoints">

    /**
     * Five endpoints do nothing but write one column and emit a trigger. They are worth one
     * test between them: what matters is that the query parameter reaches the column, and
     * that the name on the wire is not the name in the table.
     */
    #[DataProvider('theSingleFieldEndpoints')]
    public function testASingleFieldEndpointWritesItsColumn(string $path, string $query, string $column, string $value): void {
        $deployment = Fixtures::deployableDeployment();

        $this->signedIn()->put("deployments/{$deployment->id}/{$path}?{$query}=" . urlencode($value));

        $this->assertSame($value, $this->reload($deployment)->$column);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function theSingleFieldEndpoints(): array {
        return [
            'image pull policy' => ['image-pull-policy', 'value', 'image_pull_policy', \ImagePullPolicies::Always],
            'environment' => ['environment', 'value', 'environment', \Environments::Development],
        ];
    }

    /**
     * Moving a deployment to another workspace, which is how a standalone one is adopted.
     * Only the column is rewritten - the namespace stays what it was, so the deployment
     * keeps living where it was created while belonging to a customer that may be
     * somewhere else entirely.
     */
    public function testADeploymentIsMovedToAnotherWorkspace(): void {
        $deployment = Fixtures::deployment(['namespace' => 'infra', 'name' => 'adopted']);
        $workspace = Fixtures::workspace(['namespace' => 'acme', 'subdomain' => 'acme']);

        $body = $this->decode($this->signedIn()->put(
            "deployments/{$deployment->id}/workspace?value={$workspace->id}"
        ));

        $this->assertSame('OK', $body['status']);

        $saved = $this->reload($deployment);
        $this->assertSame((int) $workspace->id, (int) $saved->workspace_id);
        $this->assertSame('infra', $saved->namespace, 'the namespace is not moved with it');
    }

    /**
     * The deployment's own database service, which overrides the workspace's when the
     * connection details are handed to the workload. Unlike its sibling on the workspace,
     * this one writes the column and stops - nothing is re-deployed.
     */
    public function testTheDatabaseServiceIsUpdated(): void {
        $deployment = Fixtures::deployment();
        $database = Fixtures::databaseService(['name' => 'own-db']);

        $body = $this->decode($this->signedIn()->put(
            "deployments/{$deployment->id}/databaseServiceId?value={$database->id}"
        ));

        $this->assertSame('OK', $body['status']);
        $this->assertSame((int) $database->id, (int) $this->reload($deployment)->database_service_id);
    }

    /**
     * `enabled` and `requireApproval` arrive as text and are read with
     * `in_array($value, ['1', 'true'])`. So `1` and `true` turn auto update on, and
     * everything else - including `on`, `yes` and `TRUE` - turns it off. A client that sent
     * any of those would be switching the feature off while its checkbox showed on.
     */
    #[DataProvider('theWaysAFlagCanArrive')]
    public function testOnlyOneAndTrueCountAsOn(string $sent, bool $expected): void {
        $deployment = Fixtures::deployableDeployment();

        $this->signedIn()->put(
            "deployments/{$deployment->id}/updateManagement?enabled={$sent}&tagRegex=v.*&requireApproval=1"
        );

        $fresh = $this->reload($deployment);
        $this->assertSame($expected, (bool) $fresh->auto_update_enabled, "enabled={$sent}");
        $this->assertSame('v.*', $fresh->auto_update_tag_regex, 'the pattern is stored whatever the flag says');
    }

    /**
     * **Today's behaviour: leaving `tagRegex` out is a 500.**
     *
     * The two flags are read with `in_array()` and survive being absent, but `tagRegex` is
     * handed straight to a `string` parameter, and `getGet()` answers null for a parameter
     * that was not sent. The `catch (\Exception)` on the next line does not help: a
     * TypeError is an `\Error`, not an `\Exception`, so it passes straight through the one
     * handler this endpoint has.
     *
     * It is the same endpoint as `testAnInvalidTagPatternIsReportedAsSuccess`, which is
     * what makes the pair worth reading together: a pattern that cannot compile is reported
     * as success, and a pattern that is not there at all is a crash.
     */
    /**
     * Turning auto update on without a pattern used to be a TypeError that escaped the
     * controller - a TypeError is an Error, not an Exception. An empty pattern would match
     * every tag, so it is refused rather than stored.
     */
    public function testTurningAutoUpdateOnWithoutATagPatternIsRefused(): void {
        $deployment = Fixtures::deployment();

        $body = $this->decode($this->signedIn()->put("deployments/{$deployment->id}/updateManagement?enabled=1"));

        $this->assertSame('A tag pattern is needed to turn auto update on', $body['error'] ?? null);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function theWaysAFlagCanArrive(): array {
        return [
            'one' => ['1', true],
            'true' => ['true', true],
            'on' => ['on', false],
            'yes' => ['yes', false],
            'uppercase TRUE' => ['TRUE', false],
            'zero' => ['0', false],
        ];
    }

    // </editor-fold>

    // <editor-fold desc="The collections">

    /**
     * A deployment's own volumes, which are merged with the specification's when the claim
     * is built. Same snake_case wire format as the specification endpoint, where
     * every other collection is sent in camelCase.
     */
    public function testAVolumeIsStoredWithTheFieldsItArrivedWith(): void {
        $deployment = Fixtures::deployableDeployment();

        $this->putValues("deployments/{$deployment->id}/volumes", [[
            'type' => 'nfs',
            'mount_path' => '/data',
            'sub_path' => 'tenant',
            'capacity' => 20,
            'volume_mode' => 'Filesystem',
            'reclaim_policy' => 'Delete',
            'nfs_server' => '10.0.0.2',
            'nfs_path' => '/exports/tenant',
            'storage_class' => 'nfs',
            'csi_driver' => '',
            'csi_volume_handle' => '',
        ]]);

        $row = db_connect()->table('deployment_volumes')->where('deployment_id', $deployment->id)->get()->getRowArray();
        $this->assertSame('/data', $row['mount_path']);
        $this->assertSame('Delete', $row['reclaim_policy']);
        $this->assertSame(20, (int) $row['capacity']);
    }

    /**
     * Saving the volumes it already has is not a change, so the cluster is not asked - which
     * matters because this suite has none: a call would fail, and the save would be refused
     * with the reason.
     */
    public function testSavingTheSameVolumeAgainIsNotAChange(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::deploymentVolume(array_merge(['deployment_id' => $deployment->id], $this->volume()));

        $body = $this->putVolumes($deployment, [$this->volume()]);

        $this->assertSame('OK', $body['status']);
    }

    /**
     * Whether the change is allowed can only be answered by the cluster, and a cluster that
     * cannot be reached is not an answer - so the save is refused with the reason rather
     * than stored and left to fail on the next deploy.
     */
    public function testAChangedVolumeIsRefusedWhenTheClusterCannotBeAsked(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::deploymentVolume(array_merge(['deployment_id' => $deployment->id], $this->volume()));

        $body = $this->putVolumes($deployment, [array_merge($this->volume(), ['capacity' => 50])]);

        $this->assertStringContainsString('could not be asked', $body['error'] ?? '');
    }

    /**
     * A deployment mounts one volume: every volume is named after it, so a second makes the
     * Deployment invalid and every deploy of it fails. Refused when saved, before a row is
     * written.
     */
    public function testASecondVolumeIsRefused(): void {
        $deployment = Fixtures::deployableDeployment();

        $body = $this->putVolumes($deployment, [$this->volume(), $this->volume()]);

        $this->assertSame('A deployment can have one volume', $body['error'] ?? null);
        $this->assertSame(0, db_connect()->table('deployment_volumes')->where('deployment_id', $deployment->id)->countAllResults());
    }

    public function testAVolumeOfItsOwnIsRefusedWhenTheSpecificationGivesOne(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::specificationVolume(['deployment_specification_id' => $deployment->deployment_specification_id]);

        $body = $this->putVolumes($deployment, [$this->volume()]);

        $this->assertStringContainsString('already gives this deployment its volume', $body['error'] ?? '');
    }

    /**
     * A removed volume is soft deleted and no longer mounted, so it does not count.
     */
    public function testARemovedSpecificationVolumeDoesNotCount(): void {
        $deployment = Fixtures::deployableDeployment();
        $removed = Fixtures::specificationVolume(['deployment_specification_id' => $deployment->deployment_specification_id]);
        db_connect()->table('deployment_specification_volumes')->where('id', $removed->id)->update(['deletion_id' => 1]);

        $body = $this->putVolumes($deployment, [$this->volume()]);

        $this->assertSame('OK', $body['status']);
    }

        public function testLabelsReplaceTheWholeSet(): void {
        $deployment = Fixtures::deployableDeployment();
        $this->putValues("deployments/{$deployment->id}/labels", [
            ['name' => 'team', 'value' => 'platform'],
            ['name' => 'tier', 'value' => 'backend'],
        ]);

        $this->putValues("deployments/{$deployment->id}/labels", [['name' => 'team', 'value' => 'infra']]);

        // Labels are shared rows joined to a deployment, so the set is the junction table.
        $rows = db_connect()->table('deployments_labels')
            ->select('labels.name, labels.value')
            ->join('labels', 'labels.id = deployments_labels.label_id')
            ->where('deployments_labels.deployment_id', $deployment->id)
            ->get()->getResultArray();
        $this->assertCount(1, $rows);
        $this->assertSame('infra', $rows[0]['value']);
    }

    /**
     * Cron jobs on a deployment take their position from the order of the list, as they do
     * on a specification.
     */
    public function testCronJobPositionComesFromTheOrderOfTheList(): void {
        $deployment = Fixtures::deployableDeployment();
        $image = Fixtures::containerImage(['name' => 'cron-image']);
        $first = Fixtures::cronJob(['name' => 'nightly', 'container_image_id' => $image->id]);
        $second = Fixtures::cronJob(['name' => 'hourly', 'container_image_id' => $image->id]);

        // Singular `deployment`, unlike every sibling endpoint on this controller. The
        // generated client follows the annotation, so it works - but the url list reads as
        // though this one belongs somewhere else.
        $this->putValues("deployment/{$deployment->id}/cron-jobs", [$second->id, $first->id]);

        $rows = db_connect()->table('deployment_cron_jobs')
            ->where('deployment_id', $deployment->id)
            ->orderBy('position', 'asc')
            ->get()->getResultArray();
        $this->assertSame(
            [(int) $second->id, (int) $first->id],
            array_map('intval', array_column($rows, 'k8s_cron_job_id'))
        );
        $this->assertSame([0, 1], array_map('intval', array_column($rows, 'position')));
    }

    /**
     * The schedules are picked by id rather than built from the body, so this endpoint is
     * the only one that ignores what it cannot find: an id that does not exist is simply
     * not in the result of the lookup.
     */
    public function testMinScaleSchedulesAreAttachedByIdAndUnknownIdsAreDropped(): void {
        $deployment = Fixtures::deployableDeployment();
        $schedule = Fixtures::minScaleSchedule(['description' => 'weekday mornings']);

        $this->putValues("deployments/{$deployment->id}/knative-min-scale-schedules", [$schedule->id, 999999]);

        $rows = db_connect()->table('deployments_knative_min_scale_schedules')
            ->where('deployment_id', $deployment->id)->get()->getResultArray();
        $this->assertSame([(int) $schedule->id], array_map('intval', array_column($rows, 'knative_min_scale_schedule_id')));
    }

    public function testAnEmptyListDetachesEverySchedule(): void {
        $deployment = Fixtures::deployableDeployment();
        $schedule = Fixtures::minScaleSchedule();
        $this->putValues("deployments/{$deployment->id}/knative-min-scale-schedules", [$schedule->id]);

        $this->putValues("deployments/{$deployment->id}/knative-min-scale-schedules", []);

        $this->assertSame(0, db_connect()->table('deployments_knative_min_scale_schedules')
            ->where('deployment_id', $deployment->id)->countAllResults());
    }

    // </editor-fold>

    /**
     * **Today's behaviour: `PUT /deployments/{id}/ingress` cannot work.**
     *
     * The controller calls `$item->updateIngress(...)` on a Deployment, and that method
     * exists only on Workspace - so every call is a fatal error before it reaches any
     * validation. A deployment has no domain or subdomain of its own either; those live on
     * the workspace, and the UI edits them through `/workspaces/{id}/ingress`, which works.
     *
     * Nothing in the frontend calls this one. It is still generated into the API client and
     * published in the OpenAPI document, so it reads as a supported endpoint.
     */
    public function testTheDeploymentIngressEndpointFailsOnEveryCall(): void {
        $deployment = Fixtures::deployableDeployment();
        $domain = Fixtures::domain(['name' => 'other.example.org']);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to undefined method');

        $this->signedIn()->put(
            "deployments/{$deployment->id}/ingress?domainId={$domain->id}&subdomain=tenant&aliases="
        );
    }

    // <editor-fold desc="Status without a cluster">

    /**
     * The status endpoint asks every step how it is doing and **writes the answer back**.
     * A step answers by calling the cluster, and there is none here - which is also the
     * state of an installation whose credentials have expired. What has to happen then is
     * what happens here: the steps fail to validate, the deployment drops to Draft, and the
     * endpoint answers rather than breaking the page.
     */
    public function testTheStatusEndpointRecomputesTheStatusWithoutACluster(): void {
        $deployment = Fixtures::deployableDeployment(['status' => \DeploymentStatusTypes::Active]);

        $body = $this->decode($this->signedIn()->get("deployments/{$deployment->id}/status"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame(
            \DeploymentStatusTypes::Draft,
            $this->reload($deployment)->status,
            'it was recomputed, not left as it was'
        );
    }

    // </editor-fold>

    // <editor-fold desc="Running a cron job now">

    public function testTheCronJobNamesAreListed(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::deploymentCronJob([
            'deployment_id' => $deployment->id,
            'k8s_cron_job_id' => Fixtures::cronJob(['container_image_id' => Fixtures::containerImage()->id])->id,
        ]);

        $body = $this->decode($this->signedIn()->get("deployments/{$deployment->id}/cron-jobs/names"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame(["{$deployment->name}-cleanup"], $body['resource']['names']);
    }

    public function testRunningACronJobTheDeploymentDoesNotHaveIsReported(): void {
        $deployment = Fixtures::deployableDeployment();

        $body = $this->decode($this->signedIn()->post("deployments/{$deployment->id}/cron-jobs/run?name=other-cleanup"));

        $this->assertNotSame('OK', $body['status']);
        $this->assertStringContainsString("'other-cleanup' is not one of the deployment's cron jobs", json_encode($body));
    }

    public function testRunningACronJobOnAnUnknownDeploymentIsReported(): void {
        $body = $this->decode($this->signedIn()->post('deployments/999999/cron-jobs/run?name=x'));

        $this->assertNotSame('OK', $body['status']);
        $this->assertStringContainsString('unknown deployment', json_encode($body));
    }

    // </editor-fold>

    // <editor-fold desc="Helpers">

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    /**
     * @param array<array<string, mixed>> $values
     */
    private function putValues(string $path, array $values): void {
        $this->withBodyFormat('json')->signedIn()->put($path, ['values' => $values]);
    }

    /**
     * @param array<array<string, mixed>> $volumes
     * @return array<string, mixed>
     */
    private function putVolumes(Deployment $deployment, array $volumes): array {
        return $this->decode($this->withBodyFormat('json')->signedIn()->put("deployments/{$deployment->id}/volumes", ['values' => $volumes]));
    }

    /**
     * @return array<string, mixed>
     */
    private function volume(): array {
        return [
            'type' => 'nfs', 'mount_path' => '/data', 'sub_path' => '', 'capacity' => 20,
            'volume_mode' => 'Filesystem', 'reclaim_policy' => 'Retain', 'nfs_server' => '10.0.0.2',
            'nfs_path' => '/exports', 'storage_class' => '', 'csi_driver' => '', 'csi_volume_handle' => '',
        ];
    }

        private function reload(Deployment $deployment): Deployment {
        $fresh = new Deployment();
        $fresh->find($deployment->id);

        return $fresh;
    }

    /**
     * @return string[]
     */
    private function environmentVariableNames(Deployment $deployment): array {
        $names = [];
        foreach ((new \App\Models\EnvironmentVariableModel())->where('deployment_id', $deployment->id)->find() as $variable) {
            $names[] = $variable->name;
        }
        sort($names);

        return $names;
    }

    private function migrationJob(int $deploymentId, string $log): void {
        $this->db->table('migration_jobs')->insert([
            'deployment_id' => $deploymentId,
            'status' => 'completed',
            'log' => $log,
            'command' => 'php spark migrate',
            'image' => 'registry/app:1.0',
            'created' => date('Y-m-d H:i:s'),
        ]);
    }

    // </editor-fold>

}
