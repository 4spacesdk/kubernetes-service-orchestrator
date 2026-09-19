<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use RestExtension\Exceptions\UnauthorizedException;

/**
 * A copy of a deployment specification or a deployment package, made on the server.
 *
 * A specification is mostly child collections, edited in dialogs of their own that need a
 * saved row to write to - which is why this cannot be done in the browser, as the copy
 * button on the flat lists is.
 *
 * Two things are held here. **Every column of every row comes along**, which the tests check
 * by filling each column from its type rather than by naming fields, so a column added
 * later is covered without anyone remembering to. And **the original keeps its rows**: a
 * child copied by re-pointing its foreign key would move from the original to the copy,
 * which is what copying a gateway in the browser nearly did to its addresses.
 *
 * The last test in each half is a guard: every table that belongs to a specification or a
 * package has to be listed here as copied or as deliberately not, so a new child collection
 * cannot be left out of a copy by accident.
 */
class DuplicateApiTest extends ControllerTestCase {

    /**
     * The rows a specification owns outright, keyed on `deployment_specification_id`. The
     * init containers, post update actions and cron jobs are links to shared entities, and
     * the link is the row.
     */
    private const SpecificationCollections = [
        'deployment_specification_post_commands',
        'deployment_specification_environment_variables',
        'deployment_specification_service_ports',
        'deployment_specification_cluster_role_rules',
        'deployment_specification_role_rules',
        'deployment_specification_service_annotations',
        'deployment_specification_deployment_annotations',
        'deployment_specification_quick_commands',
        'deployment_specification_init_containers',
        'deployment_specification_post_update_actions',
        'deployment_specification_cron_jobs',
        'deployment_specification_http_proxy_routes',
        'deployment_specification_volumes',
    ];

    /**
     * Columns that are the row's identity, not its content, and never compared.
     */
    private const Identity = ['id', 'created', 'updated', 'created_by_id', 'updated_by_id', 'deletion_id'];

    // <editor-fold desc="Specifications">

    public function testTheCopyIsNamedAsACopyAndKeepsEveryOtherColumn(): void {
        $original = Fixtures::deploymentSpecification(['name' => 'Backend']);
        $this->fillEveryColumn('deployment_specifications', $original->id, except: ['name']);

        $copy = $this->duplicate("deployment-specifications/{$original->id}");

        $this->assertSame('Backend (copy)', $copy['name']);
        $this->assertNotSame($original->id, $copy['id']);
        $this->assertSame(
            $this->content('deployment_specifications', 'id', $original->id, ['name']),
            $this->content('deployment_specifications', 'id', $copy['id'], ['name']),
        );
    }

    #[DataProvider('specificationCollections')]
    public function testARowIsCopiedAndTheOriginalKeepsIt(string $table): void {
        $original = Fixtures::deploymentSpecification();
        $this->insertFilledRow($table, ['deployment_specification_id' => $original->id]);
        $before = $this->content($table, 'deployment_specification_id', $original->id);

        $copy = $this->duplicate("deployment-specifications/{$original->id}");

        $this->assertSame($before, $this->content($table, 'deployment_specification_id', $original->id), 'the original lost its row');
        $this->assertSame($before, $this->content($table, 'deployment_specification_id', $copy['id']));
    }

    public static function specificationCollections(): array {
        return array_combine(self::SpecificationCollections, array_map(fn ($table) => [$table], self::SpecificationCollections));
    }

    /**
     * An ingress is the one child with children of its own, so it is copied a level deeper.
     */
    public function testAnIngressIsCopiedWithItsPathsAndAnnotations(): void {
        $original = Fixtures::deploymentSpecification();
        $ingress = Fixtures::ingress(['deployment_specification_id' => $original->id]);
        $this->insertFilledRow('deployment_specification_ingress_rule_paths', ['deployment_specification_ingress_id' => $ingress->id]);
        $this->insertFilledRow('deployment_specification_ingress_annotations', ['deployment_specification_ingress_id' => $ingress->id]);

        $copy = $this->duplicate("deployment-specifications/{$original->id}");

        $copiedIngressId = (int) $this->db->table('deployment_specification_ingresses')
            ->where('deployment_specification_id', $copy['id'])->get()->getRow()->id;
        $this->assertNotSame($ingress->id, $copiedIngressId);
        $this->assertSame(
            $this->content('deployment_specification_ingresses', 'deployment_specification_id', $original->id),
            $this->content('deployment_specification_ingresses', 'deployment_specification_id', $copy['id']),
        );
        foreach (['deployment_specification_ingress_rule_paths', 'deployment_specification_ingress_annotations'] as $table) {
            $this->assertSame(
                $this->content($table, 'deployment_specification_ingress_id', $ingress->id),
                $this->content($table, 'deployment_specification_ingress_id', $copiedIngressId),
                $table
            );
            $this->assertCount(1, $this->content($table, 'deployment_specification_ingress_id', $ingress->id), "{$table} left the original");
        }
    }

