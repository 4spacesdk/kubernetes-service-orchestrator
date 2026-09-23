<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use CodeIgniter\Test\TestResponse;
use OrmExtension\DataMapper\RelationDef;
use OrmExtension\Extensions\Model;

/**
 * `?include=` against every relation of every routed resource.
 *
 * `RestGetSweepTest` calls every plain read and took fourteen models to 100 %. The rest of
 * them have no route of their own: `DeploymentVolumeModel`, `LabelModel`,
 * `PodioFieldReferenceModel` and thirty more are only ever entered through
 * `ResourceModelTrait::applyIncludeMany()`, which fetches a relation by calling `restGet()`
 * on the **child's** model - and that is what runs the child's `preRestGet()`,
 * `postRestGet()` and `appleRestGetManyRelations()`.
 *
 * The list of relations is read off the models, the same way `RestGetSweepTest` reads its
 * list of resources out of `api_routes`: `getRelations()` is what `applyIncludeOne()` and
 * `applyIncludeMany()` resolve the include against, so a relation added to a `$hasOne` or
 * `$hasMany` tomorrow is swept without anyone remembering to add it here.
 *
 * **An include over an empty table proves nothing.** `restGet()` skips `postRestGet()` and
 * `appleRestGetManyRelations()` when nothing was found, so a sweep with no rows in it walks
 * past the methods it exists to reach. `arrangement()` therefore writes one row for every
 * resource and one child row for every relation it can; the ones it cannot are named in
 * `testTheseRelationsAreSweptWithoutAnyRowsInThem()` rather than left to look covered.
 *
 * What an included collection is allowed to contain - only its own parent's rows, and why
 * `ResourceModelInterface` is what makes that true - is pinned in `RelationIncludeApiTest`
 * and is not repeated here.
 */
class IncludeSweepTest extends ControllerTestCase {

    // <editor-fold desc="The models are the list">

    /**
     * Every resource with a plain collection read, mapped to its model.
     *
     * The route table names a controller, and `ResourceControllerTrait::_getResourceName()`
     * is what turns that into a model - so the same transformation is done here rather than
     * a second list being kept. A route whose controller has no model (`environments`
     * answers from an enum) maps to null and is swept for nothing.
     *
     * @return array<string, class-string<Model>|null>
     */
    private function resourceModels(): array {
        $rows = $this->db->table('api_routes')
            ->select('`from`, `to`', false)
            ->where('method', 'get')
            ->like('to', '::get', 'before')
            ->get()
            ->getResultArray();

        $models = [];
        foreach ($rows as $row) {
            $controller = substr($row['to'], 0, (int) strpos($row['to'], '::'));
            $model = str_replace('Controllers', 'Models', singular($controller) . 'Model');
            $models[$row['from']] = class_exists($model) ? $model : null;
        }

        ksort($models);

        return $models;
    }

    /**
     * The include property names of one model, and whether each is a has-one or a has-many.
     *
     * `RelationDef::getSimpleName()` is what `getRelation(..., true)` matches an include
     * against, so this is the same name a caller has to send. A has-many is read back under
     * the plural of it, which is what `applyIncludeMany()` assigns.
     *
     * @param class-string<Model> $modelName
     * @return array<string, int> include property to RelationDef::HasOne|HasMany
     */
    private function relationsOf(string $modelName): array {
        $relations = [];
        foreach ((new $modelName())->getRelations() as $relation) {
            $relations[$relation->getSimpleName()] = $relation->getType();
        }

        return $relations;
    }

    // </editor-fold>

    // <editor-fold desc="The sweep">

