<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class DeploymentSpecificationModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        ContainerImageModel::class,
        'database_migration_container_image' => [
            'class' => ContainerImageModel::class,
            'otherField' => 'deployment_specification_database_migration_container_image',
            'joinSelfAs' => 'id',
            'joinTable' => 'deployment_specifications',
        ],
    ];

    public $hasMany = [
        DeploymentModel::class,
        DeploymentSpecificationPostCommandModel::class,
        DeploymentSpecificationEnvironmentVariableModel::class,
        DeploymentSpecificationServicePortModel::class,
        DeploymentSpecificationIngressModel::class,
        DeploymentSpecificationClusterRoleRuleModel::class,
        DeploymentPackageDeploymentSpecificationModel::class,
        DeploymentSpecificationServiceAnnotationModel::class,
        DeploymentSpecificationQuickCommandModel::class,
        DeploymentSpecificationInitContainerModel::class,
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
            DeploymentModel::class,
        ];
    }

}
