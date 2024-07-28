<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class DeploymentPackageDeploymentSpecification
 * @package App\Entities
 * @property int $deployment_package_id
 * @property DeploymentPackage $deployment_package
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 *
 * # Settings
 * @property bool $default_enable_podio_notification
 * @property string $default_version
 * @property string $default_keel_policy
 * @property bool $default_keel_auto_update
 * @property string $default_environment
 * @property int $default_cpu_request
 * @property int $default_cpu_limit
 * @property int $default_memory_request
 * @property int $default_memory_limit
 * @property int $default_replicas
 */
class DeploymentPackageDeploymentSpecification extends Entity {

    /**
     * @param \App\Interfaces\DeploymentPackageDeploymentSpecification $data
     * @return DeploymentPackageDeploymentSpecification
     */
    public static function Create($data): DeploymentPackageDeploymentSpecification {
        $item = new DeploymentPackageDeploymentSpecification();
        $item->deployment_specification_id = $data->deploymentSpecification->id;
        $item->default_enable_podio_notification = $data->defaultEnablePodioNotification ?? null;
        $item->default_version = $data->defaultVersion ?? null;
        $item->default_keel_policy = $data->defaultKeelPolicy ?? null;
        $item->default_keel_auto_update = $data->defaultKeelAutoUpdate ?? null;
        $item->default_environment = $data->defaultEnvironment ?? null;
        $item->default_cpu_request = $data->defaultCpuRequest ?? null;
        $item->default_cpu_limit = $data->defaultCpuLimit ?? null;
        $item->default_memory_request = $data->defaultMemoryRequest ?? null;
        $item->default_memory_limit = $data->defaultMemoryLimit ?? null;
        $item->default_replicas = $data->defaultReplicas ?? null;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentPackageDeploymentSpecification[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
