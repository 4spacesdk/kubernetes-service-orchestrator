<?php namespace App\Models;

use App\Entities\Deployment;
use App\Entities\Workspace;
use DebugTool\Data;
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
        LabelModel::class,
    ];

    public function preRestGet($queryParser, $id) {
        if ($queryParser->hasInclude('deployment')) {
            $queryParser->getInclude('deployment')->ignoreAuto = true;
        }

        if ($queryParser->hasFilter('label')) {
            $queryParser->getFilter('label')[0]->ignoreAuto = true;
            $selectors = explode(',', $queryParser->getFilter('label')[0]->value);

            foreach ($selectors as $selector) {
                [$name, $value] = explode('=', $selector);

                $labelSubQuery = (new LabelModel())
                    ->select('COUNT(*) as count', true, false)
                    ->whereRelated(WorkspaceModel::class, 'id', '${parent}.id', false)
                    ->where('name', $name)
                    ->where('value', $value)
                    ->having('count >', 0, true, false);

                $this->whereSubQuery($labelSubQuery, '', null, false);
            }
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
