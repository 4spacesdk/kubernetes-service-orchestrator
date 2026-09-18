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
     */
    public function updateDeploymentSpecifications(int $id): void {
        $item = new DeploymentPackage();
        $item->find($id);
        if ($item->exists()) {
            /** @var DeploymentPackageDeploymentSpecificationList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentPackageDeploymentSpecification();
            $values->all = array_map(
                fn($data) => DeploymentPackageDeploymentSpecification::Create($data),
                $body->values
            );
            $item->updateDeploymentSpecifications($values);
        }
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
     */
    public function updateEnvironmentVariables(int $id): void {
        $item = new DeploymentPackage();
        $item->find($id);
        if ($item->exists()) {
            /** @var EnvironmentVariableList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentPackageEnvironmentVariable();
            $values->all = array_map(
                fn($data) => DeploymentPackageEnvironmentVariable::Create($data->name, $data->value),
                $body->values
            );
            $item->updateEnvironmentVariables($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-packages/{deploymentPackageId}/environment-variables/copy-to-deployments
     * @method put
     * @custom true
     * @param int $deploymentPackageId
     * @parameter string $name parameterType=query
     * @parameter string $value parameterType=query
     * @parameter bool $override parameterType=query
     * @return void
     */
    public function copyEnvironmentVariableToDeployments(int $deploymentPackageId): void {
        $item = new DeploymentPackage();
        $item->find($deploymentPackageId);
        if (!$item->exists()) {
            $this->fail('unknown deployment package');
            return;
        }

        $name = $this->request->getGet('name');
        $value = $this->request->getGet('value');
        $override = in_array($this->request->getGet('override'), ['1', 'true']);
        $environmentVariable = EnvironmentVariable::Prepare($name, $value);

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
     */
    public function updateLabels(int $deploymentPackageId): void {
        $item = new DeploymentPackage();
        $item->find($deploymentPackageId);
        if (!$item->exists()) {
            $this->fail('unknown deployment package');
            return;
        }

        if ($item->exists()) {
            /** @var LabelList $body */
            $body = $this->request->getJSON();
            $values = new Label();
            $values->all = array_map(
                fn($data) => Label::Create($data->name, $data->value),
                $body->values
            );
            $item->updateLabels($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * Switched off. `@ignore true` is read by `ci4restextension`'s `ApiItem`, so the route
     * generator and swagger leave this verb out - and no migration ever wrote it into
     * `api_routes` either, so no request can reach it. Both halves are needed: the
     * annotation does not remove a row that is already in the table. See SEC-11, and
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
