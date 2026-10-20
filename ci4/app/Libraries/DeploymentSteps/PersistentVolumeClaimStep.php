<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\DeploymentSpecificationVolume;
use App\Entities\DeploymentVolume;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\KubeHelper;
use App\Models\DeploymentSpecificationVolumeModel;
use App\Models\DeploymentVolumeModel;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sEvent;
use RenokiCo\PhpK8s\Kinds\K8sPersistentVolumeClaim;

class PersistentVolumeClaimStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::PersistentVolumeClaim;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Deployment;
    }

    public function getName(): string {
        return 'Persistent Volume Claim';
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
        return true;
    }

    public function hasKubernetesStatus(): bool {
        return true;
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
        $statuses = [];
        foreach ($this->getResources($deployment, true) as $resource) {
            $live = KubeHelper::LiveOrNull($resource);
            $statuses[] = $live !== null
                ? self::StatusOf($live)
                : DeploymentStepHelper::PersistentVolumeClaim_NotFound;
        }

        // The worst of them: a claim that is found says nothing about one that is not.
        foreach ([
            DeploymentStepHelper::PersistentVolumeClaim_Terminating,
            DeploymentStepHelper::PersistentVolumeClaim_Lost,
            DeploymentStepHelper::PersistentVolumeClaim_NotFound,
            DeploymentStepHelper::PersistentVolumeClaim_Pending,
        ] as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }

        return DeploymentStepHelper::PersistentVolumeClaim_Found;
    }

    /**
     * A claim that is there can still not be used: deleted and held back by its pods, waiting for
     * a volume, or without the one it had. `found` is only for one that is bound.
     *
     * @param array<string, mixed> $claim As the cluster has it
     */
    public static function StatusOf(array $claim): string {
        if (!empty($claim['metadata']['deletionTimestamp'])) {
            return DeploymentStepHelper::PersistentVolumeClaim_Terminating;
        }

        return match ($claim['status']['phase'] ?? null) {
            'Bound' => DeploymentStepHelper::PersistentVolumeClaim_Found,
            'Lost' => DeploymentStepHelper::PersistentVolumeClaim_Lost,
            default => DeploymentStepHelper::PersistentVolumeClaim_Pending,
        };
    }

    /**
     * What the claim is doing, in the words of the questions it raises: is it bound, to which
     * volume - kso's own or one its storage class made - and is it being deleted, held back by
     * which pods. A pod holds its claim for as long as it exists, a finished one too.
     *
     * @param array<string, mixed> $claim As the cluster has it
     * @param list<array<string, mixed>> $pods The pods in its namespace
     * @return array<string, mixed>
     */
    public static function Describe(array $claim, array $pods, string $ksosVolume): array {
        $name = (string) ($claim['metadata']['name'] ?? '');
        $volume = $claim['spec']['volumeName'] ?? null;

        $usedBy = [];
        foreach ($pods as $pod) {
            foreach ($pod['spec']['volumes'] ?? [] as $podVolume) {
                if (($podVolume['persistentVolumeClaim']['claimName'] ?? null) === $name) {
                    $usedBy[] = ($pod['metadata']['name'] ?? '') . ' (' . ($pod['status']['phase'] ?? 'Unknown') . ')';
                    break;
                }
            }
        }

        return [
            'claim' => ($claim['metadata']['namespace'] ?? '') . "/{$name}",
            'status' => self::StatusOf($claim),
            'phase' => $claim['status']['phase'] ?? null,
            'terminatingSince' => $claim['metadata']['deletionTimestamp'] ?? null,
            'finalizers' => $claim['metadata']['finalizers'] ?? [],
            'volume' => $volume,
            'onKsosVolume' => $volume !== null && $volume === $ksosVolume,
            'storageClass' => $claim['spec']['storageClassName'] ?? null,
            'capacity' => $claim['status']['capacity']['storage'] ?? $claim['spec']['resources']['requests']['storage'] ?? null,
            'usedBy' => $usedBy,
        ];
    }

    /**
     * Whether the deployment's claim is in the cluster. Asked when a volume is edited: the
     * claim's spec cannot change, so the edit is refused before it is stored rather than
     * failing the next deploy with a raw 422.
     *
     * @throws \Exception When the cluster cannot be reached.
     */
    public function claimExists(Deployment $deployment): bool {
        $claim = new K8sPersistentVolumeClaim();
        $claim->setName($deployment->name)
            ->setNamespace($deployment->namespace)
            ->onCluster((new KubeAuth())->authenticate());

        return $claim->exists();
    }

    /**
     * Whether the claim is there and bound to kso's own disk - not to one its storage class
     * made. Only then is the disk's source fixed: kso makes its disk again when it is not used.
     *
     * @throws \Exception When the cluster cannot be reached.
     */
    public function claimIsOnKsosVolume(Deployment $deployment): bool {
        $claim = new K8sPersistentVolumeClaim();
        $claim->setName($deployment->name)
            ->setNamespace($deployment->namespace)
            ->onCluster((new KubeAuth())->authenticate());

        return $claim->exists()
            && $claim->get()->getAttribute('spec.volumeName') === PersistentVolumeStep::VolumeName($deployment);
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
            $this->apply($resource);
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
        $events = [];
        foreach ($this->getResources($deployment, true) as $resource) {
            if (!$resource->exists()) {
                continue;
            }
            /** @var K8sEvent $event */
            foreach ($resource->getEvents() as $event) {
                $events[] = [
                    'count' => $event->getAttribute('count'),
                    'type' => $event->getAttribute('type'),
                    'reason' => $event->getAttribute('reason'),
                    'date' => date('Y-m-d H:i:s', strtotime_($event->getAttribute('lastTimestamp'))),
                    'from' => $event->getAttribute('source')['component'] ?? '',
                    'message' => $event->getAttribute('message'),
                ];
            }
        }

        return $events;
    }

    /**
     * See `Describe()`. One claim is shown as itself, more as a list - the deployment's own and
     * its specification's collide on one name, which the editor refuses.
     */
    public function getKubernetesStatus(Deployment $deployment): array {
        $cluster = (new KubeAuth())->authenticate();
        $pods = null;

        $claims = [];
        foreach ($this->getResources($deployment, true) as $resource) {
            $live = KubeHelper::LiveOrNull($resource);
            if ($live === null) {
                $claims[] = [
                    'claim' => "{$deployment->namespace}/{$resource->getName()}",
                    'status' => DeploymentStepHelper::PersistentVolumeClaim_NotFound,
                ];
                continue;
            }
            $pods ??= array_map(fn ($pod) => $pod->toArray(), iterator_to_array($cluster->getAllPods((string) $deployment->namespace)));
            $claims[] = self::Describe($live, $pods, PersistentVolumeStep::VolumeName($deployment));
        }

        return count($claims) === 1 ? $claims[0] : $claims;
    }

    /**
     * @return K8sPersistentVolumeClaim[]
     * @throws \Exception
     */
    protected function getResources(Deployment $deployment, bool $auth = false): array {
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
                ->setStorageClass($deploymentVolume->storage_class);

            $resources[] = $resource;
        }

        /** @var DeploymentSpecificationVolume $deploymentSpecificationVolumes */
        $deploymentSpecificationVolumes = (new DeploymentSpecificationVolumeModel())
            ->where('deployment_specification_id', $deployment->findDeploymentSpecification()->id)
            ->find();
        foreach ($deploymentSpecificationVolumes as $deploymentSpecificationVolume) {
            $resource = new K8sPersistentVolumeClaim();
            $resource
                ->setName($deployment->name)
                ->setNamespace($deployment->namespace)
                ->setCapacity($deploymentSpecificationVolume->capacity)
                ->setAccessModes(['ReadWriteMany'])
                ->setStorageClass($deploymentSpecificationVolume->storage_class);

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
