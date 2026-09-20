<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\Deployment;
use App\Entities\DeploymentPackage;
use App\Entities\DeploymentPackageDeploymentSpecification;
use App\Entities\DeploymentSpecification;
use App\Entities\Label;
use App\Entities\Workspace;
use App\Exceptions\ValidationException;
use App\Interfaces\LabelList;
use App\Models\DeploymentModel;
use App\Models\DeploymentPackageDeploymentSpecificationModel;
use App\Models\MigrationJobModel;
use Google\ApiCore\ApiException;

class Workspaces extends ResourceController {

    /**
     * @route /workspaces/create
     * @method post
     * @custom true
     * @parameter int $deploymentPackageId parameterType=query
     * @parameter string $name parameterType=query
     * @parameter string $namespace parameterType=query
     * @parameter int $domainId parameterType=query
     * @parameter string $subdomain parameterType=query
     * @return void
     */
    public function create(): void {
        try {
            $deploymentPackage = new DeploymentPackage();
            $deploymentPackage->find($this->request->getGet('deploymentPackageId'));

            $item = Workspace::Create(
                $deploymentPackage,
                $this->request->getGet('name') ?? '',
                $this->request->getGet('namespace') ?? '',
                $this->request->getGet('domainId') ?? 0,
                $this->request->getGet('subdomain') ?? ''
            );
            $this->_setResource($item);
        } catch (ValidationException $e) {
            $this->fail($e->getMessage());
            return;
        }
        $this->success();
    }

    /**
     * @route /workspaces/{id}/deployments
     * @method post
     * @custom true
     * @param int $id
     * @parameter int $deploymentSpecificationId parameterType=query
     * @parameter string $name parameterType=query
     * @parameter string $namespace parameterType=query
     * @parameter string $version parameterType=query
     * @return void
     * @responseSchema Deployment
     */
    public function createDeployment(int $id = 0): void {
        $item = new Workspace();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace');
            return;
        }

        $deploymentSpecification = new DeploymentSpecification();
        $deploymentSpecification->find($this->request->getGet('deploymentSpecificationId'));

        if (!$deploymentSpecification->exists()) {
            $this->fail('unknown deployment specification');
            return;
        }

        try {
            $deployment = $item->addDeployment(
                $deploymentSpecification,
                $this->request->getGet('name') ?? null,
                $this->request->getGet('version') ?? null
            );
            $this->_setResource($deployment);
        } catch (ValidationException|ApiException|\Google\ApiCore\ValidationException $e) {
            $this->fail($e->getMessage());
            return;
        }

        $this->success();
    }

    /**
     * @route /workspaces/{id}/name
     * @method put
     * @custom true
     * @param int $id
     * @parameter string $value parameterType=query
     * @return void
     */
    public function updateName(int $id = 0): void {
        $value = $this->request->getGet('value');

        $item = new Workspace();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace');
            return;
        }

        $item->updateName($value);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /workspaces/{id}/ingress
     * @method put
     * @custom true
     * @param int $id
     * @parameter int $domainId parameterType=query
     * @parameter string $subdomain parameterType=query
     * @parameter string $aliases parameterType=query
     * @return void
     */
    public function updateIngress(int $id): void {
        $item = new Workspace();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace');
            return;
        }

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
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /workspaces/{id}/emailServiceId
     * @method put
     * @custom true
     * @param int $id
     * @parameter int $value parameterType=query
     * @return void
     */
    public function updateEmailServiceId(int $id = 0): void {
        $value = $this->request->getGet('value');

        $item = new Workspace();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace');
            return;
        }

        $item->updateEmailServiceId($value);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /workspaces/{id}/databaseServiceId
     * @method put
     * @custom true
     * @param int $id
     * @parameter int $value parameterType=query
     * @return void
     */
    public function updateDatabaseServiceId(int $id = 0): void {
        $value = $this->request->getGet('value');

        $item = new Workspace();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace');
            return;
        }

        $item->updateDatabaseServiceId($value);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /workspaces/{id}/deploy
     * @method put
     * @custom true
     * @param int $id
     * @parameter string $value parameterType=query
     * @return void
     */
    public function deploy(int $id = 0): void {
        $item = new Workspace();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace');
            return;
        }

        $errors = $item->deploy();
        if ($errors) {
            $this->fail($errors);
            return;
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /workspaces/{id}/terminate
     * @method put
     * @custom true
     * @param int $id
     * @parameter string $value parameterType=query
     * @return void
     */
    public function terminate(int $id = 0): void {
        $item = new Workspace();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace');
            return;
        }

        $errors = $item->terminate();
        if ($errors) {
            $this->fail($errors);
            return;
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * Pause: terminate the workspace and remember that a person decided to, so the pause
     * cannot fall off when the status is recomputed.
     *
     * @route /workspaces/{id}/pause
     * @method put
     * @custom true
     * @param int $id
     * @return void
     */
    public function pause(int $id = 0): void {
        $item = new Workspace();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace');
            return;
        }

        $errors = $item->pause();
        if ($errors) {
            $this->fail($errors);
            return;
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * Take the pause off. The workspace stays terminated until someone deploys it.
     *
     * @route /workspaces/{id}/resume
     * @method put
     * @custom true
     * @param int $id
     * @return void
     */
    public function resume(int $id = 0): void {
        $item = new Workspace();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace');
            return;
        }

        $item->resume();
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /workspaces/{id}/status
     * @method get
     * @custom true
     * @param int $id
     * @return void
     */
    public function getStatus(int $id): void {
        $item = new Workspace();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace');
            return;
        }


        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('workspace_id', $item->id)
            ->find();
        foreach ($deployments as $deployment) {
            $deployment->checkStatus(false);
        }
        $item->deployments = $deployments;

        $item->checkStatus();
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /workspaces/{id}/migration-jobs
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema MigrationJob
     * @return void
     */
    public function getMigrationJobs(int $id): void {
        $this->queryParser->parseFilter("deployment.workspace.id:$id");
        $items = (new MigrationJobModel())->restGet(0, $this->queryParser);
        $this->_setResources($items);
        $this->success();
    }

    /**
     * @route /workspaces/{id}/labels
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema LabelList
     * @return void
     */
    public function updateLabels(int $id): void {
        $item = new Workspace();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace');
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
     * Empty on purpose - and routed anyway.
     *
     * `@ignore true` keeps the verb out of the route generator and swagger, but the init
     * migration wrote `post workspaces` and `put workspaces` into `api_routes` back in 2023 and
     * nothing removed them. Both still answer 200 with an entirely empty body: `success()`
     * is never called, so there is no envelope at all - no status, no error. A generated
     * client calling them is told the write succeeded. Closing them takes a
     * migration that deletes the rows.
     *
     * @return void
     * @ignore true
     */
    public function post() {
    }

    /**
     * Empty on purpose - and routed anyway. See `post()` above.
     *
     * @param $id
     * @return void
     * @ignore true
     */
    public function put($id = 0) {
    }

}
