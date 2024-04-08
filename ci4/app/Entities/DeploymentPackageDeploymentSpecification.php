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
 * @property string $default_environment
 * @property int $default_cpu_request
 * @property int $default_cpu_limit
 * @property int $default_memory_request
 * @property int $default_memory_limit
 * @property int $default_replicas
 *
 *  # Update management
 * @property bool $default_auto_update_enabled
 * @property string $default_auto_update_tag_regex
 * @property bool $default_auto_update_require_approval
 */
class DeploymentPackageDeploymentSpecification extends Entity {

    /**
     * @param \App\Interfaces\DeploymentPackageDeploymentSpecification $data
     * @return DeploymentPackageDeploymentSpecification
     */
    public static function Create($data): DeploymentPackageDeploymentSpecification {
        $item = new DeploymentPackageDeploymentSpecification();
        $item->deployment_specification_id = $data->deploymentSpecification->id;
        $item->default_enable_podio_notification = $data->defaultEnablePodioNotification;
        $item->default_version = $data->defaultVersion;
        $item->default_auto_update_enabled = $data->defaultAutoUpdateEnabled;
        $item->default_auto_update_tag_regex = $data->defaultAutoUpdateTagRegex;
        $item->default_auto_update_require_approval = $data->defaultAutoUpdateRequireApproval;
        $item->default_environment = $data->defaultEnvironment;
        $item->default_cpu_request = $data->defaultCpuRequest;
        $item->default_cpu_limit = $data->defaultCpuLimit;
        $item->default_memory_request = $data->defaultMemoryRequest;
        $item->default_memory_limit = $data->defaultMemoryLimit;
        $item->default_replicas = $data->defaultReplicas;
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
