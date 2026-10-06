<?php namespace App\Models;

use App\Libraries\ImageScanning\ImageScanner;
use App\Libraries\Kubernetes\SecurityAdvice;
use Config\Database;
use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class ContainerImageModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        ContainerRegistryModel::class,
        GithubIntegrationModel::class,
    ];

    public $hasMany = [
        DeploymentSpecificationModel::class,
        'deployment_specification_database_migration_container_image' => [
            'class' => DeploymentSpecificationModel::class,
            'otherField' => 'database_migration_container_image',
            'joinTable' => 'deployment_specifications',
            'joinSelfAs' => 'database_migration_container_image_id',
        ],
        InitContainerModel::class,
        K8sCronJobModel::class,
        ContainerImageScanModel::class,
        ContainerImageScanRecordModel::class,
    ];

    public function preRestGet($queryParser, $id) {

    }

    public function postRestGet($queryParser, $items) {
        $running = [];
        foreach (ImageScanner::runningDeployments() as $row) {
            $running[$row['container_image_id']][] = $row['deployment_id'];
        }
        // The newest scan of each image that says what its tag runs as, for the advice.
        $lastScans = [];
        $scans = Database::connect()->table('container_image_scans')
            ->select('container_image_id, tag, image_user, scanned_at')
            ->where('status', \ContainerImageScanStatuses::Scanned)
            ->where('image_user IS NOT NULL', null, false)
            ->orderBy('scanned_at', 'desc')
            ->get()->getResultArray();
        foreach ($scans as $scan) {
            $lastScans[(int) $scan['container_image_id']] ??= $scan;
        }

        $uses = self::SpecificationUses();

        foreach ($items as $image) {
            $image->running_deployment_ids = $running[(int) $image->id] ?? [];
            $image->specification_uses = json_encode(array_values($uses[(int) $image->id] ?? []));
            $image->security_advice = json_encode(SecurityAdvice::For($image, $lastScans[(int) $image->id] ?? null));
        }
    }

    /**
     * Which specifications use each image, and as what - the workload, the migration job, an init
     * container or a sidecar, a cron job. Four queries for every image on the page.
     *
     * @return array<int, array<int, array{id: int, name: string, roles: list<string>}>> image id => specification id => use
     */
    private static function SpecificationUses(): array {
        $db = Database::connect();
        $rows = [
            ...array_map(fn(array $row) => [...$row, 'role' => 'workload'], $db->table('deployment_specifications')
                ->select('id AS spec_id, name, container_image_id AS image_id')
                ->where('container_image_id >', 0)
                ->get()->getResultArray()),
            ...array_map(fn(array $row) => [...$row, 'role' => 'migration job'], $db->table('deployment_specifications')
                ->select('id AS spec_id, name, database_migration_container_image_id AS image_id')
                ->where('enable_database', 1)
                ->where('database_migration_container_image_id >', 0)
                ->get()->getResultArray()),
            ...array_map(fn(array $row) => [...$row, 'role' => $row['is_sidecar'] ? 'sidecar' : 'init container'], $db->table('deployment_specification_init_containers dsic')
                ->select('s.id AS spec_id, s.name, i.container_image_id AS image_id, i.is_sidecar')
                ->join('deployment_specifications s', 's.id = dsic.deployment_specification_id')
                ->join('init_containers i', 'i.id = dsic.init_container_id')
                ->get()->getResultArray()),
            ...array_map(fn(array $row) => [...$row, 'role' => 'cron job'], $db->table('deployment_specification_cron_jobs dscj')
                ->select('s.id AS spec_id, s.name, c.container_image_id AS image_id')
                ->join('deployment_specifications s', 's.id = dscj.deployment_specification_id')
                ->join('k8s_cron_jobs c', 'c.id = dscj.k8s_cron_job_id')
                ->get()->getResultArray()),
        ];

        $uses = [];
        foreach ($rows as $row) {
            $imageId = (int) $row['image_id'];
            $specId = (int) $row['spec_id'];
            $uses[$imageId][$specId] ??= ['id' => $specId, 'name' => (string) $row['name'], 'roles' => []];
            if (!in_array($row['role'], $uses[$imageId][$specId]['roles'], true)) {
                $uses[$imageId][$specId]['roles'][] = $row['role'];
            }
        }
        foreach ($uses as &$bySpec) {
            uasort($bySpec, fn(array $a, array $b) => strcasecmp($a['name'], $b['name']));
        }

        return $uses;
    }

    public function isRestCreationAllowed($item): bool {
        return true;
    }

    public function isRestUpdateAllowed($item): bool {
        return true;
    }

    public function isRestDeleteAllowed($item): bool {
        return true;
    }

    public function appleRestGetManyRelations($items) {

    }

    public function ignoredRestGetOnRelations(): array {
        return [
            DeploymentSpecificationModel::class,
            // Each carries every finding; asked for with `include` when wanted.
            ContainerImageScanModel::class,
            // A row per scan for a year.
            ContainerImageScanRecordModel::class,
        ];
    }

}