    /**
     * Every relation of every routed resource, asked for and answered.
     *
     * The relations of one resource are asked for in a single request rather than one each:
     * `parseInclude()` takes a comma-separated list and applies each one on its own, and a
     * request through the whole stack costs about fifty milliseconds - eighty-three of them
     * is four seconds on a suite with a thirty second budget. When a batched request comes
     * back wrong, `whichIncludeBrokeIt()` re-asks one relation at a time so the failure names
     * the offender rather than the resource.
     *
     * A has-many that is present has to be a list. It is not asserted to be present on
     * every row, because it is not - see
     * `testAnIncludedCollectionWithNoRowsInItIsLeftOutOfTheResponse()`.
     *
     * The second assertion is what keeps the first one worth making. An include over an empty
     * table answers `200 OK` with nothing in it, and `restGet()` never reaches the child's
     * `postRestGet()` or `appleRestGetManyRelations()` - so a sweep that only checked the
     * envelope would be green over a database with no rows in it at all. Everything not named
     * below came back carrying at least one row.
     *
     * A relation added later lands in that list and fails here, which is the point: the
     * choice is then to arrange a row for it or to write down why there cannot be one. The
     * `deletion` entries are the ones that cannot have a row by construction - a row with a
     * `deletion_id` is soft-deleted, and a soft-deleted row is not in the collection that
     * would carry the include.
     */
    public function testEveryRelationOfEveryRoutedResourceCanBeIncluded(): void {
        $this->arrangement();

        $deviations = [];
        $empty = [];

        foreach ($this->resourceModels() as $resource => $modelName) {
            if ($modelName === null) {
                continue;
            }

            $relations = $this->relationsOf($modelName);

            $body = $this->decode($this->signedIn()->get($resource . '?include=' . implode(',', array_keys($relations))));
            $resources = $body['resources'] ?? [];

            if (($body['status'] ?? null) !== 'OK') {
                $deviations[$this->whichIncludeBrokeIt($resource, $relations)] =
                    'status was ' . json_encode($body['status'] ?? null);
                continue;
            }
            if ($resources === []) {
                $deviations[$resource] = 'no rows to include anything on';
                continue;
            }

            foreach ($relations as $property => $type) {
                $where = "{$resource}?include={$property}";

                if (!$this->anyRowCarries($resources, $property, $type)) {
                    $empty[] = $where;
                }
                if ($type !== RelationDef::HasMany) {
                    continue;
                }

                $plural = plural($property);
                foreach ($resources as $row) {
                    if (array_key_exists($plural, $row) && !is_array($row[$plural])) {
                        $deviations[$where] = "{$plural} was " . json_encode($row[$plural]);
                        break;
                    }
                }
            }
        }

        $this->assertSame([], $deviations);

        sort($empty);
        $this->assertSame([
            'container_registries?include=deletion',
            'database_services?include=deletion',
            'deployments?include=deletion',
            'domains?include=deletion',
            'email_services?include=deletion',
            'gateways?include=deletion',
            'github_integrations?include=deletion',
            'podio_integrations?include=deletion',
            'projects?include=deletion',
            'users?include=deletion',
            'workspaces?include=deletion',
        ], $empty, 'these were swept over an empty table');
    }

    /**
     * Ask for one relation at a time and name the first that does not answer.
     *
     * Only reached when the batched request above already failed, so its cost is paid once
     * and only by a run that is failing anyway.
     *
     * @param array<string, int> $relations
     */
    private function whichIncludeBrokeIt(string $resource, array $relations): string {
        foreach (array_keys($relations) as $property) {
            $body = $this->decode($this->signedIn()->get("{$resource}?include={$property}"));

            if (($body['status'] ?? null) !== 'OK') {
                return "{$resource}?include={$property}";
            }
        }

        return "{$resource}?include= (only when they are asked for together)";
    }

