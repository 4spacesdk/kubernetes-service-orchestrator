<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class MigrationJobModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        DeploymentModel::class,
    ];

    public $hasMany = [

    ];

    public function preRestGet($queryParser, $id) {
        $this->includeRelated([DeploymentModel::class, WorkspaceModel::class]);
    }

    public function postRestGet($queryParser, $items) {

    }

    public function isRestCreationAllowed($item): bool {
        return false;
    }

    public function isRestUpdateAllowed($item): bool {
        return false;
    }

    public function isRestDeleteAllowed($item): bool {
        return false;
    }

    public function appleRestGetManyRelations($items) {

    }
}
