<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\Deployment;
use App\Entities\DeploymentPackage;
use App\Entities\DeploymentSpecification;
use App\Entities\Workspace;
use App\Exceptions\ValidationException;
use App\Models\DeploymentModel;
use App\Models\DomainModel;
use App\Models\MigrationJobModel;

class Workspaces extends ResourceController {

    /**
     * @route /workspaces/create
     * @method post
     * @custom true
     * @parameter int $deploymentPackageId parameterType=query
     * @parameter string $name parameterType=query
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
     * @return void
     * @responseSchema Deployment
     */
    public function createDeplyment(int $id = 0): void {
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
            $deployment = $item->prepareDeploymentFromSpecification($deploymentSpecification);
            $deployment->save();
            $this->_setResource($deployment);
        } catch (ValidationException $e) {
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
        if ($item->exists()) {
            $item->updateName($value);
        }
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
        if ($item->exists()) {
            try {
                $item->updateIngress(
                    $this->request->getGet('domainId'),
                    $this->request->getGet('subdomain'),
                    $this->request->getGet('aliases')
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
        if ($item->exists()) {
            $item->updateEmailServiceId($value);
        }
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
        if ($item->exists()) {
            $item->updateDatabaseServiceId($value);
        }
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
        if ($item->exists()) {
            $errors = $item->deploy();
            if ($errors) {
                $this->fail($errors);
                return;
            }
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
        if ($item->exists()) {
            $errors = $item->terminate();
            if ($errors) {
                $this->fail($errors);
                return;
            }
        }
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
        if ($item->exists()) {
            $item->checkStatus();
        }
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
     * @route /workspaces/{id}/requestSupportLogin
     * @method get
     * @param int $id parameterType=path
     * @parameter bool $redirect parameterType=query
     * @custom true
     * @responseSchema StringInterface
     */
    public function requestSupportLogin(int $id): void {
        $item = new Workspace();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown workspace');
            return;
        }

//        // TODO Disabled for now
//        /** @var Deployment $deployment */
//        $deployment = (new DeploymentModel())
//            ->includeRelated(DomainModel::class)
//            ->where('workspace_id', $id)
//            ->where('spec', DeploymentSpecHelper::Klartboard_Backend)
//            ->find();
//
//        if (!$deployment->exists()) {
//            $this->fail('Workspace deployment missing');
//            return;
//        }
//
//        if (!$deployment->domain->exists()) {
//            $this->fail('Workspace deployment missing domain');
//            return;
//        }

//        $api = new KlartboardBackendApi($deployment);
//        $token = $api->requestSupportToken();
//        if (!$token) {
//            $this->fail('Failed to contact workspace. Try again later.');
//            return;
//        }
//
//        $url = $deployment->getUrl(true, "/auth/login/support_login?token={$token}&reason=SentFromDeployService");
//
//        if ($this->request->getGet('redirect')) {
//            $this->response->redirect($url);
//            return;
//        }
//
//        Data::set('resource', [
//            'value' => $url,
//        ]);

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
