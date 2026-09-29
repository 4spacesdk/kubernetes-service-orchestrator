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

        foreach ($items as $image) {
            $image->running_deployment_ids = $running[(int) $image->id] ?? [];
            $image->security_advice = json_encode(SecurityAdvice::For($image, $lastScans[(int) $image->id] ?? null));
        }
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
