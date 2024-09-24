<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\KubeAuth;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sNamespace;
use RenokiCo\PhpK8s\Kinds\K8sServiceAccount;

class NamespaceStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::Namespace;
    }

    public function getName(): string {
        return 'Namespace';
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
        return false;
    }

    public function hasKubernetesEvents(): bool {
        return false;
    }

    public function hasKubernetesStatus(): bool {
        return true;
    }

    public function getSuccessStatus(Deployment $deployment): string {
        return DeploymentStepHelper::Namespace_Found;
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
            unset($remote['metadata']['labels']);
            unset($remote['metadata']['annotations']);
            unset($remote['metadata']['managedFields']);
            unset($remote['spec']);
            unset($remote['status']);
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
            return DeploymentStepHelper::Namespace_Found;
        } else {
            return DeploymentStepHelper::Namespace_NotFound;
        }
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (strlen($deployment->namespace) == 0) {
            return 'Missing namespace';
        }

        return null;
    }

    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        $resource = $this->getResource($deployment, true);
        $resource->createOrUpdate();
    }

    public function startTerminateCommand(Deployment $deployment): void {
        throw new \Exception('Namespaces must be deleted manually');
    }

    public function getKubernetesEvents(Deployment $deployment): array {
        return [];
    }

    public function getKubernetesStatus(Deployment $deployment): array {
        /** @var K8sNamespace $resource */
        $resource = $this->getResource($deployment, true)->get();
        $status = $resource->getAttribute('status');
        return $status;
    }

    /**
     * @throws \Exception
     */
    private function getResource(Deployment $deployment, bool $auth = false): K8sNamespace {
        $resource = new K8sNamespace();
        $resource
            ->setName($deployment->namespace);

        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }

}
