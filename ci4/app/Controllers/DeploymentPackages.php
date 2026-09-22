<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\Deployment;
use App\Entities\DeploymentPackage;
use App\Entities\DeploymentPackageDeploymentSpecification;
use App\Entities\DeploymentPackageEnvironmentVariable;
use App\Entities\DeploymentSpecification;
use App\Entities\DeploymentSpecificationEnvironmentVariable;
use App\Entities\EnvironmentVariable;
use App\Entities\Label;
use App\Interfaces\DeploymentPackageDeploymentSpecificationList;
use App\Interfaces\EnvironmentVariableList;
use App\Interfaces\LabelList;
use App\Models\DeploymentModel;
use App\Models\DeploymentPackageEnvironmentVariableModel;
use App\Models\WorkspaceModel;

class DeploymentPackages extends ResourceController {

    /**
     * A saved copy, with everything it is made of. See DeploymentPackage::duplicate() for what
     * is and is not part of that.
     *
     * @route /deployment-packages/{id}/duplicate
     * @method post
     * @custom true
     * @param int $id
     * @return void
     * @audit entity
     */
    public function duplicate(int $id): void {
        $item = new DeploymentPackage();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment package');
            return;
        }
        $this->_setResource($item->duplicate());
        $this->success();
    }

    /**
     * @route /deployment-packages/{id}/deployment-specifications
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema DeploymentPackageDeploymentSpecificationList
     * @return void
     * @audit entity
     */
    public function updateDeploymentSpecifications(int $id): void {
        $item = new DeploymentPackage();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment package');
            return;
        }

        /** @var DeploymentPackageDeploymentSpecificationList $body */
        $body = $this->request->getJSON();
        $values = new DeploymentPackageDeploymentSpecification();
        $values->all = array_map(
            fn($data) => DeploymentPackageDeploymentSpecification::Create($data),
            $body->values
        );
        $item->updateDeploymentSpecifications($values);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-packages/{id}/environment-variables
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema EnvironmentVariableList
     * @return void
     * @audit entity
     */
    public function updateEnvironmentVariables(int $id): void {
        $item = new DeploymentPackage();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment package');
            return;
        }

        /** @var EnvironmentVariableList $body */
        $body = $this->request->getJSON();
        $values = new DeploymentPackageEnvironmentVariable();
        $values->all = array_map(
            fn(array $variable) => DeploymentPackageEnvironmentVariable::Create(...$variable),
            DeploymentPackageEnvironmentVariable::Replacements($body->values, $item->deployment_package_environment_variables->find())
        );
        $item->updateEnvironmentVariables($values);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-packages/{deploymentPackageId}/environment-variables/copy-to-deployments
     * @method put
     * @custom true
     * @param int $deploymentPackageId
     * The template's own variable of that name, value and all: a secret one cannot be sent
     * here, because the UI is never shown it, and a value in a url ends up in logs.
     *
     * @parameter string $name parameterType=query
     * @parameter bool $override parameterType=query
     * @return void
     * @audit entity
     */
    public function copyEnvironmentVariableToDeployments(int $deploymentPackageId): void {
        $item = new DeploymentPackage();
        $item->find($deploymentPackageId);
        if (!$item->exists()) {
            $this->fail('unknown deployment package');
            return;
        }

        $name = (string) $this->request->getGet('name');
        $override = in_array($this->request->getGet('override'), ['1', 'true']);

        /** @var DeploymentPackageEnvironmentVariable $packageVariable */
        $packageVariable = (new DeploymentPackageEnvironmentVariableModel())
            ->where('deployment_package_id', $item->id)
            ->where('name', $name)
            ->find();
        if (!$packageVariable->exists()) {
            $this->fail('unknown environment variable');
            return;
        }
        $environmentVariable = EnvironmentVariable::Prepare(
            $packageVariable->name,
            (string) $packageVariable->value,
            (bool) $packageVariable->is_secret
        );

        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->whereRelated(WorkspaceModel::class, 'deployment_package_id', $item->id)
            ->find();
        foreach ($deployments as $deployment) {
            $deployment->updateEnvironmentVariable($environmentVariable, $override);
        }

        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-packages/{deploymentPackageId}/labels
     * @method put
     * @custom true
     * @param int $deploymentPackageId
     * @requestSchema LabelList
     * @return void
     * @audit entity
     */
    public function updateLabels(int $deploymentPackageId): void {
        $item = new DeploymentPackage();
        $item->find($deploymentPackageId);
        if (!$item->exists()) {
            $this->fail('unknown deployment package');
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
