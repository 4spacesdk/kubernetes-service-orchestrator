<?php namespace App\Models;

use App\Models\Concerns\FiltersByLabel;
use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class DeploymentSpecificationModel extends Model implements ResourceModelInterface {

    use FiltersByLabel;

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
        WorkspaceTemplateDeploymentSpecificationModel::class,
        DeploymentSpecificationServiceAnnotationModel::class,
        DeploymentSpecificationDeploymentAnnotationModel::class,
        DeploymentSpecificationQuickCommandModel::class,
        DeploymentSpecificationInitContainerModel::class,
        DeploymentSpecificationPostUpdateActionModel::class,
        LabelModel::class,
        DeploymentSpecificationCronJobModel::class,
        DeploymentSpecificationHttpProxyRouteModel::class,
        DeploymentSpecificationVolumeModel::class,
    ];

    public function preRestGet($queryParser, $id) {
        $this->includeRelated(ContainerImageModel::class);

        $this->applyLabelFilter($queryParser);
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
