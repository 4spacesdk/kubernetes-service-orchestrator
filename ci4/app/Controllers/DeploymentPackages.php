<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\Deployment;
use App\Entities\DeploymentPackage;
use App\Entities\DeploymentPackageDeploymentSpecification;
use App\Entities\DeploymentPackageEnvironmentVariable;
use App\Entities\DeploymentSpecification;
use App\Entities\DeploymentSpecificationEnvironmentVariable;
use App\Entities\EnvironmentVariable;
use App\Interfaces\DeploymentPackageDeploymentSpecificationList;
use App\Interfaces\EnvironmentVariableList;
use App\Models\DeploymentModel;
use App\Models\DeploymentPackageEnvironmentVariableModel;
use App\Models\WorkspaceModel;

class DeploymentPackages extends ResourceController {

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
     * @ignore true
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

}
