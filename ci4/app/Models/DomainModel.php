<?php namespace App\Models;

use RestExtension\ResourceModelInterface;

class DomainModel extends \RestExtension\Models\UserModel implements ResourceModelInterface {

    public $hasOne = [
        DeletionModel::class,
    ];

    public $hasMany = [
        WorkspaceModel::class,
        DeploymentModel::class,
    ];

    public function preRestGet($queryParser, $id) {

    }

    public function postRestGet($queryParser, $items) {

    }

    public function isRestCreationAllowed($item): bool {
        return true;
    }

    public function isRestUpdateAllowed($item): bool {
        return false;
    }

    public function isRestDeleteAllowed($item): bool {
        return true;
    }

    public function appleRestGetManyRelations($items) {

    }

}
