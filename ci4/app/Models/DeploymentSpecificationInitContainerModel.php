<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class DeploymentSpecificationInitContainerModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        InitContainerModel::class,
        DeploymentSpecificationModel::class,
    ];

    public $hasMany = [

    ];

    public function preRestGet($queryParser, $id) {
        $this->includeRelated(InitContainerModel::class);
        $this->includeRelated([InitContainerModel::class, ContainerImageModel::class]);
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

    public function ignoredRestGetOnRelations() {
        return [

        ];
    }

}
