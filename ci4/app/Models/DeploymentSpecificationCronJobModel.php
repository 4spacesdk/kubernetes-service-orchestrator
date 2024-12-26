<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class DeploymentSpecificationCronJobModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        K8sCronJobModel::class,
        DeploymentSpecificationModel::class,
    ];

    public $hasMany = [

    ];

    public function preRestGet($queryParser, $id) {
        $this
            ->includeRelated(K8sCronJobModel::class)
            ->includeRelated([K8sCronJobModel::class, ContainerImageModel::class]);
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
