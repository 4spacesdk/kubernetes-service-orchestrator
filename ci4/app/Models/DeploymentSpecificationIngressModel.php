<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class DeploymentSpecificationIngressModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        DeploymentSpecificationModel::class,
    ];

    public $hasMany = [
        DeploymentSpecificationIngressRulePathModel::class,
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
