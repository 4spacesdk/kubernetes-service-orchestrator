<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class DeploymentPackageDeploymentSpecificationModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        DeploymentPackageModel::class,
        DeploymentSpecificationModel::class,
    ];

    public $hasMany = [
        KNativeMinScaleScheduleModel::class => [
            'joinTable' => 'deployment_package_ds_knative_min_scale_schedules',
            'joinSelfAs' => 'deployment_package_deployment_specification_id',
            'joinOtherAs' => 'knative_min_scale_schedule_id',
        ],
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

    public function ignoredRestGetOnRelations() {
        return [
            WorkspaceModel::class,
        ];
    }

}
