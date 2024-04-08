<?php namespace App\Interfaces;
use App\Entities\DeploymentSpecification;

/**
 * @property DeploymentSpecification $deploymentSpecification
 * @property bool $defaultEnablePodioNotification
 * @property string $defaultVersion
 * @property bool $defaultAutoUpdateEnabled
 * @property string $defaultAutoUpdateTagRegex
 * @property bool $defaultAutoUpdateRequireApproval
 * @property string $defaultKeelPolicy
 * @property bool $defaultKeelAutoUpdate
 * @property string $defaultEnvironment
 * @property int $defaultCpuRequest
 * @property int $defaultCpuLimit
 * @property int $defaultMemoryRequest
 * @property int $defaultMemoryLimit
 * @property int $defaultReplicas
 */
interface DeploymentPackageDeploymentSpecification {

}
