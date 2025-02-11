<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\DeploymentSpecificationVolume;
use App\Entities\DeploymentVolume;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use App\Libraries\Kubernetes\KubeAuth;
use App\Models\DeploymentSpecificationVolumeModel;
use App\Models\DeploymentVolumeModel;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sPersistentVolume;

class PersistentVolumeStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::PersistentVolume;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Deployment;
    }

    public function getName(): string {
        return 'Persistent Volume';
    }

    public function getTriggers(): array {
        return [
            DeploymentStepTriggers::Deployment_Volume_Updated,
        ];
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
        return DeploymentStepHelper::PersistentVolume_Found;
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

        return $found ? DeploymentStepHelper::PersistentVolume_Found : DeploymentStepHelper::PersistentVolume_NotFound;
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (strlen($deployment->name) == 0) {
            return 'Missing name';
        }

        /** @var DeploymentVolume $deploymentVolumes */
        $deploymentVolumes = (new DeploymentVolumeModel())
            ->where('deployment_id', $deployment->id)
            ->find();
        /** @var DeploymentSpecificationVolume $deploymentSpecificationVolumes */
        $deploymentSpecificationVolumes = (new DeploymentSpecificationVolumeModel())
            ->where('deployment_specification_id', $deployment->findDeploymentSpecification()->id)
            ->find();
        if ($deploymentVolumes->count() == 0 && $deploymentSpecificationVolumes->count() == 0) {
            return 'No volumes found';
        }

        foreach ($deploymentVolumes as $deploymentVolume) {
            if ($error = $deploymentVolume->validate()) {
                return $error;
            }
        }
        foreach ($deploymentSpecificationVolumes as $deploymentSpecificationVolume) {
            if ($error = $deploymentSpecificationVolume->validate()) {
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
     * @return K8sPersistentVolume[]
     * @throws \Exception
     */
    private function getResources(Deployment $deployment, bool $auth = false): array {
        /** @var DeploymentVolume $deploymentVolumes */
        $deploymentVolumes = (new DeploymentVolumeModel())
            ->where('deployment_id', $deployment->id)
            ->find();

        $resources = [];

        foreach ($deploymentVolumes as $deploymentVolume) {
            $resource = new K8sPersistentVolume();
            $resource
                ->setName("{$deployment->namespace}-{$deployment->name}")
                ->setCapacity($deploymentVolume->capacity)
                ->setSpec('volumeMode', $deploymentVolume->volume_mode)
                ->setAccessModes(['ReadWriteMany'])
                ->setSpec('nfs', [
                    'server' => $deploymentVolume->nfs_server,
                    'path' => $deploymentVolume->nfs_path,
                ])
                ->setSpec('persistentVolumeReclaimPolicy', $deploymentVolume->reclaim_policy);

            $resources[] = $resource;
        }

        /** @var DeploymentSpecificationVolume $deploymentSpecificationVolumes */
        $deploymentSpecificationVolumes = (new DeploymentSpecificationVolumeModel())
            ->where('deployment_specification_id', $deployment->findDeploymentSpecification()->id)
            ->find();
        foreach ($deploymentSpecificationVolumes as $deploymentSpecificationVolume) {
            $resource = new K8sPersistentVolume();
            $resource
                ->setName("{$deployment->namespace}-{$deployment->name}")
                ->setCapacity($deploymentSpecificationVolume->capacity)
                ->setSpec('volumeMode', $deploymentSpecificationVolume->volume_mode)
                ->setAccessModes(['ReadWriteMany'])
                ->setSpec('nfs', [
                    'server' => $deploymentSpecificationVolume->nfs_server,
                    'path' => $deploymentSpecificationVolume->nfs_path,
                ])
                ->setSpec('persistentVolumeReclaimPolicy', $deploymentSpecificationVolume->reclaim_policy);

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
