<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\KubeAuth;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sService;

class ServiceStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::Service;
    }

    public function getName(): string {
        return 'Service';
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
        return DeploymentStepHelper::Service_Found;
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
            foreach ($remote['spec']['ports'] as &$port) {
                unset($port['nodePort']);
            }
            unset($remote['spec']['clusterIP']);
            unset($remote['spec']['clusterIPs']);
            unset($remote['spec']['sessionAffinity']);
            unset($remote['spec']['externalTrafficPolicy']);
            unset($remote['spec']['ipFamilies']);
            unset($remote['spec']['ipFamilyPolicy']);
            unset($remote['spec']['internalTrafficPolicy']);
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
            return DeploymentStepHelper::Service_Found;
        } else {
            return DeploymentStepHelper::Service_NotFound;
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

        $deploymentStep = new DeploymentStep();
        if ($deploymentStep->getStatus($deployment) != DeploymentStepHelper::Deployment_Found) {
            return 'Missing Deployment';
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
    private function getResource(Deployment $deployment, bool $auth = false): K8sService {
        $spec = $deployment->findDeploymentSpecification();

        $resource = new K8sService();
        $resource
            ->setName($deployment->name)
            ->setNamespace($deployment->namespace)
            ->setSpec('type', 'NodePort')
            ->setSelectors([
                'app' => $deployment->name,
            ])
            ->setAnnotations($spec->getServiceAnnotations())
            ->setPorts($spec->getServicePorts());

        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }

}
