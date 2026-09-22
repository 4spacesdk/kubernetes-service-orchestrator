<?php namespace App\Models;

use App\Entities\Deployment;
use App\Entities\DeploymentsLabel;
use App\Entities\Workspace;
use App\Models\Concerns\FiltersByLabel;
use DebugTool\Data;
use RestExtension\Core\Model;
use RestExtension\QueryParser;
use RestExtension\ResourceModelInterface;

class WorkspaceModel extends Model implements ResourceModelInterface {

    use FiltersByLabel;

    public $hasOne = [
        DeletionModel::class,
        EmailServiceModel::class,
        DomainModel::class,
        DatabaseServiceModel::class,
        WorkspaceTemplateModel::class,
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

        $this->applyLabelFilter($queryParser);

        if ($queryParser->hasFilter('status')) {
            $filter = $queryParser->getFilter('status')[0];
            $statuses = $filter->value;

            // `ignoreAuto` is set only where a condition is actually added, and that is the
            // whole of this fix. It used to be set two lines earlier, before the guard - so
            // `filter=status:synced`, which parses to a string rather than a list, fell
            // through the guard *and* told the extension not to apply it. The answer was
            // `200 OK`, shaped exactly like a filtered one, carrying every status there is.
            //
            // A scalar needs no branch of its own now: left alone, the extension applies it
            // as the ordinary `status = 'synced'` it always was on every other resource.
            if (is_array($statuses) && count($statuses) > 0 && $statuses[0] !== '') {
                // An empty list means "all", which is what a cleared status picker sends -
                // `whereIn('status', [''])` would answer nothing at all.
                $this->whereIn('status', $statuses);
                $filter->ignoreAuto = true;
            } else if (is_array($statuses)) {
                // Nothing to add, and nothing for the extension to add either: a list of
                // one empty string is not a column value.
                $filter->ignoreAuto = true;
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
