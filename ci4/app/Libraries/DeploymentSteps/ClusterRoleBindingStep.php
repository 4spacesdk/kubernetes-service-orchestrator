<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\KubeAuth;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Instances\Subject;
use RenokiCo\PhpK8s\Kinds\K8sClusterRole;
use RenokiCo\PhpK8s\Kinds\K8sClusterRoleBinding;

class ClusterRoleBindingStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::ClusterRoleBinding;
    }

    public function getName(): string {
        return 'Cluster Role Binding';
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

    public function getSuccessStatus(): string {
        return DeploymentStepHelper::ClusterRoleBinding_Found;
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
            return DeploymentStepHelper::ClusterRoleBinding_Found;
        } else {
            return DeploymentStepHelper::ClusterRoleBinding_NotFound;
        }
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (strlen($deployment->name) == 0) {
            return 'Missing name';
        }
        if (strlen($deployment->namespace) == 0) {
            return 'Missing namespace';
        }

        $namespaceStep = new NamespaceStep();
        if ($namespaceStep->getStatus($deployment) != DeploymentStepHelper::Namespace_Found) {
            return 'Missing Namespace';
        }
        $clusterRoleStep = new ClusterRoleStep();
        if ($clusterRoleStep->getStatus($deployment) != DeploymentStepHelper::ClusterRole_Found) {
            return 'Missing Cluster Role';
        }
        $serviceAccount = new ServiceAccountStep();
        if ($serviceAccount->getStatus($deployment) != DeploymentStepHelper::ServiceAccount_Found) {
            return 'Missing Service Account';
        }
        return null;
    }

    public function startDeployCommand(Deployment $deployment): void {
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
    private function getResource(Deployment $deployment, bool $auth = false): K8sClusterRoleBinding {
        $resource = new K8sClusterRoleBinding();
        $resource
            ->setName("{$deployment->name}.{$deployment->namespace}")
            ->addSubject(
                (new Subject())
                    ->setAttribute('kind', 'ServiceAccount')
                    ->setAttribute('name', $deployment->name)
                    ->setAttribute('namespace', $deployment->namespace)
            )
            ->setRole(
                (new K8sClusterRole())
                    ->setName($deployment->name)
            );

        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }

}
