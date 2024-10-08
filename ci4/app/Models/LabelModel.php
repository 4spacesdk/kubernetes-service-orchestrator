<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class LabelModel extends Model implements ResourceModelInterface {

    public $hasOne = [

    ];

    public $hasMany = [
        WorkspaceModel::class,
        DeploymentPackageModel::class,
        DeploymentModel::class,
        DeploymentsLabelModel::class,
        DeploymentSpecificationModel::class,
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
            WorkspaceModel::class,
        ];
    }

}
