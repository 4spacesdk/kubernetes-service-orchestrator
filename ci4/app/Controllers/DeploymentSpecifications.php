<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\DeploymentSpecification;
use App\Entities\DeploymentSpecificationClusterRoleRule;
use App\Entities\DeploymentSpecificationEnvironmentVariable;
use App\Entities\DeploymentSpecificationIngress;
use App\Entities\DeploymentSpecificationPostCommand;
use App\Entities\DeploymentSpecificationServicePort;
use App\Interfaces\ClusterRoleRuleList;
use App\Interfaces\EnvironmentVariableList;
use App\Interfaces\IngressList;
use App\Interfaces\PostCommandList;
use App\Interfaces\ServicePortList;
use App\Libraries\GoogleCloud\GoogleCloudArtifactRegistry;
use DebugTool\Data;
use Google\ApiCore\ApiException;
use Google\ApiCore\ValidationException;

class DeploymentSpecifications extends ResourceController {

    /**
     * @route /deployment-specifications/{id}/tags
     * @method get
     * @custom true
     * @responseSchema DeploymentSpecificationTagsGetResponse
     * @param int $id
     */
    public function getTags(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            $item->container_image->find();

            $registry = new GoogleCloudArtifactRegistry($item->container_image->url);
            try {
                $tags = $registry->getTags();
            } catch (ApiException|ValidationException $e) {
                Data::debug($e->getMessage());
                $tags = [];
            }
            Data::set('resource', [
                'tags' => $tags,
            ]);
        }
        $this->success();
    }

    /**
     * @route /deployment-specifications/{id}/post-commands
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema PostCommandList
     * @return void
     */
    public function updatePostCommands(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var PostCommandList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentSpecificationPostCommand();
            $values->all = array_map(
                fn($data) => DeploymentSpecificationPostCommand::Create(
                    $data->name,
                    $data->command,
                    $data->allPods
                ),
                $body->values
            );
            $item->updatePostCommands($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-specifications/{id}/environment-variables
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema EnvironmentVariableList
     * @return void
     */
    public function updateEnvironmentVariables(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var EnvironmentVariableList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentSpecificationEnvironmentVariable();
            $values->all = array_map(
                fn($data) => DeploymentSpecificationEnvironmentVariable::Create($data->name, $data->value),
                $body->values
            );
            $item->updateEnvironmentVariables($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-specifications/{id}/service-ports
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema ServicePortList
     * @return void
     */
    public function updateServicePorts(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var ServicePortList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentSpecificationServicePort();
            $values->all = array_map(
                fn($data) => DeploymentSpecificationServicePort::Create(
                    $data->protocol,
                    $data->name,
                    $data->port,
                    $data->targetPort
                ),
                $body->values
            );
            $item->updateServicePorts($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-specifications/{id}/ingresses
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema IngressList
     * @return void
     */
    public function updateIngresses(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var IngressList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentSpecificationIngress();
            $values->all = array_map(
                fn($data) => DeploymentSpecificationIngress::Create(
                    $data->ingressClass,
                    $data->proxyBodySize,
                    $data->proxyConnectTimeout,
                    $data->proxyReadTimeout,
                    $data->proxySendTimeout,
                    $data->sslRedirect,
                    $data->enableTls,
                    $data->paths
                ),
                $body->values
            );
            $item->updateIngresses($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-specifications/{id}/cluster-role-rules
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema ClusterRoleRuleList
     * @return void
     */
    public function updateClusterRoleRules(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var ClusterRoleRuleList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentSpecificationClusterRoleRule();
            $values->all = array_map(
                fn($data) => DeploymentSpecificationClusterRoleRule::Create(
                    $data->apiGroup,
                    $data->resource,
                    $data->verbs
                ),
                $body->values
            );
            $item->updateClusterRoleRules($values);
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
