<?php namespace App\Models;

use App\Entities\Deployment;
use App\Models\Concerns\FiltersByLabel;
use App\Models\Concerns\FiltersByProject;
use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class DeploymentModel extends Model implements ResourceModelInterface {

    use FiltersByLabel;
    use FiltersByProject;

    public $hasOne = [
        DeletionModel::class,
        DeploymentSpecificationModel::class,
        WorkspaceModel::class,
        DatabaseServiceModel::class,
        'last_migration_job' => [
            'class' => MigrationJobModel::class,
        ]
    ];

    public $hasMany = [
        EnvironmentVariableModel::class,
        MigrationJobModel::class,
        DeploymentVolumeModel::class,
        AutoUpdateModel::class,
        LabelModel::class,
        DeploymentsLabelModel::class,
        DeploymentCronJobModel::class,
        KNativeMinScaleScheduleModel::class => [
            'joinTable' => 'deployments_knative_min_scale_schedules',
            'joinSelfAs' => 'deployment_id',
            'joinOtherAs' => 'knative_min_scale_schedule_id',
        ],
    ];

    public function preRestGet($queryParser, $id) {
        $this
            ->includeRelated(DeploymentSpecificationModel::class)
            ->includeRelated([DeploymentSpecificationModel::class, ContainerImageModel::class])
            ->includeRelated('last_migration_job');

        $this->applyLabelFilter($queryParser);
        $this->applyProjectFilter($queryParser, WorkspaceModel::class);
    }

    /**
     * @param $queryParser
     * @param Deployment $items
     * @return void
     */
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

    public function ignoredRestGetOnRelations() {
        return [
            MigrationJobModel::class,
            AutoUpdateModel::class,
        ];
    }

}
