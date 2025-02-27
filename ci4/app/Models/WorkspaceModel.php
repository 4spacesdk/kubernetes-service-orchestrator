<?php namespace App\Models;

use App\Entities\Deployment;
use App\Entities\DeploymentsLabel;
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
        $this->includeRelated(DomainModel::class);

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

        if ($queryParser->hasFilter('status')) {
            $queryParser->getFilter('status')[0]->ignoreAuto = true;
            $statuses = $queryParser->getFilter('status')[0]->value;
            if (is_array($statuses) && count($statuses) > 0 && $statuses[0] !== '') {
                $this->groupStart();
                foreach ($statuses as $status) {
                    $deploymentStatusSubQuery = (new DeploymentModel())
                        ->select('COUNT(*) as count', true, false)
                        ->whereRelated(WorkspaceModel::class, 'id', '${parent}.id', false)
                        ->where('status', $status)
                        ->having('count >', 0, true, false);

                    $this->orWhereSubQuery($deploymentStatusSubQuery, '', null, false);
                }
                $this->groupEnd();
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
                ->whereIn('workspace_id', $items)
                ->find();
            foreach ($deployments as $deployment) {
                $workspace = $items->getById($deployment->workspace_id);
                $workspace->deployments->add($deployment);
                $deployment->url_external = $deployment
                    ->findDeploymentSpecification()
                    ->getUrl($workspace->subdomain, $workspace->domain, true, true);
                $deployment->url_internal = $deployment->getInternalUrl();
            }

            if ($deployments->exists()) {
                /** @var DeploymentsLabel $deploymentsLabels */
                $deploymentsLabels = (new DeploymentsLabelModel())
                    ->includeRelated(LabelModel::class)
                    ->whereIn('deployment_id', array_map(fn(Deployment $deployment) => $deployment->id, $deployments->all))
                    ->find();
                foreach ($deploymentsLabels as $deploymentsLabel) {
                    $deployments->getById($deploymentsLabel->deployment_id)->labels->add($deploymentsLabel->label);
                }
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
