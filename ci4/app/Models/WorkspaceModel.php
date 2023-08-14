<?php namespace App\Models;

use App\Entities\Deployment;
use App\Entities\Workspace;
use RestExtension\Core\Model;
use RestExtension\QueryParser;
use RestExtension\ResourceModelInterface;

class WorkspaceModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        DeletionModel::class,
        EmailServiceModel::class,
        DomainModel::class,
        DatabaseServiceModel::class,
        DeploymentPackageModel::class,
    ];

    public $hasMany = [
        DeploymentModel::class,
    ];

    public function preRestGet($queryParser, $id) {
        if ($queryParser->hasInclude('deployment')) {
            $queryParser->getInclude('deployment')->ignoreAuto = true;
        }
    }

    /**
     * @param QueryParser $queryParser
     * @param Workspace $items
     * @return void
     */
    public function postRestGet($queryParser, $items): void {
        if ($queryParser->hasInclude('deployment')) {
            /** @var Deployment $deployments */
            $deployments = (new DeploymentModel())
                ->includeRelated(DeploymentSpecificationModel::class)
                ->includeRelated(DomainModel::class)
                ->whereIn('workspace_id', $items)
                ->find();
            foreach ($deployments as $deployment) {
                $items->getById($deployment->workspace_id)->deployments->add($deployment);
            }
        }
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

}
