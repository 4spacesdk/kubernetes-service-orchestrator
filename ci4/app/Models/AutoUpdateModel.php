<?php namespace App\Models;

use App\Models\Concerns\FiltersByProject;
use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class AutoUpdateModel extends Model implements ResourceModelInterface {

    use FiltersByProject;

    public $hasOne = [
        DeploymentModel::class,
    ];

    public $hasMany = [

    ];

    public function preRestGet($queryParser, $id) {
        $this->applyProjectFilter($queryParser, [DeploymentModel::class, WorkspaceModel::class]);
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
        return true;
    }

    public function appleRestGetManyRelations($items) {

    }

}
