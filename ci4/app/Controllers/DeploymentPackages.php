<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\DeploymentPackage;
use App\Entities\DeploymentPackageDeploymentSpecification;
use App\Interfaces\DeploymentPackageDeploymentSpecificationList;

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
     * @ignore true
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

}
