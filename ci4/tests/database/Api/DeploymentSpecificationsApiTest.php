<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\DeploymentSpecification;
use App\Fixtures;
use App\Models\DeploymentSpecificationRoleRuleModel;
use App\Models\DeploymentSpecificationServicePortModel;
use App\Tests\Fakes\FakeIntegrations;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The DeploymentSpecifications endpoints - sixteen of them, one per child collection.
 *
 * A specification is mostly lists: ports, ingresses, role rules, annotations, volumes,
 * cron jobs. Each has its own endpoint and each behaves the same way, which is the part
 * worth holding: **the list that arrives replaces the list that is there.** Nothing is
 * merged, nothing is matched by id, and an empty list is a valid instruction to delete
 * everything. The dialogs send the whole collection every time, so that is the contract -
 * but it means a client bug costs the whole set, silently.
 *
 * The wire format is not the storage format either. The body uses camelCase names that
 * do not exist as columns, and the controller maps them by hand.
 */
class DeploymentSpecificationsApiTest extends ControllerTestCase {

    public function tearDown(): void {
        FakeIntegrations::uninstall();

        parent::tearDown();
    }


    /**
     * `apiGroup` on the wire, `api_group` in the table. The mapping is hand-written in the
     * controller, so nothing keeps the two in step but a test.
     */
    public function testRoleRulesArriveInCamelCaseAndAreStoredAsColumns(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/role-rules", [
            ['apiGroup' => 'apps', 'resource' => 'deployments', 'verbs' => 'get,list'],
        ]);

        $rules = $this->roleRules($specification);

        $this->assertCount(1, $rules);
        $this->assertSame('apps', $rules[0]->api_group);
        $this->assertSame('deployments', $rules[0]->resource);
        $this->assertSame('get,list', $rules[0]->verbs);
    }

    /**
     * Verbs stay one comma-separated string all the way to the database. They are only
     * split when the manifest is built - where nothing trims them, so `get, list` gives
     * Kubernetes a verb with a leading space.
     */
    public function testVerbsAreStoredAsTheSingleStringTheyArrivedAs(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/role-rules", [
            ['apiGroup' => '', 'resource' => 'pods', 'verbs' => 'get, list, watch'],
        ]);

        $this->assertSame('get, list, watch', $this->roleRules($specification)[0]->verbs);
    }

    public function testASecondCallReplacesTheRulesRatherThanAddingToThem(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/role-rules", [
            ['apiGroup' => '', 'resource' => 'pods', 'verbs' => 'get'],
            ['apiGroup' => '', 'resource' => 'secrets', 'verbs' => 'get'],
        ]);
        $this->assertCount(2, $this->roleRules($specification));

        $this->putValues("deployment-specifications/{$specification->id}/role-rules", [
            ['apiGroup' => '', 'resource' => 'configmaps', 'verbs' => 'list'],
        ]);

        $rules = $this->roleRules($specification);
        $this->assertCount(1, $rules);
        $this->assertSame('configmaps', $rules[0]->resource);
    }

    /**
     * The end of the same rule. An empty list is not "no change" - it is "remove them all",
     * and the answer is still OK.
     */
    public function testAnEmptyListRemovesEveryRule(): void {
        $specification = Fixtures::deploymentSpecification();
        $this->putValues("deployment-specifications/{$specification->id}/role-rules", [
            ['apiGroup' => '', 'resource' => 'pods', 'verbs' => 'get'],
        ]);

        $body = $this->putValues("deployment-specifications/{$specification->id}/role-rules", []);

        $this->assertSame('OK', $body['status']);
        $this->assertCount(0, $this->roleRules($specification));
    }

    /**
     * Cluster role rules are a separate collection with an endpoint of its own. A rule sent
     * to one must not turn up in the other - one is scoped to a namespace, the other to the
     * whole cluster.
     */
    public function testClusterRoleRulesAreASeparateCollection(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/role-rules", [
            ['apiGroup' => '', 'resource' => 'namespaced-only', 'verbs' => 'get'],
        ]);
        $this->putValues("deployment-specifications/{$specification->id}/cluster-role-rules", [
            ['apiGroup' => '', 'resource' => 'cluster-only', 'verbs' => 'list'],
        ]);

        $this->assertSame('namespaced-only', $this->roleRules($specification)[0]->resource);

        $clusterRules = (new \App\Models\DeploymentSpecificationClusterRoleRuleModel())
            ->where('deployment_specification_id', $specification->id)
            ->find();
        $this->assertSame('cluster-only', $clusterRules->all[0]->resource);
    }

    /**
     * `targetPort` is optional on the wire and falls back to `port`, which is what the
     * dialog relies on when the two are the same.
     */
    public function testAServicePortWithoutATargetPortTargetsItself(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/service-ports", [
            ['protocol' => 'TCP', 'name' => 'http', 'port' => 8080],
        ]);

        $ports = (new DeploymentSpecificationServicePortModel())
            ->where('deployment_specification_id', $specification->id)
            ->find();

        $this->assertSame(8080, (int) $ports->all[0]->port);
        $this->assertSame(8080, (int) $ports->all[0]->target_port);
    }

    public function testAServicePortKeepsAnExplicitTargetPort(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/service-ports", [
            ['protocol' => 'TCP', 'name' => 'http', 'port' => 80, 'targetPort' => 8080],
        ]);

        $ports = (new DeploymentSpecificationServicePortModel())
            ->where('deployment_specification_id', $specification->id)
            ->find();

        $this->assertSame(80, (int) $ports->all[0]->port);
        $this->assertSame(8080, (int) $ports->all[0]->target_port);
    }

    /**
     * Same as on the deployments controller: an unknown id is answered with OK, and
     * nothing is written.
     */
    public function testAnUnknownSpecificationIsRefusedAndWritesNothing(): void {
        $body = $this->putValues('deployment-specifications/999999/role-rules', [
            ['apiGroup' => '', 'resource' => 'pods', 'verbs' => 'get'],
        ]);

        $this->assertSame('unknown deployment specification', $body['error'] ?? null);
        $this->assertSame(
            0,
            (new DeploymentSpecificationRoleRuleModel())->where('resource', 'pods')->find()->count()
        );
    }


    // <editor-fold desc="Order is the only thing that carries position">

    /**
     * Three of these endpoints take a plain list of ids and derive `position` from where
     * each id sits in the array. Nothing on the wire says what the order is - the array
     * *is* the order - so a client that reshuffles the list is reordering the containers,
     * and one that sends a set rather than a sequence has silently reordered them too.
     */
    #[DataProvider('theCollectionsOrderedByArrayPosition')]
    public function testPositionIsTakenFromTheOrderOfTheList(string $endpoint, string $table, string $column): void {
        $specification = Fixtures::deploymentSpecification();
        $ids = [$this->rowId($endpoint, 'third'), $this->rowId($endpoint, 'first'), $this->rowId($endpoint, 'second')];

        $this->putValues("deployment-specifications/{$specification->id}/{$endpoint}", $ids);

        $rows = db_connect()->table($table)
            ->where('deployment_specification_id', $specification->id)
            ->orderBy('position', 'asc')
            ->get()->getResultArray();
        $this->assertSame($ids, array_map('intval', array_column($rows, $column)));
        $this->assertSame([0, 1, 2], array_map('intval', array_column($rows, 'position')));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function theCollectionsOrderedByArrayPosition(): array {
        return [
            'cron jobs' => ['cron-jobs', 'deployment_specification_cron_jobs', 'k8s_cron_job_id'],
            'post update actions' => [
                'post-update-actions',
                'deployment_specification_post_update_actions',
                'post_update_action_id',
            ],
        ];
    }

    /**
     * Init containers are the exception: the position is sent rather than taken from where
     * the entry sits in the list. That is deliberate - the dialog sends it - but the call
     * used to be written exactly like the two above, `array_keys($body->values)` and all,
     * with a closure that took one parameter and dropped the keys. Two endpoints that looked
     * identical answered differently. The keys are gone now; the behaviour is unchanged.
     */
    public function testAnInitContainerTakesItsPositionFromTheBodyRatherThanTheOrder(): void {
        $specification = Fixtures::deploymentSpecification();
        $first = Fixtures::initContainer(['name' => 'wait-for-db']);
        $second = Fixtures::initContainer(['name' => 'migrate']);

        // `second` is sent first, and says it belongs last.
        $this->putValues("deployment-specifications/{$specification->id}/init-containers", [
            ['initContainerId' => $second->id, 'position' => 99, 'includeInMigrationJob' => true],
            ['initContainerId' => $first->id, 'position' => 0, 'includeInMigrationJob' => false],
        ]);

        $rows = db_connect()->table('deployment_specification_init_containers')
            ->where('deployment_specification_id', $specification->id)
            ->orderBy('position', 'asc')
            ->get()->getResultArray();
        $this->assertSame(
            [(int) $first->id, (int) $second->id],
            array_map('intval', array_column($rows, 'init_container_id')),
            'the body decided, not the order'
        );
        $this->assertSame([0, 99], array_map('intval', array_column($rows, 'position')));
        $this->assertSame(1, (int) $rows[1]['include_in_migration_job']);
    }

    // </editor-fold>

    // <editor-fold desc="The rest of the collections">

    /**
     * An ingress is the one collection with children of its own - paths and annotations -
     * and they are written in the same call.
     */
    public function testAnIngressCarriesItsPathsAndAnnotations(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/ingresses", [[
            'ingressClass' => 'nginx',
            'proxyBodySize' => 16,
            'proxyConnectTimeout' => 30,
            'proxyReadTimeout' => 31,
            'proxySendTimeout' => 32,
            'sslRedirect' => true,
            'enableTls' => true,
            'paths' => [['path' => '/api', 'pathType' => 'Prefix', 'backendServicePortName' => 'http']],
            'annotations' => [['name' => 'nginx.ingress.kubernetes.io/whitelist-source-range', 'value' => '10.0.0.0/8']],
        ]]);

        $ingress = db_connect()->table('deployment_specification_ingresses')
            ->where('deployment_specification_id', $specification->id)
            ->get()->getRowArray();
        $this->assertSame('nginx', $ingress['ingress_class']);
        $this->assertSame(16, (int) $ingress['proxy_body_size']);

        $paths = db_connect()->table('deployment_specification_ingress_rule_paths')
            ->where('deployment_specification_ingress_id', $ingress['id'])
            ->get()->getResultArray();
        $this->assertSame('/api', $paths[0]['path']);
        $this->assertSame('http', $paths[0]['backend_service_port_name']);
    }

    /**
     * Everything but the path and the port is optional on a route, and a body that leaves
     * them out has to mean "not set" rather than an empty string - the Contour step checks
     * `strlen()` on each before it writes a timeout policy.
     */
    public function testAnHttpProxyRouteWithoutTimeoutsLeavesThemUnset(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/http-proxy-routes", [
            ['path' => '/', 'port' => 80],
        ]);

        $route = db_connect()->table('deployment_specification_http_proxy_routes')
            ->where('deployment_specification_id', $specification->id)
            ->get()->getRowArray();
        $this->assertSame('/', $route['path']);
        $this->assertSame(80, (int) $route['port']);
        $this->assertSame('', (string) $route['timeout_policy_idle']);
        $this->assertSame('', (string) $route['protocol']);
    }

    public function testEnvironmentVariablesAreStoredAsNameAndValue(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/environment-variables", [
            ['name' => 'LOG_LEVEL', 'value' => 'debug'],
        ]);

        $row = db_connect()->table('deployment_specification_environment_variables')
            ->where('deployment_specification_id', $specification->id)
            ->get()->getRowArray();
        $this->assertSame('LOG_LEVEL', $row['name']);
        $this->assertSame('debug', $row['value']);
    }

    /**
     * Volumes take the column names straight, where every other collection maps a camelCase
     * body onto them by hand. The declared shape is still this one - it is what the dialog
     * sends - but a client that follows the pattern used by everything else is read too,
     * instead of hitting an undefined property and a 500.
     */
    public function testVolumesTakeTheirFieldsInSnakeCase(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/volumes", [[
            'type' => 'nfs',
            'mount_path' => '/data',
            'sub_path' => 'tenant',
            'capacity' => 10,
            'volume_mode' => 'Filesystem',
            'reclaim_policy' => 'Retain',
            'nfs_server' => '10.0.0.1',
            'nfs_path' => '/exports',
            'storage_class' => 'nfs',
            'csi_driver' => '',
            'csi_volume_handle' => '',
        ]]);

        $row = db_connect()->table('deployment_specification_volumes')
            ->where('deployment_specification_id', $specification->id)
            ->get()->getRowArray();
        $this->assertSame('/data', $row['mount_path']);
        $this->assertSame('tenant', $row['sub_path']);
        $this->assertSame('Retain', $row['reclaim_policy']);
    }

    public function testVolumesAreAlsoReadInCamelCase(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/volumes", [[
            'type' => 'nfs',
            'mountPath' => '/data',
            'subPath' => 'tenant',
            'capacity' => 10,
            'volumeMode' => 'Filesystem',
            'reclaimPolicy' => 'Retain',
            'nfsServer' => '10.0.0.1',
            'nfsPath' => '/exports',
            'storageClass' => 'nfs',
            'csiDriver' => '',
            'csiVolumeHandle' => '',
        ]]);

        $row = db_connect()->table('deployment_specification_volumes')
            ->where('deployment_specification_id', $specification->id)
            ->get()->getRowArray();
        $this->assertSame('/data', $row['mount_path']);
        $this->assertSame('tenant', $row['sub_path']);
        $this->assertSame('Retain', $row['reclaim_policy']);
    }

    public function testServiceAnnotationsAreStoredAsNameAndValue(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/service-annotations", [
            ['name' => 'cloud.google.com/neg', 'value' => '{"ingress": true}'],
        ]);

        $row = db_connect()->table('deployment_specification_service_annotations')
            ->where('deployment_specification_id', $specification->id)
            ->get()->getRowArray();
        $this->assertSame('cloud.google.com/neg', $row['name']);
        $this->assertSame('{"ingress": true}', $row['value']);
    }

    /**
     * A deployment annotation carries a third field the service one does not: `level`
     * decides whether it lands on the Deployment object or on the pod template. The two
     * end up in different places in the manifest, so getting the field order wrong here
     * would annotate the wrong object rather than fail.
     */
    public function testADeploymentAnnotationCarriesTheLevelItArrivedWith(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/deployment-annotations", [
            ['level' => \DeploymentAnnotationLevels::Pod, 'name' => 'prometheus.io/scrape', 'value' => 'true'],
            ['level' => \DeploymentAnnotationLevels::Deployment, 'name' => 'kubernetes.io/change-cause', 'value' => 'release'],
        ]);

        $rows = db_connect()->table('deployment_specification_deployment_annotations')
            ->where('deployment_specification_id', $specification->id)
            ->orderBy('id', 'asc')
            ->get()->getResultArray();

        $this->assertSame(\DeploymentAnnotationLevels::Pod, $rows[0]['level']);
        $this->assertSame('prometheus.io/scrape', $rows[0]['name']);
        $this->assertSame('true', $rows[0]['value']);
        $this->assertSame(\DeploymentAnnotationLevels::Deployment, $rows[1]['level']);
        $this->assertSame('kubernetes.io/change-cause', $rows[1]['name']);
    }

    /**
     * Labels are shared rows joined to the specification through a junction table rather
     * than columns on a table of its own, so "replace the set" means replacing the
     * junction. The label row itself is left behind - whoever else points at it keeps it.
     */
    public function testLabelsReplaceTheWholeSet(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/labels", [
            ['name' => 'team', 'value' => 'platform'],
            ['name' => 'tier', 'value' => 'backend'],
        ]);
        $this->assertCount(2, $this->labelsOn($specification));

        $this->putValues("deployment-specifications/{$specification->id}/labels", [
            ['name' => 'team', 'value' => 'infra'],
        ]);

        $rows = $this->labelsOn($specification);
        $this->assertCount(1, $rows);
        $this->assertSame('team', $rows[0]['name']);
        $this->assertSame('infra', $rows[0]['value']);
    }

    public function testQuickCommandsAreStoredAsNameAndCommand(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/quick-commands", [
            ['name' => 'Clear cache', 'command' => 'php spark cache:clear'],
        ]);

        $row = db_connect()->table('deployment_specification_quick_commands')
            ->where('deployment_specification_id', $specification->id)
            ->get()->getRowArray();
        $this->assertSame('Clear cache', $row['name']);
        $this->assertSame('php spark cache:clear', $row['command']);
    }

    public function testPostCommandsCarryTheContainerAndWhetherEveryPodRunsThem(): void {
        $specification = Fixtures::deploymentSpecification();

        $this->putValues("deployment-specifications/{$specification->id}/post-commands", [
            ['name' => 'Migrate', 'command' => 'php spark migrate', 'allPods' => false, 'container' => 'api'],
        ]);

        $row = db_connect()->table('deployment_specification_post_commands')
            ->where('deployment_specification_id', $specification->id)
            ->get()->getRowArray();
        $this->assertSame('api', $row['container']);
        $this->assertSame(0, (int) $row['all_pods']);
    }

    // </editor-fold>

    // <editor-fold desc="Tags">

    /**
     * The tag list a specification offers comes from the registry the image points at, and
     * the endpoint reaches out over the network to get it. The fake stands in, which is the
     * whole reason this endpoint can be tested at all.
     */
    public function testTheTagListComesFromTheRegistry(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = ['1.0.0', '1.1.0'];
        $fakes->tagPushTimes = ['1.1.0' => '2026-09-01T10:30:00Z'];
        $image = Fixtures::containerImage(['container_registry_id' => Fixtures::containerRegistry()->id]);
        $specification = Fixtures::deploymentSpecification(['container_image_id' => $image->id]);

        $body = $this->getJson("deployment-specifications/{$specification->id}/tags");

        $this->assertSame(['1.0.0', '1.1.0'], $body['resource']['tags']);
        $this->assertSame([
            ['name' => '1.0.0', 'pushed_at' => null],
            ['name' => '1.1.0', 'pushed_at' => '2026-09-01T10:30:00Z'],
        ], $body['resource']['tag_details']);
    }

    /**
     * The version dialogs wait for a list and cannot show a failure, so this keeps answering
     * one. `/container-images/{id}/tags` is where the reason is shown.
     */
    public function testARegistryThatRefusesStillGivesTheVersionDialogsAList(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = [];
        $fakes->failTagsWith = new \Exception('Harbor answered 401: unauthorized');
        $image = Fixtures::containerImage(['container_registry_id' => Fixtures::containerRegistry()->id]);
        $specification = Fixtures::deploymentSpecification(['container_image_id' => $image->id]);

        $body = $this->getJson("deployment-specifications/{$specification->id}/tags");

        $this->assertSame('OK', $body['status']);
        $this->assertSame([], $body['resource']['tags']);
    }

    // </editor-fold>

    // <editor-fold desc="One volume per deployment">

    /**
     * Every deployment on the specification mounts its volumes, and a deployment mounts one.
     */
    public function testASecondVolumeIsRefused(): void {
        $specification = Fixtures::deploymentSpecification();

        $body = $this->putValues("deployment-specifications/{$specification->id}/volumes", [$this->volume(), $this->volume()]);

        $this->assertSame('A specification can have one volume', $body['error'] ?? null);
        $this->assertSame(0, db_connect()->table('deployment_specification_volumes')->where('deployment_specification_id', $specification->id)->countAllResults());
    }

    /**
     * The deployments that would end up with two are named, so the operator knows where
     * to look.
     */
    public function testAVolumeIsRefusedWhileADeploymentHasOneOfItsOwn(): void {
        $deployment = Fixtures::deployableDeployment(['name' => 'with-a-disk']);
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id]);

        $body = $this->putValues("deployment-specifications/{$deployment->deployment_specification_id}/volumes", [$this->volume()]);

        $this->assertSame('These deployments have a volume of their own: with-a-disk', $body['error'] ?? null);
    }

    public function testARemovedDeploymentVolumeDoesNotCount(): void {
        $deployment = Fixtures::deployableDeployment();
        $removed = Fixtures::deploymentVolume(['deployment_id' => $deployment->id]);
        db_connect()->table('deployment_volumes')->where('id', $removed->id)->update(['deletion_id' => 1]);

        $body = $this->putValues("deployment-specifications/{$deployment->deployment_specification_id}/volumes", [$this->volume()]);

        $this->assertSame('OK', $body['status']);
    }

    // </editor-fold>

    // <editor-fold desc="The image a specification runs">

    /**
     * What the edit dialog sends: the row as it was loaded by id, relations included, with
     * the image picker changed. The id is what was edited, so the id wins, and the image the
     * dialog was holding is not written back.
     */
    public function testChangingTheImageIsNotUndoneByTheImageTheDialogWasHolding(): void {
        $old = Fixtures::containerImage(['name' => 'old-image']);
        $new = Fixtures::containerImage(['name' => 'new-image']);
        $spec = Fixtures::deploymentSpecification(['container_image_id' => $old->id]);

        $this->withBodyFormat('json')->signedIn()->patch("deployment_specifications/{$spec->id}", [
            'name' => $spec->name,
            'container_image_id' => $new->id,
            'container_image' => ['id' => $old->id, 'name' => 'renamed-by-a-stale-copy', 'url' => $old->url],
        ]);

        $this->assertSame((int) $new->id, (int) $this->row('deployment_specifications', $spec->id)['container_image_id']);
        $this->assertSame('old-image', $this->row('container_images', $old->id)['name']);
    }

    /**
     * The same for the migration image, the second relation to the same table.
     */
    public function testChangingTheMigrationImageIsNotUndoneEither(): void {
        $old = Fixtures::containerImage(['name' => 'old-image']);
        $new = Fixtures::containerImage(['name' => 'new-image']);
        $spec = Fixtures::deploymentSpecification(['database_migration_container_image_id' => $old->id]);

        $this->withBodyFormat('json')->signedIn()->patch("deployment_specifications/{$spec->id}", [
            'database_migration_container_image_id' => $new->id,
            'database_migration_container_image' => ['id' => $old->id, 'name' => 'renamed-by-a-stale-copy'],
        ]);

        $this->assertSame((int) $new->id, (int) $this->row('deployment_specifications', $spec->id)['database_migration_container_image_id']);
        $this->assertSame('old-image', $this->row('container_images', $old->id)['name']);
    }

    // </editor-fold>

    /**
     * @return array<string, mixed>
     */
    private function row(string $table, int|string $id): array {
        return $this->db->table($table)->where('id', $id)->get()->getRowArray();
    }

    // <editor-fold desc="Helpers">

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


    /**
     * @return array<string, mixed> the decoded response
     */
    private function getJson(string $path): array {
        $response = $this->signedIn()->get($path);

        return json_decode((string) $response->response()->getBody(), true);
    }

    /**
     * A row for one of the id-list endpoints to point at.
     */
    private function rowId(string $endpoint, string $name): int {
        return match ($endpoint) {
            'cron-jobs' => Fixtures::cronJob([
                'name' => $name,
                'container_image_id' => Fixtures::containerImage(['name' => $name])->id,
            ])->id,
            'post-update-actions' => Fixtures::postUpdateAction(['type' => \PostUpdateActionTypes::Podio_AddComment])->id,
        };
    }

    /**
     * @param array<array<string, mixed>> $values
     * @return array<string, mixed> the decoded response
     */
    private function putValues(string $path, array $values): array {
        $response = $this->withBodyFormat('json')->signedIn()->put($path, ['values' => $values]);

        return json_decode((string) $response->response()->getBody(), true);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function labelsOn(DeploymentSpecification $specification): array {
        return db_connect()->table('deployment_specifications_labels')
            ->select('labels.name, labels.value')
            ->join('labels', 'labels.id = deployment_specifications_labels.label_id')
            ->where('deployment_specifications_labels.deployment_specification_id', $specification->id)
            ->orderBy('labels.name', 'asc')
            ->get()->getResultArray();
    }

    /**
     * @return \App\Entities\DeploymentSpecificationRoleRule[]
     */
    private function roleRules(DeploymentSpecification $specification): array {
        return (new DeploymentSpecificationRoleRuleModel())
            ->where('deployment_specification_id', $specification->id)
            ->orderBy('id', 'asc')
            ->find()
            ->all ?? [];
    }

    // </editor-fold>

}