    /**
     * What a child model's own `preRestGet()` pulls in, without anyone asking for it.
     *
     * Five models do more than nothing on the way in: they call `includeRelated()` so that
     * the rows they hand back already carry the relation the UI needs next, one query
     * instead of one per row. It is the only behaviour those files have, and nothing sees it
     * unless the join is asserted - the sweep above is green whether the join happened or
     * not.
     *
     * The nested pairs (`k8s_cron_job.container_image`, `init_container.container_image`)
     * are the two-element form of `includeRelated()`, which is a second join on top of the
     * first, and each is asserted by the value it carries rather than by the key being there.
     */
    public function testAChildModelsPreRestGetEagerlyLoadsWhatItNames(): void {
        $this->arrangement();

        $cronJob = $this->firstIncluded('deployments?include=deployment_cron_job', 'deployment_cron_jobs');
        $this->assertSame('sweep-cron', $cronJob['k8s_cron_job']['name'] ?? null);
        $this->assertSame('sweep-image', $cronJob['k8s_cron_job']['container_image']['name'] ?? null);

        $specificationCronJob = $this->firstIncluded(
            'deployment_specifications?include=deployment_specification_cron_job',
            'deployment_specification_cron_jobs'
        );
        $this->assertSame('sweep-cron', $specificationCronJob['k8s_cron_job']['name'] ?? null);
        $this->assertSame('sweep-image', $specificationCronJob['k8s_cron_job']['container_image']['name'] ?? null);

        $initContainer = $this->firstIncluded(
            'deployment_specifications?include=deployment_specification_init_container',
            'deployment_specification_init_containers'
        );
        $this->assertSame('sweep-init', $initContainer['init_container']['name'] ?? null);
        $this->assertSame('sweep-image', $initContainer['init_container']['container_image']['name'] ?? null);

        $action = $this->firstIncluded(
            'deployment_specifications?include=deployment_specification_post_update_action',
            'deployment_specification_post_update_actions'
        );
        $this->assertSame('sweep-action', $action['post_update_action']['name'] ?? null);

        // `MigrationJobModel` reaches two levels up rather than down: a migration job is
        // listed with the workspace of the deployment it belongs to.
        $body = $this->decode($this->signedIn()->get('migration_jobs'));
        $this->assertSame(
            'sweep-workspace',
            $body['resources'][0]['deployment']['workspace']['name_readable'] ?? null
        );
    }

    /**
     * The first row of an included collection, from whichever parent has one.
     *
     * @return array<string, mixed>
     */
    private function firstIncluded(string $path, string $key): array {
        $body = $this->decode($this->signedIn()->get($path));

        foreach ($body['resources'] ?? [] as $row) {
            if (($row[$key] ?? []) !== []) {
                return $row[$key][0];
            }
        }

        $this->fail("nothing came back under {$key} for {$path}");
    }

    /**
     * The two relations a by-id read is told to leave out, with rows in both of them.
     *
     * `applyRestGetOneRelations()` loads every relation of a single resource whether it was
     * asked for or not, minus whatever `ignoredRestGetOnRelations()` names -
     * `DeploymentModel` names migration jobs and auto updates, because a deployment
     * accumulates those forever and nobody reading one deployment wants its whole history.
     *
     * The arrangement writes one of each, which is what makes the absence mean something:
     * an included collection with no rows in it is left out of the response entirely (see
     * `testAnIncludedCollectionWithNoRowsInItIsLeftOutOfTheResponse()`), so asserting that
     * `auto_updates` is missing over an empty table passes with the ignore list deleted.
     * Both are asked for explicitly afterwards to show the rows were there all along.
     */
    public function testAByIdReadHoldsBackTheRelationsTheModelNamesEvenWhenTheyHaveRows(): void {
        $this->arrangement();

        $resource = $this->decode($this->signedIn()->get("deployments/{$this->deploymentId}"))['resource'] ?? [];

        $this->assertNotSame([], $resource['deployment_volumes'] ?? [], 'an ordinary relation is loaded');
        $this->assertArrayNotHasKey('migration_jobs', $resource);
        $this->assertArrayNotHasKey('auto_updates', $resource);

        $asked = $this->decode(
            $this->signedIn()->get("deployments/{$this->deploymentId}?include=auto_update")
        )['resource'] ?? [];

        $this->assertCount(1, $asked['auto_updates'] ?? [], 'the row the read above held back');
    }

