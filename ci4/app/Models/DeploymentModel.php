<?php namespace App\Models;

use App\Entities\Deployment;
use RestExtension\ResourceModelInterface;

class DeploymentModel extends \RestExtension\Models\UserModel implements ResourceModelInterface {

    public $hasOne = [
        DeletionModel::class,
        DeploymentSpecificationModel::class,
        WorkspaceModel::class,
        DatabaseServiceModel::class,
        DomainModel::class,
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
    ];

    public function preRestGet($queryParser, $id) {
        $this
            ->includeRelated(DeploymentSpecificationModel::class)
            ->includeRelated([DeploymentSpecificationModel::class, ContainerImageModel::class])
            ->includeRelated('last_migration_job');

        if ($queryParser->hasFilter('label')) {
            $queryParser->getFilter('label')[0]->ignoreAuto = true;
            $selectors = explode(',', $queryParser->getFilter('label')[0]->value);

            foreach ($selectors as $selector) {
                [$name, $value] = explode('=', $selector);

                $labelSubQuery = (new LabelModel())
                    ->select('COUNT(*) as count', true, false)
                    ->whereRelated(DeploymentModel::class, 'id', '${parent}.id', false)
                    ->where('name', $name)
                    ->where('value', $value)
                    ->having('count >', 0, true, false);

                $this->whereSubQuery($labelSubQuery, '', null, false);
            }
        }
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
