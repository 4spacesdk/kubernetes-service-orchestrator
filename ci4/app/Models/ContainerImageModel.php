<?php namespace App\Models;

use App\Libraries\ImageScanning\ImageScanner;
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
        foreach ($items as $image) {
            $image->running_deployment_ids = $running[(int) $image->id] ?? [];
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
