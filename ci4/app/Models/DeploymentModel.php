<?php namespace App\Models;

use App\Entities\Deployment;
use RestExtension\ResourceModelInterface;

class DeploymentModel extends \RestExtension\Models\UserModel implements ResourceModelInterface {

    public $hasOne = [
        DeletionModel::class,
        DeploymentSpecificationModel::class,
        WorkspaceModel::class,
        DatabaseServiceModel::class,
        DomainModel::class,
        'last_migration_job' => [
            'class' => MigrationJobModel::class,
        ]
    ];

    public $hasMany = [
        EnvironmentVariableModel::class,
        MigrationJobModel::class,
        DeploymentVolumeModel::class,
        AutoUpdateModel::class,
    ];

    public function preRestGet($queryParser, $id) {
        $this
            ->includeRelated(DeploymentSpecificationModel::class)
            ->includeRelated([DeploymentSpecificationModel::class, ContainerImageModel::class])
            ->includeRelated('last_migration_job');
    }

    /**
     * @param $queryParser
     * @param Deployment $items
     * @return void
     */
    public function postRestGet($queryParser, $items) {

    }

    public function isRestCreationAllowed($item): bool {
        return false;
    }

    public function isRestUpdateAllowed($item): bool {
        return false;
    }

    public function isRestDeleteAllowed($item): bool {
        return true;
    }

    public function appleRestGetManyRelations($items) {

    }

    public function ignoredRestGetOnRelations() {
        return [
            MigrationJobModel::class,
            AutoUpdateModel::class,
        ];
    }

}