    /**
     * Setting the copy's labels deletes the copy's label rows, not only the links. Shared
     * rows would take the original's labels with them.
     */
    public function testTheCopyHasLabelsOfItsOwn(): void {
        $original = Fixtures::deploymentSpecification();
        $this->putValues("deployment-specifications/{$original->id}/labels", [['name' => 'team', 'value' => 'core']]);

        $copy = $this->duplicate("deployment-specifications/{$original->id}");
        $this->assertSame([['team', 'core']], $this->labels('deployment_specifications_labels', 'deployment_specification_id', $copy['id']));

        $this->putValues("deployment-specifications/{$copy['id']}/labels", [['name' => 'team', 'value' => 'other']]);

        $this->assertSame([['team', 'core']], $this->labels('deployment_specifications_labels', 'deployment_specification_id', $original->id));
    }

    /**
     * A copy is a new recipe. The deployments made from the original stay the original's,
     * and the copy is in no package until someone puts it in one.
     */
    public function testNeitherTheDeploymentsNorThePackagesComeAlong(): void {
        $original = Fixtures::deploymentSpecification();
        Fixtures::deployment(['deployment_specification_id' => $original->id]);
        $package = Fixtures::deploymentPackage();
        Fixtures::packageSpecification(['deployment_package_id' => $package->id, 'deployment_specification_id' => $original->id]);

        $copy = $this->duplicate("deployment-specifications/{$original->id}");

        $this->assertSame(0, $this->db->table('deployments')->where('deployment_specification_id', $copy['id'])->countAllResults());
        $this->assertSame(0, $this->db->table('deployment_package_deployment_specifications')->where('deployment_specification_id', $copy['id'])->countAllResults());
    }

    public function testAnUnknownSpecificationIsRefusedAndNothingIsWritten(): void {
        $before = $this->db->table('deployment_specifications')->countAllResults();

        $body = $this->decode($this->signedIn()->post('deployment-specifications/999999/duplicate'));

        $this->assertNotSame('OK', $body['status']);
        $this->assertSame($before, $this->db->table('deployment_specifications')->countAllResults());
    }

    public function testSignedOutIsRefused(): void {
        $original = Fixtures::deploymentSpecification();

        $this->expectException(UnauthorizedException::class);
        $this->post("deployment-specifications/{$original->id}/duplicate");
    }

    public function testEveryTableThatBelongsToASpecificationIsAccountedFor(): void {
        $this->assertSame(
            $this->sorted([
                ...self::SpecificationCollections,
                'deployment_specification_ingresses',   // copied, with its children
                'deployment_specifications_labels',     // copied as new labels
                'deployments',                          // not copied, see above
                'deployment_package_deployment_specifications', // not copied, see above
                'k8s_cron_jobs',                        // an unused column from before the link table - never written
            ]),
            $this->tablesWithColumn('deployment_specification_id'),
            'A table was added or removed. Decide whether a copy of a specification should carry it, '
            . 'then update DeploymentSpecification::duplicate() and this list.'
        );
    }

    // </editor-fold>

    // <editor-fold desc="Packages">

    public function testAPackageCopyIsNamedAsACopyWithANamespaceKubernetesAccepts(): void {
        $original = Fixtures::deploymentPackage(['name' => 'Klartboard', 'namespace' => 'klartboard']);

        $copy = $this->duplicate("deployment-packages/{$original->id}");

        $this->assertSame('Klartboard (copy)', $copy['name']);
        $this->assertSame('klartboard-copy', $copy['namespace']);
    }

    public function testAnEmptyNamespaceStaysEmpty(): void {
        $original = Fixtures::deploymentPackage(['namespace' => '']);

        $copy = $this->duplicate("deployment-packages/{$original->id}");

        $this->assertSame('', $copy['namespace']);
    }

