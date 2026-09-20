<?php namespace App\Libraries\PostUpdateActions;

use App\Entities\Deployment;
use App\Entities\PostUpdateAction;
use App\Models\DeploymentSpecificationPostUpdateActionModel;
use App\Models\PostUpdateActionModel;
use DebugTool\Data;

class PostUpdateActionHelper {

    private Deployment $deployment;

    public function __construct(Deployment $deployment) {
        // The workspace is not fetched here. `PostUpdateAction::perform()` does the same
        // guard-and-find itself, right where it reads the workspace's namespace - so doing
        // it up front was a second query for a value that is looked up again anyway.
        $this->deployment = $deployment;
    }

    public function performAll(): void {
        /** @var PostUpdateAction $actions */
        $actions = (new PostUpdateActionModel())
            ->whereRelated(DeploymentSpecificationPostUpdateActionModel::class, 'deployment_specification_id', $this->deployment->deployment_specification_id)
            ->orderByRelated(DeploymentSpecificationPostUpdateActionModel::class, 'position', 'asc')
            ->find();
        Data::debug('found', $actions->count(), 'post update actions');

        $actions->all = array_filter($actions->all ?? [], fn(PostUpdateAction $action) => $action->checkConditions($this->deployment));

        foreach ($actions as $action) {
            $action->perform($this->deployment);
        }
    }

}
