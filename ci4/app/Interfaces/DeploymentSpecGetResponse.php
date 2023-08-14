<?php namespace App\Interfaces;

/**
 * Interface DeploymentSpecGetResponse
 * @package App\Interfaces
 * @property string $identifier
 * @property string $name
 * @property string $image
 * @property string $tenantType
 * @property bool $requireDatabase
 * @property bool $requireCronJob
 * @property bool $requireIngress
 * @property DeploymentStep[] $deploymentSteps
 */
interface DeploymentSpecGetResponse {

}
