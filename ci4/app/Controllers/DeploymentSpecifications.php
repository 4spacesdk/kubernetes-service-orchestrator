<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\DeploymentSpecification;
use App\Entities\DeploymentSpecificationClusterRoleRule;
use App\Entities\DeploymentSpecificationCronJob;
use App\Entities\DeploymentSpecificationDeploymentAnnotation;
use App\Entities\DeploymentSpecificationEnvironmentVariable;
use App\Entities\DeploymentSpecificationHttpProxyRoute;
use App\Entities\DeploymentSpecificationIngress;
use App\Entities\DeploymentSpecificationPostCommand;
use App\Entities\DeploymentSpecificationPostUpdateAction;
use App\Entities\DeploymentSpecificationRoleRule;
use App\Entities\DeploymentSpecificationVolume;
use App\Entities\Label;
use App\Entities\DeploymentSpecificationQuickCommand;
use App\Entities\DeploymentSpecificationServiceAnnotation;
use App\Entities\DeploymentSpecificationServicePort;
use App\Entities\DeploymentSpecificationInitContainer;
use App\Interfaces\ClusterRoleRuleList;
use App\Interfaces\DeploymentAnnotationList;
use App\Interfaces\DeploymentSpecificationInitContainersRequest;
use App\Interfaces\DeploymentSpecificationVolumeList;
use App\Interfaces\EnvironmentVariableList;
use App\Interfaces\HttpProxyRouteList;
use App\Interfaces\IngressList;
use App\Interfaces\IntArrayInterface;
use App\Interfaces\LabelList;
use App\Interfaces\PostCommandList;
use App\Interfaces\QuickCommandList;
use App\Interfaces\RoleRuleList;
use App\Interfaces\ServiceAnnotationList;
use App\Interfaces\ServicePortList;
use DebugTool\Data;

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

            Data::set('resource', [
                'tags' => $item->container_image->getTags(),
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
                    $data->allPods,
                    $data->container
                ),
                $body->values
            );
            $item->updatePostCommands($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-specifications/{id}/quick-commands
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema QuickCommandList
     * @return void
     */
    public function updateQuickCommands(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var QuickCommandList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentSpecificationQuickCommand();
            $values->all = array_map(
                fn($data) => DeploymentSpecificationQuickCommand::Create(
                    $data->name,
                    $data->command,
                ),
                $body->values
            );
            $item->updateQuickCommands($values);
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
                    $data->targetPort ?? $data->port
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
                    $data->paths,
                    $data->annotations
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
     * @route /deployment-specifications/{id}/role-rules
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema ClusterRoleRuleList
     * @return void
     */
    public function updateRoleRules(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var RoleRuleList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentSpecificationRoleRule();
            $values->all = array_map(
                fn($data) => DeploymentSpecificationRoleRule::Create(
                    $data->apiGroup,
                    $data->resource,
                    $data->verbs
                ),
                $body->values
            );
            $item->updateRoleRules($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-specifications/{id}/service-annotations
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema ServiceAnnotationList
     * @return void
     */
    public function updateServiceAnnotations(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var ServiceAnnotationList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentSpecificationServiceAnnotation();
            $values->all = array_map(
                fn($data) => DeploymentSpecificationServiceAnnotation::Create($data->name, $data->value),
                $body->values
            );
            $item->updateServiceAnnotations($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-specifications/{id}/deployment-annotations
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema DeploymentAnnotationList
     * @return void
     */
    public function updateDeploymentAnnotations(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var DeploymentAnnotationList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentSpecificationDeploymentAnnotation();
            $values->all = array_map(
                fn($data) => DeploymentSpecificationDeploymentAnnotation::Create($data->level, $data->name, $data->value),
                $body->values
            );
            $item->updateDeploymentAnnotations($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-specifications/{id}/init-containers
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema DeploymentSpecificationInitContainersRequest
     * @return void
     */
    public function updateInitContainers(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var DeploymentSpecificationInitContainersRequest $body */
            $body = $this->request->getJSON();

            $values = new DeploymentSpecificationInitContainer();
            $values->all = array_map(
                fn($item) => DeploymentSpecificationInitContainer::Create(
                    $item->initContainerId,
                    $item->position,
                    $item->includeInMigrationJob
                ),
                $body->values,
                array_keys($body->values)
            );

            $item->updateInitContainers($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-specifications/{id}/post-update-actions
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema IntArrayInterface
     * @return void
     */
    public function updatePostUpdateActions(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var IntArrayInterface $body */
            $body = $this->request->getJSON();

            $values = new DeploymentSpecificationPostUpdateAction();
            $pos = 0;
            $values->all = array_map(
                fn($postUpdateActionId, $i) => DeploymentSpecificationPostUpdateAction::Create($postUpdateActionId, $pos + $i),
                $body->values,
                array_keys($body->values)
            );

            $item->updatePostUpdateActions($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-specifications/{id}/labels
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema LabelList
     * @return void
     */
    public function updateLabels(int $id): void {
        $item = new DeploymentSpecification();
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
     * @route /deployment-specifications/{id}/cron-jobs
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema IntArrayInterface
     * @return void
     */
    public function updateCronJobs(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var IntArrayInterface $body */
            $body = $this->request->getJSON();

            $values = new DeploymentSpecificationCronJob();
            $pos = 0;
            $values->all = array_map(
                fn($cronJobId, $i) => DeploymentSpecificationCronJob::Create($cronJobId, $pos + $i),
                $body->values,
                array_keys($body->values)
            );

            $item->updateCronJobs($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-specifications/{id}/http-proxy-routes
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema HttpProxyRouteList
     * @return void
     */
    public function updateDeploymentHttpProxyRoutes(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var HttpProxyRouteList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentSpecificationHttpProxyRoute();
            $values->all = array_map(
                fn($data) => DeploymentSpecificationHttpProxyRoute::Create($data->path, $data->port, $data->protocol),
                $body->values
            );
            $item->updateHttpProxyRoutes($values);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment-specifications/{id}/volumes
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema DeploymentSpecificationVolumeList
     * @return void
     */
    public function updateVolumes(int $id): void {
        $item = new DeploymentSpecification();
        $item->find($id);
        if ($item->exists()) {
            /** @var DeploymentSpecificationVolumeList $body */
            $body = $this->request->getJSON();
            $values = new DeploymentSpecificationVolume();
            $values->all = array_map(
                fn($data) => DeploymentSpecificationVolume::Create(
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
            $item->updateVolumes($values);
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
