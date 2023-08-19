<?php namespace App\Models;

use RestExtension\Core\Model;

class DeletionModel extends Model {

    public $hasOne = [
        'created_by' => [
            'class' => UserModel::class,
            'otherField' => 'deletion_creator'
        ],
        'updated_by' => [
            'class' => UserModel::class,
            'otherField' => 'deletion_editor'
        ],
    ];

    public $hasMany = [
        UserModel::class,
        DatabaseServiceModel::class,
        DeploymentModel::class,
        DomainModel::class,
        EmailServiceModel::class,
        EnvironmentVariableModel::class,
        WorkspaceModel::class,
        DeploymentVolumeModel::class,
    ];

}
