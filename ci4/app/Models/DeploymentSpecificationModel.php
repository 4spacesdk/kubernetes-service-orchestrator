<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class DeploymentSpecificationModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        ContainerImageModel::class,
        'database_migration_container_image' => [
            'class' => ContainerImageModel::class,
            'otherField' => 'deployment_specification_database_migration_container_image',
            'joinSelfAs' => 'id',
            'joinTable' => 'deployment_specifications',
        ],
    ];

    public $hasMany = [
        DeploymentModel::class,
        DeploymentSpecificationPostCommandModel::class,
        DeploymentSpecificationEnvironmentVariableModel::class,
        DeploymentSpecificationServicePortModel::class,
        DeploymentSpecificationIngressModel::class,
        DeploymentSpecificationClusterRoleRuleModel::class,
        DeploymentSpecificationRoleRuleModel::class,
        DeploymentPackageDeploymentSpecificationModel::class,
        DeploymentSpecificationServiceAnnotationModel::class,
        DeploymentSpecificationDeploymentAnnotationModel::class,
        DeploymentSpecificationQuickCommandModel::class,
        DeploymentSpecificationInitContainerModel::class,
        DeploymentSpecificationPostUpdateActionModel::class,
        LabelModel::class,
        DeploymentSpecificationCronJobModel::class,
    ];

    public function preRestGet($queryParser, $id) {
        $this->includeRelated(ContainerImageModel::class);

        if ($queryParser->hasFilter('label')) {
            $queryParser->getFilter('label')[0]->ignoreAuto = true;
            $selectors = explode(',', $queryParser->getFilter('label')[0]->value);

            foreach ($selectors as $selector) {
                [$name, $value] = explode('=', $selector);

                $labelSubQuery = (new LabelModel())
                    ->select('COUNT(*) as count', true, false)
                    ->whereRelated(DeploymentSpecificationModel::class, 'id', '${parent}.id', false)
                    ->where('name', $name)
                    ->where('value', $value)
                    ->having('count >', 0, true, false);

                $this->whereSubQuery($labelSubQuery, '', null, false);
            }
        }
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
        ];
    }

}
