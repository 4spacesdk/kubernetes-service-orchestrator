<?php namespace App\Models;

use RestExtension\ResourceModelInterface;

class DeploymentVolumeModel extends \RestExtension\Models\UserModel implements ResourceModelInterface {

    public $hasOne = [
        DeploymentModel::class,
        DeletionModel::class,
    ];

    public $hasMany = [

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

}
