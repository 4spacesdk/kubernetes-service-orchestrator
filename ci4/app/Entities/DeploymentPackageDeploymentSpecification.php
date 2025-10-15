<?php namespace App\Entities;

use App\Core\Entity;
use App\Models\KNativeMinScaleScheduleModel;

/**
 * Class DeploymentPackageDeploymentSpecification
 * @package App\Entities
 * @property int $deployment_package_id
 * @property DeploymentPackage $deployment_package
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 *
 * # Settings
 * @property string $default_version
 * @property string $default_image_pull_policy
 * @property string $default_environment
 * @property int $default_cpu_request
 * @property int $default_cpu_limit
 * @property int $default_memory_request
 * @property int $default_memory_limit
 * @property int $default_replicas
 * @property int $default_knative_concurrency_limit_soft
 * @property int $default_knative_concurrency_limit_hard
 * @property bool $default_knative_scheduled_minscale_is_enabled
 *
 *  # Update management
 * @property bool $default_auto_update_enabled
 * @property string $default_auto_update_tag_regex
 * @property bool $default_auto_update_require_approval
 *
 * Many
 * @property KNativeMinScaleSchedule $k_native_min_scale_schedules
 */
class DeploymentPackageDeploymentSpecification extends Entity {

    /**
     * @param \App\Interfaces\DeploymentPackageDeploymentSpecification $data
     * @return DeploymentPackageDeploymentSpecification
     */
    public static function Create($data): DeploymentPackageDeploymentSpecification {
        $item = new DeploymentPackageDeploymentSpecification();
        $item->deployment_specification_id = $data->deploymentSpecification->id;
        $item->default_version = $data->defaultVersion ?? null;
        $item->default_image_pull_policy = $data->defaultImagePullPolicy ?? null;
        $item->default_environment = $data->defaultEnvironment ?? null;
        $item->default_cpu_request = $data->defaultCpuRequest ?? null;
        $item->default_cpu_limit = $data->defaultCpuLimit ?? null;
        $item->default_memory_request = $data->defaultMemoryRequest ?? null;
        $item->default_memory_limit = $data->defaultMemoryLimit ?? null;
        $item->default_replicas = $data->defaultReplicas ?? null;
        $item->default_knative_concurrency_limit_soft = $data->defaultKnativeConcurrencyLimitSoft ?? null;
        $item->default_knative_concurrency_limit_hard = $data->defaultKnativeConcurrencyLimitHard ?? null;
        $item->default_auto_update_enabled = $data->defaultAutoUpdateEnabled ?? null;
        $item->default_auto_update_tag_regex = $data->defaultAutoUpdateTagRegex ?? null;
        $item->default_auto_update_require_approval = $data->defaultAutoUpdateRequireApproval ?? null;
        $item->default_knative_scheduled_minscale_is_enabled = isset($data->defaultKnativeScheduledMinScaleIds)
            && count($data->defaultKnativeScheduledMinScaleIds);
        $item->save();
        if ($item->default_knative_scheduled_minscale_is_enabled) {
            /** @var KNativeMinScaleSchedule $knativeMinScaleSchedules */
            $knativeMinScaleSchedules = (new KNativeMinScaleScheduleModel())
                ->whereIn('id', $data->defaultKnativeScheduledMinScaleIds)
                ->find();
            $item->save($knativeMinScaleSchedules);
        }
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentPackageDeploymentSpecification[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
