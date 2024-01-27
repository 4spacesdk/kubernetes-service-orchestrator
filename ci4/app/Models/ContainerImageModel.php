<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class ContainerImageModel extends Model implements ResourceModelInterface {

    public $hasOne = [

    ];

    public $hasMany = [
        DeploymentSpecificationModel::class,
        'deployment_specification_database_migration_container_image' => [
            'class' => DeploymentSpecificationModel::class,
            'otherField' => 'database_migration_container_image',
            'joinTable' => 'deployment_specifications',
            'joinSelfAs' => 'database_migration_container_image_id',
        ],
    ];

    public function preRestGet($queryParser, $id) {

    }

    public function postRestGet($queryParser, $items) {

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
        ];
    }

}