    public function testThePackageKeepsEveryOtherColumn(): void {
        $original = Fixtures::deploymentPackage();
        $this->fillEveryColumn('deployment_packages', $original->id, except: ['name', 'namespace']);

        $copy = $this->duplicate("deployment-packages/{$original->id}");

        $this->assertSame(
            $this->content('deployment_packages', 'id', $original->id, ['name', 'namespace']),
            $this->content('deployment_packages', 'id', $copy['id'], ['name', 'namespace']),
        );
    }

    public function testEnvironmentVariablesAreCopiedAndTheOriginalKeepsThem(): void {
        $original = Fixtures::deploymentPackage();
        $this->insertFilledRow('deployment_package_environment_variables', ['deployment_package_id' => $original->id]);
        $before = $this->content('deployment_package_environment_variables', 'deployment_package_id', $original->id);

        $copy = $this->duplicate("deployment-packages/{$original->id}");

        $this->assertSame($before, $this->content('deployment_package_environment_variables', 'deployment_package_id', $original->id));
        $this->assertSame($before, $this->content('deployment_package_environment_variables', 'deployment_package_id', $copy['id']));
    }

    /**
     * The copy points at the same specifications, with the same defaults and the same
     * min scale schedules. None of those three is itself copied.
     */
    public function testTheSpecificationsAreSharedAndTheirDefaultsCopied(): void {
        $original = Fixtures::deploymentPackage();
        $specification = Fixtures::deploymentSpecification();
        $this->insertFilledRow('deployment_package_deployment_specifications', [
            'deployment_package_id' => $original->id,
            'deployment_specification_id' => $specification->id,
        ]);
        $row = $this->db->table('deployment_package_deployment_specifications')->where('deployment_package_id', $original->id)->get()->getRow();
        $schedule = Fixtures::minScaleSchedule();
        $this->db->table('deployment_package_ds_knative_min_scale_schedules')->insert([
            'deployment_package_deployment_specification_id' => $row->id,
            'knative_min_scale_schedule_id' => $schedule->id,
        ]);
        $specifications = $this->db->table('deployment_specifications')->countAllResults();
        $schedules = $this->db->table('knative_min_scale_schedules')->countAllResults();

        $copy = $this->duplicate("deployment-packages/{$original->id}");

        $this->assertSame(
            $this->content('deployment_package_deployment_specifications', 'deployment_package_id', $original->id),
            $this->content('deployment_package_deployment_specifications', 'deployment_package_id', $copy['id']),
        );
        $copiedRow = $this->db->table('deployment_package_deployment_specifications')->where('deployment_package_id', $copy['id'])->get()->getRow();
        $this->assertSame(
            [(string) $schedule->id],
            array_column($this->db->table('deployment_package_ds_knative_min_scale_schedules')
                ->where('deployment_package_deployment_specification_id', $copiedRow->id)->get()->getResultArray(), 'knative_min_scale_schedule_id'),
        );
        $this->assertSame(1, $this->db->table('deployment_package_ds_knative_min_scale_schedules')
            ->where('deployment_package_deployment_specification_id', $row->id)->countAllResults(), 'the original lost its schedule');
        $this->assertSame($specifications, $this->db->table('deployment_specifications')->countAllResults(), 'no specification was copied');
        $this->assertSame($schedules, $this->db->table('knative_min_scale_schedules')->countAllResults(), 'no schedule was copied');
    }

    public function testThePackageCopyHasLabelsOfItsOwn(): void {
        $original = Fixtures::deploymentPackage();
        $this->putValues("deployment-packages/{$original->id}/labels", [['name' => 'tier', 'value' => 'gold']]);

        $copy = $this->duplicate("deployment-packages/{$original->id}");
        $this->putValues("deployment-packages/{$copy['id']}/labels", []);

        $this->assertSame([['tier', 'gold']], $this->labels('deployment_packages_labels', 'deployment_package_id', $original->id));
    }

    public function testTheWorkspacesMadeFromThePackageStayWithTheOriginal(): void {
        $original = Fixtures::deploymentPackage();
        Fixtures::workspace(['deployment_package_id' => $original->id]);

        $copy = $this->duplicate("deployment-packages/{$original->id}");

        $this->assertSame(0, $this->db->table('workspaces')->where('deployment_package_id', $copy['id'])->countAllResults());
    }

    public function testAnUnknownPackageIsRefused(): void {
        $body = $this->decode($this->signedIn()->post('deployment-packages/999999/duplicate'));

        $this->assertNotSame('OK', $body['status']);
    }