    /**
     * A second level of include reaches the children of a child.
     *
     * `applyIncludeMany()` hands the nested query parser to the child's own `restGet()`, so
     * `include=a?include=b` is what enters a grandchild's model. Two models have no other
     * way in: `DeploymentSpecificationIngressAnnotationModel` and
     * `DeploymentSpecificationIngressRulePathModel` hang off an ingress, and an ingress
     * hangs off a specification, which is the only one of the three with a route.
     *
     * The nesting is written with `?` rather than a dot: a dotted `a.b` is resolved by
     * `getRelation()` into a list of relations which `applyIncludeMany()` then applies to
     * the **top-level** rows, one after the other, rather than to each other.
     */
    public function testANestedIncludeReachesTheChildrenOfAChild(): void {
        $this->arrangement();

        $paths = $this->theOnlyIngress('deployment_specification_ingress_rule_path');
        $this->assertSame(['/swept'], array_column($paths['deployment_specification_ingress_rule_paths'], 'path'));

        // One nested relation per request: `parseInclude()` splits the whole `include` value
        // on commas before `QueryInclude::parse()` ever sees the `?`, so a second nested
        // name is read as a relation of the *top-level* model and throws.
        $annotations = $this->theOnlyIngress('deployment_specification_ingress_annotation');
        $this->assertSame(
            ['sweep.example.org/annotation'],
            array_column($annotations['deployment_specification_ingress_annotations'], 'name')
        );
    }

    /**
     * The one ingress the arrangement writes, read back with one nested relation on it.
     *
     * @return array<string, mixed>
     */
    private function theOnlyIngress(string $nested): array {
        $body = $this->decode($this->signedIn()->get(
            'deployment_specifications?include='
            . rawurlencode("deployment_specification_ingress?include={$nested}")
        ));

        $this->assertSame('OK', $body['status'] ?? null);

        $ingresses = [];
        foreach ($body['resources'] ?? [] as $row) {
            foreach ($row['deployment_specification_ingresses'] ?? [] as $ingress) {
                $ingresses[] = $ingress;
            }
        }

        $this->assertCount(1, $ingresses, 'the arrangement writes exactly one ingress');

        return $ingresses[0];
    }

