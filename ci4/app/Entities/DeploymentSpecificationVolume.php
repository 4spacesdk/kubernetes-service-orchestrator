<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentSpecificationVolume
 * @package App\Entities
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property string $mount_path
 * @property string $sub_path
 * @property int $capacity
 * @property string $volume_mode
 * @property string $reclaim_policy
 * @property string $nfs_server
 * @property string $nfs_path
 */
class DeploymentSpecificationVolume extends Entity {

    public static function Create(string $mountPath, string $subPath, int $capacity,
                                  string $volumeMode, string $reclaimPolicy,
                                  string $nfsServer, string $nfcPath): DeploymentSpecificationVolume {
        $item = new DeploymentSpecificationVolume();
        $item->mount_path = $mountPath;
        $item->sub_path = $subPath;
        $item->capacity = $capacity;
        $item->volume_mode = $volumeMode;
        $item->reclaim_policy = $reclaimPolicy;
        $item->nfs_server = $nfsServer;
        $item->nfs_path = $nfcPath;
        $item->save();
        return $item;
    }

    public function validate(): ?string {
        if (empty($this->mount_path)) {
            return 'Missing mount path';
        }
        if (empty($this->sub_path)) {
            return 'Missing sub path';
        }
        if (empty($this->capacity) || $this->capacity <= 0) {
            return 'Missing capacity';
        }
        if (empty($this->volume_mode)) {
            return 'Missing volume mode';
        }
        if (empty($this->reclaim_policy)) {
            return 'Missing reclaim policy';
        }
        if (empty($this->nfs_server)) {
            return 'Missing nfs server';
        }
        if (empty($this->nfs_path)) {
            return 'Missing nfs path';
        }

        return null;
    }

    public function getCompiledSubPath(Deployment $deployment): string {
        return EnvironmentVariable::ApplyVariablesToString($this->sub_path, $deployment);
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationVolume[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