    public function testEveryTableThatBelongsToAPackageIsAccountedFor(): void {
        $this->assertSame(
            $this->sorted([
                'deployment_package_environment_variables',     // copied
                'deployment_package_deployment_specifications', // copied, pointing at the same specifications
                'deployment_packages_labels',                   // copied as new labels
                'workspaces',                                   // not copied
            ]),
            $this->tablesWithColumn('deployment_package_id'),
            'A table was added or removed. Decide whether a copy of a package should carry it, '
            . 'then update DeploymentPackage::duplicate() and this list.'
        );
    }

    // </editor-fold>

    // <editor-fold desc="Helpers">

    /**
     * @return array<string, mixed> The copy, as the endpoint answered it.
     */
    private function duplicate(string $path): array {
        $body = $this->decode($this->signedIn()->post("{$path}/duplicate"));
        $this->assertSame('OK', $body['status'], json_encode($body));

        return $body['resource'];
    }

    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    private function putValues(string $path, array $values): void {
        $body = $this->decode($this->signedIn()->withBodyFormat('json')->put($path, ['values' => $values]));
        $this->assertSame('OK', $body['status'], json_encode($body));
    }

    /**
     * @return list<array{string, string, ?int}> name, type, and the longest a string may be
     */
    private function columns(string $table): array {
        return array_map(
            fn ($column) => [$column->COLUMN_NAME, $column->DATA_TYPE, $column->CHARACTER_MAXIMUM_LENGTH],
            $this->db->query(
                'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position',
                [$table]
            )->getResult()
        );
    }

    /**
     * A value of the column's type that no default produces, so a column the copy leaves
     * out shows up as a difference.
     */
    private function valueFor(string $column, string $type, ?int $length): mixed {
        return match (true) {
            in_array($type, ['int', 'bigint', 'smallint', 'mediumint'], true) => 7,
            $type === 'tinyint' => 1,
            in_array($type, ['decimal', 'float', 'double'], true) => 7.5,
            in_array($type, ['datetime', 'timestamp'], true) => '2026-01-02 03:04:05',
            $type === 'date' => '2026-01-02',
            default => substr("x-{$column}", 0, $length ?? PHP_INT_MAX),
        };
    }

    private function insertFilledRow(string $table, array $values): void {
        foreach ($this->columns($table) as [$column, $type, $length]) {
            if (!in_array($column, self::Identity, true) && !array_key_exists($column, $values)) {
                $values[$column] = $this->valueFor($column, $type, $length);
            }
        }
        $this->db->table($table)->insert($values);
    }

    private function fillEveryColumn(string $table, int $id, array $except = []): void {
        $values = [];
        foreach ($this->columns($table) as [$column, $type, $length]) {
            if (!in_array($column, [...self::Identity, ...$except], true)) {
                $values[$column] = $this->valueFor($column, $type, $length);
            }
        }
        $this->db->table($table)->where('id', $id)->update($values);
    }

    /**
     * The rows under a parent, without what identifies them or points them at the parent.
     *
     * @return list<array<string, mixed>>
     */
    private function content(string $table, string $parentColumn, int $parentId, array $alsoIgnore = []): array {
        $ignore = [...self::Identity, $parentColumn, ...$alsoIgnore];
        if ($parentColumn !== 'id') {
            $ignore[] = 'id';
        }

        return array_map(
            fn (array $row) => array_diff_key($row, array_flip($ignore)),
            $this->db->table($table)->where($parentColumn, $parentId)->orderBy('id')->get()->getResultArray()
        );
    }

    private function labels(string $joinTable, string $ownerColumn, int $ownerId): array {
        return array_map(
            fn ($row) => [$row->name, $row->value],
            $this->db->table('labels')
                ->join($joinTable, "{$joinTable}.label_id = labels.id")
                ->where("{$joinTable}.{$ownerColumn}", $ownerId)
                ->get()->getResult()
        );
    }

    /**
     * @return list<string>
     */
    private function tablesWithColumn(string $column): array {
        return $this->sorted(array_column($this->db->query(
            'SELECT table_name AS name FROM information_schema.columns
             WHERE table_schema = DATABASE() AND column_name = ?',
            [$column]
        )->getResultArray(), 'name'));
    }

    private function sorted(array $values): array {
        sort($values);
        return $values;
    }

    // </editor-fold>

}
