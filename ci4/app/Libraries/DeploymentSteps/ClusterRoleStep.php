<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\KubeAuth;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Instances\Rule;
use RenokiCo\PhpK8s\Kinds\K8sClusterRole;
use RenokiCo\PhpK8s\Kinds\K8sClusterRoleBinding;
use RenokiCo\PhpK8s\Kinds\K8sCronJob;
use RenokiCo\PhpK8s\Kinds\K8sDeployment;
use RenokiCo\PhpK8s\Kinds\K8sEvent;
use RenokiCo\PhpK8s\Kinds\K8sIngress;
use RenokiCo\PhpK8s\Kinds\K8sJob;
use RenokiCo\PhpK8s\Kinds\K8sNamespace;
use RenokiCo\PhpK8s\Kinds\K8sPersistentVolume;
use RenokiCo\PhpK8s\Kinds\K8sPersistentVolumeClaim;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\Kinds\K8sService;
use RenokiCo\PhpK8s\Kinds\K8sServiceAccount;

class ClusterRoleStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::ClusterRole;
    }

    public function getName(): string {
        return 'Cluster Role';
    }

    public function hasPreviewCommand(): bool {
        return true;
    }

    public function hasStatusCommand(): bool {
        return true;
    }

    public function hasDeployCommand(): bool {
        return true;
    }

    public function hasTerminateCommand(): bool {
        return true;
    }

    public function hasKubernetesEvents(): bool {
        return false;
    }

    public function hasKubernetesStatus(): bool {
        return false;
    }

    public function getSuccessStatus(Deployment $deployment): string {
        return DeploymentStepHelper::ClusterRole_Found;
    }

    /**
     * @throws \Exception
     */
    public function getPreview(Deployment $deployment): string {
        $resource = $this->getResource($deployment, true);
        $local = $resource->toJson();

        if ($resource->exists()) {
            $exiting = $resource->get();
            $remote = json_decode($exiting->toJson(), true);
            unset($remote['metadata']['uid']);
            unset($remote['metadata']['resourceVersion']);
            unset($remote['metadata']['creationTimestamp']);
            unset($remote['metadata']['managedFields']);
            $remote = json_encode($remote);
        }

        return json_encode([
            'local' => $local,
            'remote' => $remote ?? null,
        ]);
    }

    /**
     * @throws KubernetesAPIException
     * @throws \Exception
     */
    public function getStatus(Deployment $deployment): string {
        $resource = $this->getResource($deployment, true);
        if ($resource->exists()) {
            return DeploymentStepHelper::ClusterRole_Found;
        } else {
            return DeploymentStepHelper::ClusterRole_NotFound;
        }
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (strlen($deployment->name) == 0) {
            return 'Missing name';
        }

        return null;
    }

    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        $resource = $this->getResource($deployment, true);
        $resource->createOrUpdate();
    }

    public function startTerminateCommand(Deployment $deployment): void {
        $resource = $this->getResource($deployment, true);
        $resource->synced();
        $resource->delete();
    }

    public function getKubernetesEvents(Deployment $deployment): array {
        return [];
    }

    public function getKubernetesStatus(Deployment $deployment): array {
        return [];
    }

    /**
     * @throws \Exception
     */
    private function getResource(Deployment $deployment, bool $auth = false): K8sClusterRole {
        $spec = $deployment->findDeploymentSpecification();

        $resource = new K8sClusterRole();
        $resource->setName($deployment->name);

        $rules = [];
        $spec->deployment_specification_cluster_role_rules->find();
        foreach ($spec->deployment_specification_cluster_role_rules as $clusterRoleRule) {
            $rule = new Rule();
            $rule->addApiGroup($clusterRoleRule->api_group);
            $rule->addResource($clusterRoleRule->resource);
            $rule->addVerbs(explode(',', $clusterRoleRule->verbs));
            $rules[] = $rule;
        }
        $resource->setRules($rules);

        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }
}
