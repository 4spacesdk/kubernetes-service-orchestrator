<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\DeploymentVolume;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\KubeAuth;
use App\Models\DeploymentVolumeModel;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sPersistentVolumeClaim;

class PersistentVolumeClaimStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::PersistentVolumeClaim;
    }

    public function getName(): string {
        return 'Persistent Volume Claim';
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
        return DeploymentStepHelper::PersistentVolumeClaim_Found;
    }

    /**
     * @throws \Exception
     */
    public function getPreview(Deployment $deployment): string {
        $resources = $this->getResources($deployment, true);

        $locals = [];
        $remotes = [];

        foreach ($resources as $resource) {
            $locals[] = $resource->toJson();

            if ($resource->exists()) {
                $exiting = $resource->get();
                $remote = json_decode($exiting->toJson(), true);
                unset($remote['metadata']['uid']);
                unset($remote['metadata']['resourceVersion']);
                unset($remote['metadata']['creationTimestamp']);
                unset($remote['metadata']['managedFields']);
                $remotes[] = json_encode($remote);
            }
        }

        return json_encode([
            'local' => $locals,
            'remote' => $remotes,
        ]);
    }

    /**
     * @throws KubernetesAPIException
     * @throws \Exception
     */
    public function getStatus(Deployment $deployment): string {
        $resources = $this->getResources($deployment, true);

        $found = true;
        foreach ($resources as $resource) {
            if (!$resource->exists()) {
                $found = false;
                break;
            }
        }

        return $found ? DeploymentStepHelper::PersistentVolumeClaim_Found : DeploymentStepHelper::PersistentVolumeClaim_NotFound;
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (strlen($deployment->name) == 0) {
            return 'Missing name';
        }

        /** @var DeploymentVolume $deploymentVolumes */
        $deploymentVolumes = (new DeploymentVolumeModel())
            ->where('deployment_id', $deployment->id)
            ->find();
        if ($deploymentVolumes->count() == 0) {
            return 'No volumes found';
        }

        foreach ($deploymentVolumes as $deploymentVolume) {
            if ($error = $deploymentVolume->validate()) {
                return $error;
            }
        }

        return null;
    }

    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        $resources = $this->getResources($deployment, true);
        foreach ($resources as $resource) {
            $resource->createOrUpdate();
        }
    }

    public function startTerminateCommand(Deployment $deployment): void {
        $resources = $this->getResources($deployment, true);
        foreach ($resources as $resource) {
            $resource->synced();
            $resource->delete();
        }
    }

    public function getKubernetesEvents(Deployment $deployment): array {
        return [];
    }

    public function getKubernetesStatus(Deployment $deployment): array {
        return [];
    }

    /**
     * @return K8sPersistentVolumeClaim[]
     * @throws \Exception
     */
    private function getResources(Deployment $deployment, bool $auth = false): array {
        /** @var DeploymentVolume $deploymentVolumes */
        $deploymentVolumes = (new DeploymentVolumeModel())
            ->where('deployment_id', $deployment->id)
            ->find();

        $resources = [];

        foreach ($deploymentVolumes as $deploymentVolume) {
            $resource = new K8sPersistentVolumeClaim();
            $resource
                ->setName($deployment->name)
                ->setNamespace($deployment->namespace)
                ->setCapacity($deploymentVolume->capacity)
                ->setAccessModes(['ReadWriteMany'])
                ->setStorageClass('');

            $resources[] = $resource;
        }

        if ($auth) {
            $auth = new KubeAuth();
            $cluster = $auth->authenticate();
            foreach ($resources as $resource) {
                $resource->onCluster($cluster);
            }
        }

        return $resources;
    }
}
