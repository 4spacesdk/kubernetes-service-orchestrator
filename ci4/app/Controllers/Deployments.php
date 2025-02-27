<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\Deployment;
use App\Entities\DeploymentSpecification;
use App\Entities\DeploymentVolume;
use App\Entities\EnvironmentVariable;
use App\Entities\Label;
use App\Entities\Workspace;
use App\Exceptions\ValidationException;
use App\Interfaces\DeploymentVolumeList;
use App\Interfaces\EnvironmentVariableList;
use App\Interfaces\LabelList;
use App\Libraries\DeploymentSteps\BaseDeploymentStep;
use App\Models\MigrationJobModel;
use DebugTool\Data;
use Google\ApiCore\ApiException;

class Deployments extends ResourceController {

    /**
     * @route /deployments/create
     * @method post
     * @custom true
     * @parameter int $deploymentSpecificationId parameterType=query
     * @parameter string $name parameterType=query
     * @parameter int $workspaceId parameterType=query
     * @parameter string $namespace parameterType=query
     * @parameter string $version parameterType=query
     * @return void
     */
    public function create(): void {
        $deploymentSpecification = new DeploymentSpecification();
        $deploymentSpecification->find($this->request->getGet('deploymentSpecificationId'));

        if (!$deploymentSpecification->exists()) {
            $this->fail('unknown deployment specification');
            return;
        }

        $workspace = new Workspace();
        $workspaceId = $this->request->getGet('workspaceId') ?? 0;
        if ($workspaceId) {
            $workspace->find($workspaceId);
        }

        try {
            if ($workspace->exists()) {
                $item = $workspace->addDeployment($deploymentSpecification, $this->request->getGet('version') ?? null);
            } else {
                $item = Deployment::Prepare(
                    $deploymentSpecification,
                    $this->request->getGet('namespace') ?? '',
                    $this->request->getGet('workspaceId') ?? 0,
                    $this->request->getGet('name') ?? '',
                    $this->request->getGet('version') ?? ''
                );
                $item->save();
            }
            $this->_setResource($item);
        } catch (ValidationException|ApiException|\Google\ApiCore\ValidationException $e) {
            $this->fail($e->getMessage());
            return;
        }

        $this->success();
    }

    /**
     * @route /deployments/{id}/version
     * @method put
     * @custom true
     * @param int $id
     * @parameter string $value parameterType=query
     * @return void
     */
    public function updateVersion(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if ($item->exists()) {
            $item->updateVersion($this->request->getGet('value'));
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/environment
     * @method put
     * @custom true
     * @param int $id
     * @parameter string $value parameterType=query
     * @return void
     */
    public function updateEnvironment(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if ($item->exists()) {
            $item->updateEnvironment($this->request->getGet('value'));
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/workspace
     * @method put
     * @custom true
     * @param int $id
     * @parameter int $value parameterType=query
     * @return void
     */
    public function updateWorkspace(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if ($item->exists()) {
            $item->updateWorkspaceId($this->request->getGet('value'));
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/databaseServiceId
     * @method put
     * @custom true
     * @param int $id
     * @parameter int $value parameterType=query
     * @return void
     */
    public function updateDatabaseServiceId(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if ($item->exists()) {
            $item->updateDatabaseServiceId($this->request->getGet('value'));
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/ingress
     * @method put
     * @custom true
     * @param int $id
     * @parameter int $domainId parameterType=query
     * @parameter string $subdomain parameterType=query
     * @parameter string $aliases parameterType=query
     * @return void
     */
    public function updateIngress(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if ($item->exists()) {
            try {
                $item->updateIngress(
                    $this->request->getGet('domainId'),
                    $this->request->getGet('subdomain'),
                    $this->request->getGet('aliases') ?? ''
                );
            } catch (ValidationException $e) {
                $this->fail($e->getMessage());
                return;
            }
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/resourceManagement
     * @method put
     * @custom true
     * @param int $id
     * @parameter int $cpuLimit parameterType=query
     * @parameter int $cpuRequest parameterType=query
     * @parameter int $memoryLimit parameterType=query
     * @parameter int $memoryRequest parameterType=query
     * @parameter int $replicas parameterType=query
     * @return void
     */
    public function updateResourceManagement(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if ($item->exists()) {
            $item->updateResourceManagement(
                $this->request->getGet('cpuLimit'),
                $this->request->getGet('cpuRequest'),
                $this->request->getGet('memoryLimit'),
                $this->request->getGet('memoryRequest'),
                $this->request->getGet('replicas')
            );
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/updateManagement
     * @method put
     * @custom true
     * @param int $id
     * @parameter bool $enabled parameterType=query
     * @parameter string $tagRegex parameterType=query
     * @parameter bool $requireApproval parameterType=query
     * @return void
     */
    public function updateUpdateManagement(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if ($item->exists()) {
            try {
                $item->updateUpdateManagement(
                    in_array($this->request->getGet('enabled'), ['1', 'true']),
                    $this->request->getGet('tagRegex'),
                    in_array($this->request->getGet('requireApproval'), ['1', 'true'])
                );
            } catch (\Exception $e) {
                Data::debug($e->getMessage());
            }
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/environment-variables
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema EnvironmentVariableList
     * @return void
     */
    public function updateEnvironmentVariables(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if ($item->exists()) {
            /** @var EnvironmentVariableList $body */
            $body = $this->request->getJSON();
            $values = new EnvironmentVariable();
            $values->all = array_map(
                fn($data) => EnvironmentVariable::Create($data->name, $data->value),
                $body->values
            );
            $item->updateEnvironmentVariables($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/volumes
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema DeploymentVolumeList
     * @return void
     */
    public function updateDeploymentVolumes(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if ($item->exists()) {
            /** @var DeploymentVolumeList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentVolume();
            $values->all = array_map(
                fn($data) => DeploymentVolume::Create(
                    $data->mount_path,
                    $data->sub_path,
                    $data->capacity,
                    $data->volume_mode,
                    $data->reclaim_policy,
                    $data->nfs_server,
                    $data->nfs_path,
                ),
                $body->values
            );
            $item->updateDeploymentVolumes($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/labels
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema LabelList
     * @return void
     */
    public function updateLabels(int $id): void {
        $item = new Deployment();
        $item->find($id);
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
     * @route /deployments/{id}/status
     * @method get
     * @custom true
     * @param int $id
     * @return void
     */
    public function getStatus(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if ($item->exists()) {
            $item->checkStatus();
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/migration-jobs
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema MigrationJob
     * @return void
     */
    public function getMigrationJobs(int $id): void {
        $this->queryParser->parseFilter("deployment_id:$id");
        $items = (new MigrationJobModel())->restGet(0, $this->queryParser);
        $this->_setResources($items);
        $this->success();
    }

    /**
     * @route /deployments/{id}/deployment-specification
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema DeploymentSpecification
     * @return void
     */
    public function getDeploymentSpecification(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        $spec = $item->findDeploymentSpecification();

        $result = $spec->toArray();
        $result['deploymentSteps'] = array_map(fn (BaseDeploymentStep $step) => $step->toArray(), $spec->getDeploymentSteps($item));

        Data::set('resource', $result);
        $this->success();
    }

    /**
     * @ignore true
     * @return void
     */
    public function post() {
    }

    /**
     * @ignore true
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

}
