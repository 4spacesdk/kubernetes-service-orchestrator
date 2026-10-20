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
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sPersistentVolume;

class PersistentVolumeStep extends BaseDeploymentStep {

    /** Microseconds between two looks for a removed volume. An unbound one goes at once. */
    public const int GoneInterval = 200000;

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
        return true;
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
        $statuses = [];
        foreach ($this->getResources($deployment, true) as $resource) {
            $live = KubeHelper::LiveOrNull($resource);
            $statuses[] = $live !== null
                ? self::StatusOf($live, $resource->toArray())
                : DeploymentStepHelper::PersistentVolume_NotFound;
        }

        foreach ([
            DeploymentStepHelper::PersistentVolume_SourceDiffers,
            DeploymentStepHelper::PersistentVolume_Failed,
            DeploymentStepHelper::PersistentVolume_NotFound,
            DeploymentStepHelper::PersistentVolume_Released,
        ] as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }

        return DeploymentStepHelper::PersistentVolume_Found;
    }

    /**
     * A volume that mounts something other than kso has says so first: a claim bound to it does
     * not get what kso was told, and the next deploy makes it again or - when it is bound - stops.
     *
     * @param array<string, mixed> $live As the cluster has it
     * @param array<string, mixed> $manifest As kso would make it
     */
    public static function StatusOf(array $live, array $manifest): string {
        if (self::Source($live) !== self::Source($manifest)) {
            return DeploymentStepHelper::PersistentVolume_SourceDiffers;
        }

        return match ($live['status']['phase'] ?? null) {
            'Released' => DeploymentStepHelper::PersistentVolume_Released,
            'Failed' => DeploymentStepHelper::PersistentVolume_Failed,
            default => DeploymentStepHelper::PersistentVolume_Found,
        };
    }

    /**
     * What the volume is doing: bound or not, to which claim, and what it mounts - beside what kso
     * has, when the two are not the same.
     *
     * @param array<string, mixed> $live As the cluster has it
     * @param array<string, mixed> $manifest As kso would make it
     * @return array<string, mixed>
     */
    public static function Describe(array $live, array $manifest): array {
        $claim = $live['spec']['claimRef'] ?? null;
        $matches = self::Source($live) === self::Source($manifest);

        return array_filter([
            'volume' => $live['metadata']['name'] ?? null,
            'status' => self::StatusOf($live, $manifest),
            'phase' => $live['status']['phase'] ?? null,
            'claim' => $claim ? ($claim['namespace'] ?? '') . '/' . ($claim['name'] ?? '') : null,
            'reclaimPolicy' => $live['spec']['persistentVolumeReclaimPolicy'] ?? null,
            'storageClass' => $live['spec']['storageClassName'] ?? null,
            'mounts' => self::Readable($live),
            'sourceMatchesKso' => $matches,
            'inKso' => $matches ? null : self::Readable($manifest),
        ], fn ($value) => $value !== null);
    }

    /**
     * @param array<string, mixed> $volume
     * @return array<string, mixed>
     */
    private static function Readable(array $volume): array {
        $spec = $volume['spec'] ?? [];

        return array_filter([
            'nfs' => $spec['nfs'] ?? null,
            'csi' => isset($spec['csi']) ? array_filter([
                'driver' => $spec['csi']['driver'] ?? null,
                'volumeHandle' => $spec['csi']['volumeHandle'] ?? null,
                'volumeAttributes' => $spec['csi']['volumeAttributes'] ?? null,
            ], fn ($value) => $value !== null) : null,
            'mountOptions' => $spec['mountOptions'] ?? null,
        ], fn ($value) => $value !== null);
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

    /**
     * A new volume is reserved for the deployment's own claim, so no other claim with the
     * same storage class can take it first.
     *
     * An existing volume keeps the binding it has. An update replaces the whole object, and
     * the manifest has neither the controller's `claimRef` nor its `pv.kubernetes.io/*`
     * annotations - sent without them, a bound volume went `Available` on every deploy of
     * this step and its claim was lost. So both are copied from the live volume first. A
     * `claimRef` without the claim's uid is no better: that unbinds it too.
     *
     * A volume's source cannot be changed once it is made. One whose source is no longer the
     * volume's is made again when no claim is bound to it - removing the object leaves the data
     * where it is, as the reclaim policy is only acted on for a disk a provisioner made. One that
     * is bound stops the deploy and says why, rather than the api server's 422.
     */
    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        $resources = $this->getResources($deployment, true);
        foreach ($resources as $resource) {
            if ($resource->exists() && $this->replacesAnUnusedVolume($resource)) {
                $resource->setSpec('claimRef', [
                    'namespace' => $deployment->namespace,
                    'name' => $deployment->name,
                ]);
            } elseif ($resource->exists()) {
                $live = $resource->get();
                if ($claimRef = $live->getAttribute('spec.claimRef')) {
                    $resource->setSpec('claimRef', $claimRef);
                }
                $binding = array_filter(
                    $live->getAnnotations(),
                    fn ($name) => str_starts_with($name, 'pv.kubernetes.io/'),
                    ARRAY_FILTER_USE_KEY
                );
                if ($binding) {
                    $resource->setAnnotations(array_merge($resource->getAnnotations(), $binding));
                }
            } else {
                $resource->setSpec('claimRef', [
                    'namespace' => $deployment->namespace,
                    'name' => $deployment->name,
                ]);
            }
            $this->apply($resource);
        }
    }

    /**
     * Removes the live volume when its source is not the one the manifest has and no claim is
     * bound to it. True when it did, so the manifest is applied as a new volume.
     *
     * @throws \Exception When the volume is bound - its source cannot be changed - or does not
     *     go away.
     */
    private function replacesAnUnusedVolume(K8sPersistentVolume $resource): bool {
        $live = $resource->get();
        if (self::Source($live->toArray()) === self::Source($resource->toArray())) {
            return false;
        }

        if ($live->getAttribute('status.phase') === 'Bound') {
            $claim = $live->getAttribute('spec.claimRef', []);
            throw new \Exception(
                "The volume '{$resource->getName()}' is bound to the claim '" . ($claim['namespace'] ?? '') . '/' . ($claim['name'] ?? '') . "'"
                . ', and the source of a volume cannot be changed once it is made.'
                . ' Remove the claim and the volume to have it made again - its data is left where it is.'
            );
        }

        $live->delete();
        for ($attempt = 0; $attempt < 30 && $resource->exists(); $attempt++) {
            usleep(self::GoneInterval);
        }
        if ($resource->exists()) {
            throw new \Exception("The volume '{$resource->getName()}' was removed to be made again, but is still there");
        }

        return true;
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

    /**
     * See `Describe()`. One volume is shown as itself, more as a list.
     */
    public function getKubernetesStatus(Deployment $deployment): array {
        $volumes = [];
        foreach ($this->getResources($deployment, true) as $resource) {
            $live = KubeHelper::LiveOrNull($resource);
            $volumes[] = $live !== null
                ? self::Describe($live, $resource->toArray())
                : ['volume' => $resource->getName(), 'status' => DeploymentStepHelper::PersistentVolume_NotFound];
        }

        return count($volumes) === 1 ? $volumes[0] : $volumes;
    }

    /**
     * The disk's name: one per deployment, so its claim and nothing else can be reserved for it.
     */
    public static function VolumeName(Deployment $deployment): string {
        return "{$deployment->namespace}-{$deployment->name}";
    }

    /**
     * What a disk mounts: its source and the options it is mounted with. Neither can be changed on
     * a disk that is there, so two are compared by this before one is sent over the other. Read
     * from the manifest and from the cluster alike; the attributes are sorted, as the api server
     * keeps them in an order of its own.
     *
     * @param array<string, mixed> $volume
     * @return array<string, mixed>
     */
    public static function Source(array $volume): array {
        $spec = $volume['spec'] ?? [];
        $attributes = (array) ($spec['csi']['volumeAttributes'] ?? []);
        ksort($attributes);

        return [
            'nfs' => isset($spec['nfs']) ? [(string) ($spec['nfs']['server'] ?? ''), (string) ($spec['nfs']['path'] ?? '')] : null,
            'csi' => isset($spec['csi']) ? [(string) ($spec['csi']['driver'] ?? ''), (string) ($spec['csi']['volumeHandle'] ?? ''), $attributes] : null,
            'mountOptions' => array_values((array) ($spec['mountOptions'] ?? [])),
        ];
    }

    /**
     * @return K8sPersistentVolume[]
     * @throws \Exception
     */
    protected function getResources(Deployment $deployment, bool $auth = false): array {
        /** @var DeploymentVolume $deploymentVolumes */
        $deploymentVolumes = (new DeploymentVolumeModel())
            ->where('deployment_id', $deployment->id)
            ->find();
        /** @var DeploymentSpecificationVolume $deploymentSpecificationVolumes */
        $deploymentSpecificationVolumes = (new DeploymentSpecificationVolumeModel())
            ->where('deployment_specification_id', $deployment->findDeploymentSpecification()->id)
            ->find();

        $resources = [];
        foreach ([...$deploymentVolumes, ...$deploymentSpecificationVolumes] as $volume) {
            $resources[] = $this->build($volume, $deployment);
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

    private function build(DeploymentVolume|DeploymentSpecificationVolume $volume, Deployment $deployment): K8sPersistentVolume {
        $resource = new K8sPersistentVolume();
        $resource
            ->setName(self::VolumeName($deployment))
            ->setCapacity($volume->capacity)
            ->setSpec('volumeMode', $volume->volume_mode)
            ->setAccessModes(['ReadWriteMany'])
            ->setSpec('persistentVolumeReclaimPolicy', $volume->reclaim_policy)
            // The claim asks for this class, and only binds to a volume of the same one.
            ->setSpec('storageClassName', (string) $volume->storage_class);

        switch ($volume->type) {
            case 'nfs':
                $resource->setSpec('nfs', [
                    'server' => $volume->nfs_server,
                    'path' => $volume->nfs_path,
                ]);
                break;
            case 'csi':
                if (strlen($volume->csi_driver)) {
                    $resource->setSpec('csi.driver', $volume->csi_driver);
                }
                if (strlen($volume->csi_volume_handle)) {
                    $resource->setSpec('csi.volumeHandle', $volume->getCompiledCsiVolumeHandle($deployment));
                }
                // What the driver needs to mount it - csi-driver-nfs its server and share. A
                // volume with only a handle was taken by its claim and never mounted.
                $attributes = $volume->getCompiledCsiVolumeAttributes($deployment);
                if ($attributes) {
                    $resource->setSpec('csi.volumeAttributes', $attributes);
                }
                break;
        }

        // A storage class's mount options are only for the disks it makes, so a disk of kso's
        // own carries them itself.
        $mountOptions = $volume->getCompiledMountOptions($deployment);
        if ($mountOptions) {
            $resource->setSpec('mountOptions', $mountOptions);
        }

        return $resource;
    }
}
