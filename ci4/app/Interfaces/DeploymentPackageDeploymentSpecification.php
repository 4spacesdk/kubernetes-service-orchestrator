<?php namespace App\Interfaces;
use App\Entities\DeploymentSpecification;

/**
 * @property DeploymentSpecification $deploymentSpecification
 * @property ?bool $defaultEnablePodioNotification
 * @property ?string $defaultVersion
 * @property ?string $defaultEnvironment
 * @property ?int $defaultCpuRequest
 * @property ?int $defaultCpuLimit
 * @property ?int $defaultMemoryRequest
 * @property ?int $defaultMemoryLimit
 * @property ?int $defaultReplicas
 * @property ?int $defaultKnativeConcurrencyLimitSoft
 * @property ?int $defaultKnativeConcurrencyLimitHard
 * @property ?bool $defaultAutoUpdateEnabled
 * @property ?string $defaultAutoUpdateTagRegex
 * @property ?bool $defaultAutoUpdateRequireApproval
 */
interface DeploymentPackageDeploymentSpecification {

}
