<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class KNativeMinScaleScheduleModel extends Model implements ResourceModelInterface {

    public function getTableName() {
        return 'knative_min_scale_schedules';
    }

    public $hasOne = [

    ];

    public $hasMany = [
        DeploymentModel::class => [
            'joinTable' => 'deployments_knative_min_scale_schedules',
            'joinSelfAs' => 'knative_min_scale_schedule_id',
            'joinOtherAs' => 'deployment_id',
        ],
        WorkspaceTemplateDeploymentSpecificationModel::class => [
            'joinTable' => 'workspace_template_ds_knative_min_scale_schedules',
            'joinSelfAs' => 'knative_min_scale_schedule_id',
            'joinOtherAs' => 'workspace_template_deployment_specification_id',
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

    public function ignoredRestGetOnRelations(): array {
        return [
            DeploymentModel::class,
            WorkspaceTemplateDeploymentSpecificationModel::class,
        ];
    }

}
