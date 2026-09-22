<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\Deployment;
use App\Entities\WorkspaceTemplate;
use App\Entities\WorkspaceTemplateDeploymentSpecification;
use App\Entities\WorkspaceTemplateEnvironmentVariable;
use App\Entities\DeploymentSpecification;
use App\Entities\DeploymentSpecificationEnvironmentVariable;
use App\Entities\EnvironmentVariable;
use App\Entities\Label;
use App\Interfaces\WorkspaceTemplateDeploymentSpecificationList;
use App\Interfaces\EnvironmentVariableList;
use App\Interfaces\LabelList;
use App\Models\DeploymentModel;
use App\Models\WorkspaceTemplateEnvironmentVariableModel;
use App\Models\WorkspaceModel;

class WorkspaceTemplates extends ResourceController {

    /**
     * A saved copy, with everything it is made of. See WorkspaceTemplate::duplicate() for what
     * is and is not part of that.
     *
     * @route /workspace-templates/{id}/duplicate
     * @method post
     * @custom true
     * @param int $id
     * @return void
     * @audit entity
     */
    public function duplicate(int $id): void {
        $item = new WorkspaceTemplate();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace template');
            return;
        }
        $this->_setResource($item->duplicate());
        $this->success();
    }

    /**
     * @route /workspace-templates/{id}/deployment-specifications
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema WorkspaceTemplateDeploymentSpecificationList
     * @return void
     * @audit entity
     */
    public function updateDeploymentSpecifications(int $id): void {
        $item = new WorkspaceTemplate();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace template');
            return;
        }

        /** @var WorkspaceTemplateDeploymentSpecificationList $body */
        $body = $this->request->getJSON();
        $values = new WorkspaceTemplateDeploymentSpecification();
        $values->all = array_map(
            fn($data) => WorkspaceTemplateDeploymentSpecification::Create($data),
            $body->values
        );
        $item->updateDeploymentSpecifications($values);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /workspace-templates/{id}/environment-variables
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema EnvironmentVariableList
     * @return void
     * @audit entity
     */
    public function updateEnvironmentVariables(int $id): void {
        $item = new WorkspaceTemplate();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace template');
            return;
        }

        /** @var EnvironmentVariableList $body */
        $body = $this->request->getJSON();
        $values = new WorkspaceTemplateEnvironmentVariable();
        $values->all = array_map(
            fn(array $variable) => WorkspaceTemplateEnvironmentVariable::Create(...$variable),
            WorkspaceTemplateEnvironmentVariable::Replacements($body->values, $item->workspace_template_environment_variables->find())
        );
        $item->updateEnvironmentVariables($values);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /workspace-templates/{workspaceTemplateId}/environment-variables/copy-to-deployments
     * @method put
     * @custom true
     * @param int $workspaceTemplateId
     * The template's own variable of that name, value and all: a secret one cannot be sent
     * here, because the UI is never shown it, and a value in a url ends up in logs.
     *
     * @parameter string $name parameterType=query
     * @parameter bool $override parameterType=query
     * @return void
     * @audit entity
     */
    public function copyEnvironmentVariableToDeployments(int $workspaceTemplateId): void {
        $item = new WorkspaceTemplate();
        $item->find($workspaceTemplateId);
        if (!$item->exists()) {
            $this->fail('unknown workspace template');
            return;
        }

        $name = (string) $this->request->getGet('name');
        $override = in_array($this->request->getGet('override'), ['1', 'true']);

        /** @var WorkspaceTemplateEnvironmentVariable $templateVariable */
        $templateVariable = (new WorkspaceTemplateEnvironmentVariableModel())
            ->where('workspace_template_id', $item->id)
            ->where('name', $name)
            ->find();
        if (!$templateVariable->exists()) {
            $this->fail('unknown environment variable');
            return;
        }
        $environmentVariable = EnvironmentVariable::Prepare(
            $templateVariable->name,
            (string) $templateVariable->value,
            (bool) $templateVariable->is_secret
        );

        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->whereRelated(WorkspaceModel::class, 'workspace_template_id', $item->id)
            ->find();
        foreach ($deployments as $deployment) {
            $deployment->updateEnvironmentVariable($environmentVariable, $override);
        }

        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /workspace-templates/{workspaceTemplateId}/labels
     * @method put
     * @custom true
     * @param int $workspaceTemplateId
     * @requestSchema LabelList
     * @return void
     * @audit entity
     */
    public function updateLabels(int $workspaceTemplateId): void {
        $item = new WorkspaceTemplate();
        $item->find($workspaceTemplateId);
        if (!$item->exists()) {
            $this->fail('unknown workspace template');
            return;
        }

        /** @var LabelList $body */
        $body = $this->request->getJSON();
        $values = new Label();
        $values->all = array_map(
            fn($data) => Label::Create($data->name, $data->value),
            $body->values
        );
        $item->updateLabels($values);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * Switched off. `@ignore true` is read by `ci4restextension`'s `ApiItem`, so the route
     * generator and swagger leave this verb out - and no migration ever wrote it into
     * `api_routes` either, so no request can reach it. Both halves are needed: the
     * annotation does not remove a row that is already in the table. See
     * `Workspaces`/`Deployments`, where exactly that went wrong.
     *
     * @param $id
     * @return void
     * @codeCoverageIgnore
     * @ignore true
     */
    public function put($id = 0) {
    }

}