    /**
     * **An included collection with nothing in it is left out of the response entirely.**
     *
     * `applyIncludeMany()` assigns the child collection to the parent, and the entity only
     * serialises a relation it was given rows for - so the same request answers one row with
     * `deployment_volumes: [...]` and the next with no such key at all. A client that reads
     * `row.deployment_volumes.length` gets an error on exactly the rows that are fine.
     *
     * It is worth pinning rather than assuming, because the assumption is already written
     * down: `RelationIncludeApiTest::testAParentWithNoRowsGetsAnEmptyCollection()` is named
     * for the empty list, and its helper ends in `?? []` - so it passes either way and says
     * nothing about which of the two happens. Reported, not fixed; the fix makes this fail.
     */
    public function testAnIncludedCollectionWithNoRowsInItIsLeftOutOfTheResponse(): void {
        $workspace = Fixtures::workspace();
        $specification = Fixtures::deploymentSpecification();

        $withOne = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
            'name' => 'with-a-volume',
        ]);
        Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
            'name' => 'without-one',
        ]);
        Fixtures::deploymentVolume(['deployment_id' => $withOne->id, 'mount_path' => '/swept']);

        $byName = array_column(
            $this->decode($this->signedIn()->get('deployments?include=deployment_volume'))['resources'] ?? [],
            null,
            'name'
        );

        $this->assertSame(['/swept'], array_column($byName['with-a-volume']['deployment_volumes'], 'mount_path'));
        $this->assertArrayNotHasKey(
            'deployment_volumes',
            $byName['without-one'],
            'an empty included collection started coming back as [] - good, and this pin is now the fix'
        );
    }

    // </editor-fold>

    // <editor-fold desc="The guard on the sweep itself">

    /**
     * The sweep's own size, so a relation that disappears is as loud as one that is added.
     *
     * The numbers are read off the same source the sweep walks, so they only change when a
     * model's `$hasOne` or `$hasMany` changes - which is exactly the moment somebody should
     * look at whether the new relation has a row arranged for it.
     */
    public function testTheSweepStillCoversEveryRoutedResourceAndItsRelations(): void {
        $models = $this->resourceModels();

        $this->assertCount(28, $models, 'the number of plain collection reads changed');
        $this->assertSame(
            ['environments'],
            array_keys(array_filter($models, static fn ($model) => $model === null)),
            'a routed collection read has no model behind it'
        );

        $relations = 0;
        foreach (array_filter($models) as $modelName) {
            $relations += count($this->relationsOf($modelName));
        }

        $this->assertSame(101, $relations, 'the number of includable relations changed');
    }

    // </editor-fold>

    // <editor-fold desc="Rows">

    /**
     * One row per routed resource, and one child row per relation.
     *
     * Written once per test rather than once per class: the transaction is rolled back
     * between tests, so there is nothing to share.
     *
     * The order is the dependency order - a row is written after everything it points at.
     * Raw table writes are used where `Fixtures` has no helper; this test is not a reason to
     * add one to a shared file.
     */
    private function arrangement(): void {
        if ($this->arranged) {
            return;
        }
        $this->arranged = true;

        $registry = Fixtures::containerRegistry(['name' => 'sweep-registry']);
        $github = Fixtures::githubIntegration(['name' => 'sweep-github']);
        $image = Fixtures::containerImage(['name' => 'sweep-image', 'container_registry_id' => $registry->id, 'github_integration_id' => $github->id]);

        $specification = Fixtures::deploymentSpecification([
            'container_image_id' => $image->id,
            'database_migration_container_image_id' => $image->id,
            'domain_suffix' => '.svc',
        ]);

        $gateway = Fixtures::gateway();
        Fixtures::gatewayAddress(['gateway_id' => $gateway->id]);
        Fixtures::gatewayAnnotation(['gateway_id' => $gateway->id]);
        $domain = Fixtures::domain(['gateway_id' => $gateway->id, 'name' => 'sweep.example.org']);

        $emailService = Fixtures::emailService();
        $databaseService = Fixtures::databaseService();
        $project = Fixtures::project(['name' => 'sweep-project']);
        $template = Fixtures::workspaceTemplate(['project_id' => $project->id]);
        $this->insert('projects_users', ['project_id' => $project->id, 'user_id' => $this->signedInUserId()]);

        $workspace = Fixtures::workspace([
            'name_readable' => 'sweep-workspace',
            'domain_id' => $domain->id,
            'email_service_id' => $emailService->id,
            'database_service_id' => $databaseService->id,
            'workspace_template_id' => $template->id,
            'project_id' => $project->id,
        ]);

        $deployment = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
            'database_service_id' => $databaseService->id,
        ]);

        $migrationJob = $this->insert('migration_jobs', [
            'deployment_id' => $deployment->id,
            'status' => \MigrationJobStatusTypes::Completed,
            'log' => 'swept',
            'command' => 'php spark migrate',
            'image' => 'registry.example.org/test/app:1.0.0',
            'created' => date('Y-m-d H:i:s'),
        ]);
        $deployment->last_migration_job_id = $migrationJob;
        $deployment->save();

        $this->deploymentId = $deployment->id;

        $this->arrangeDeploymentChildren($deployment->id, $image->id);
        $this->arrangeSpecificationChildren($specification->id, $template->id, $image->id, $deployment->id);
        $this->arrangeTheRestOfTheResources($image->id, $workspace->id, $template->id);
    }

    private bool $arranged = false;

    private int $deploymentId = 0;

    /**
     * Everything that hangs off one deployment, plus the cron job and schedule it shares
     * with the specification.
     */
    private function arrangeDeploymentChildren(int $deploymentId, int $imageId): void {
        Fixtures::deploymentEnvironmentVariable(['deployment_id' => $deploymentId]);
        Fixtures::deploymentVolume(['deployment_id' => $deploymentId]);

        $this->insert('auto_updates', [
            'deployment_id' => $deploymentId,
            'image' => 'registry.example.org/test/app',
            'previous_tag' => '1.0.0',
            'next_tag' => '1.1.0',
            'is_approved' => 0,
            'approved_date' => '',
        ]);

        $this->attachLabel('deployments_labels', 'deployment_id', $deploymentId);

        $cronJob = Fixtures::cronJob(['container_image_id' => $imageId, 'name' => 'sweep-cron']);
        Fixtures::deploymentCronJob(['deployment_id' => $deploymentId, 'k8s_cron_job_id' => $cronJob->id]);
        $this->cronJobId = $cronJob->id;

        $schedule = Fixtures::minScaleSchedule();
        $this->insert('deployments_knative_min_scale_schedules', [
            'deployment_id' => $deploymentId,
            'knative_min_scale_schedule_id' => $schedule->id,
        ]);
        $this->scheduleId = $schedule->id;
    }

    private int $cronJobId = 0;
    private int $scheduleId = 0;

    /**
     * The seventeen collections that hang off one deployment specification.
     */
    private function arrangeSpecificationChildren(int $specificationId, int $templateId, int $imageId, int $deploymentId): void {
        Fixtures::servicePort(['deployment_specification_id' => $specificationId]);
        Fixtures::serviceAnnotation(['deployment_specification_id' => $specificationId]);
        Fixtures::deploymentAnnotation(['deployment_specification_id' => $specificationId]);
        Fixtures::roleRule(['deployment_specification_id' => $specificationId]);
        Fixtures::clusterRoleRule(['deployment_specification_id' => $specificationId]);
        Fixtures::httpProxyRoute(['deployment_specification_id' => $specificationId]);
        Fixtures::specificationVolume(['deployment_specification_id' => $specificationId]);
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $specificationId]);

        $ingress = Fixtures::ingress(['deployment_specification_id' => $specificationId]);
        Fixtures::ingressRulePath([
            'deployment_specification_ingress_id' => $ingress->id,
            'path' => '/swept',
        ]);
        $this->insert('deployment_specification_ingress_annotations', [
            'deployment_specification_ingress_id' => $ingress->id,
            'name' => 'sweep.example.org/annotation',
            'value' => 'swept',
        ]);

        $this->insert('deployment_specification_post_commands', [
            'deployment_specification_id' => $specificationId,
            'name' => 'sweep',
            'command' => 'echo swept',
            'all_pods' => 0,
            'container' => '',
        ]);
        $this->insert('deployment_specification_quick_commands', [
            'deployment_specification_id' => $specificationId,
            'name' => 'sweep',
            'command' => 'echo swept',
        ]);

        $templateSpecification = Fixtures::templateSpecification([
            'workspace_template_id' => $templateId,
            'deployment_specification_id' => $specificationId,
        ]);
        $this->insert('workspace_template_ds_knative_min_scale_schedules', [
            'workspace_template_deployment_specification_id' => $templateSpecification->id,
            'knative_min_scale_schedule_id' => $this->scheduleId,
        ]);

        $initContainer = Fixtures::initContainer(['container_image_id' => $imageId, 'name' => 'sweep-init']);
        Fixtures::specificationInitContainer([
            'deployment_specification_id' => $specificationId,
            'init_container_id' => $initContainer->id,
        ]);
        $this->insert('init_container_environment_variables', [
            'init_container_id' => $initContainer->id,
            'name' => 'SWEPT',
            'value' => 'yes',
        ]);

        Fixtures::specificationCronJob([
            'deployment_specification_id' => $specificationId,
            'k8s_cron_job_id' => $this->cronJobId,
        ]);

        $this->attachLabel('deployment_specifications_labels', 'deployment_specification_id', $specificationId);
        $this->attachLabel('labels_workspace_templates', 'workspace_template_id', $templateId);
        $this->attachLabel('labels_workspaces', 'workspace_id', $this->workspaceIdFor($deploymentId));

        $this->insert('workspace_template_environment_variables', [
            'workspace_template_id' => $templateId,
            'name' => 'SWEPT',
            'value' => 'yes',
        ]);
    }

    /**
     * The resources that hang off nothing built above: podio, rbac, oauth and webhooks.
     */
    private function arrangeTheRestOfTheResources(int $imageId, int $workspaceId, int $templateId): void {
        Fixtures::containerImageScan(['container_image_id' => $imageId]);
        Fixtures::containerImageScanRecord(['container_image_id' => $imageId]);

        $podioIntegration = Fixtures::podioIntegration();
        $fieldReference = Fixtures::podioFieldReference(['podio_integration_id' => $podioIntegration->id]);
        $postUpdateAction = Fixtures::postUpdateAction([
            'name' => 'sweep-action',
            'podio_add_comment_integration_id' => $podioIntegration->id,
            'podio_field_update_field_reference_id' => $fieldReference->id,
        ]);
        Fixtures::postUpdateActionCondition([
            'post_update_action_id' => $postUpdateAction->id,
            'podio_field_reference_id' => $fieldReference->id,
        ]);
        $this->insert('deployment_specification_post_update_actions', [
            'deployment_specification_id' => $this->firstIdOf('deployment_specifications'),
            'post_update_action_id' => $postUpdateAction->id,
            'position' => 0,
        ]);

        $role = $this->insert('rbac_roles', ['identifier' => 'sweep', 'name' => 'Sweep', 'description' => 'swept']);
        $permission = $this->insert('rbac_permissions', ['name' => 'sweep.read', 'description' => 'swept']);
        $this->insert('rbac_permissions_rbac_roles', ['rbac_permission_id' => $permission, 'rbac_role_id' => $role]);
        $this->insert('rbac_roles_users', ['rbac_role_id' => $role, 'user_id' => $this->signedInUserId()]);

        $this->db->table('oauth_clients')->insert([
            'client_id' => 'sweep-include-client',
            'client_secret' => '',
            'redirect_uri' => '',
            'grant_types' => 'client_credentials',
            'scope' => '',
            'user_id' => (string) $this->signedInUserId(),
        ]);

        $webhook = $this->insert('webhooks', [
            'type' => \WebHookTypes::All()[0],
            'name' => 'sweep-hook',
            'url' => 'https://hook.test/sweep',
            'content_type' => 'application/json',
            'auth_bearer_token' => '',
            'http_method' => 'POST',
            'created' => date('Y-m-d H:i:s'),
        ]);
        $this->insert('webhook_deliveries', [
            'webhook_id' => $webhook,
            'url' => 'https://hook.test/sweep',
            'method' => 'POST',
            'content_type' => 'application/json',
            'auth_bearer_token' => '',
            'payload' => '{}',
            'response_code' => 200,
            'created' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * A label row and the junction row that hangs it off one owner.
     */
    private function attachLabel(string $joinTable, string $ownerColumn, int $ownerId): void {
        $labelId = $this->insert('labels', ['name' => 'swept', 'value' => 'yes']);

        $this->insert($joinTable, ['label_id' => $labelId, $ownerColumn => $ownerId]);
    }

    private function workspaceIdFor(int $deploymentId): int {
        return (int) $this->db->table('deployments')
            ->select('workspace_id')
            ->where('id', $deploymentId)
            ->get()
            ->getRowArray()['workspace_id'];
    }

    private function firstIdOf(string $table): int {
        return (int) $this->db->table($table)
            ->select('id')
            ->orderBy('id', 'desc')
            ->limit(1)
            ->get()
            ->getRowArray()['id'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insert(string $table, array $row): int {
        $this->db->table($table)->insert($row);

        return (int) $this->db->insertID();
    }

    // </editor-fold>

    // <editor-fold desc="Reading responses">

    /**
     * Whether any row in the response actually carries the relation that was asked for.
     *
     * A has-many is a list under the plural name and counts when it is non-empty; a has-one
     * is an object under the singular name and counts when it has an id. Both are absent
     * rather than null when nothing was joined.
     *
     * @param array<int, array<string, mixed>> $resources
     */
    private function anyRowCarries(array $resources, string $property, int $type): bool {
        $field = $type === RelationDef::HasMany ? plural($property) : $property;

        foreach ($resources as $row) {
            $value = $row[$field] ?? null;

            if ($type === RelationDef::HasMany && is_array($value) && $value !== []) {
                return true;
            }
            if ($type !== RelationDef::HasMany && is_array($value) && ($value['id'] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true) ?? [];
    }

    // </editor-fold>

}
