<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class K8sCronJob
 * @package App\Entities
 *
 * # Settings
 * @property string $name
 * @property string $schedule
 * @property string $concurrency_policy
 * @property string $restart_policy
 * @property int $successful_jobs_history_limit
 * @property int $failed_jobs_history_limit
 *
 *  # Resource management
 * @property int $cpu_limit
 * @property int $cpu_request
 * @property int $memory_limit
 * @property int $memory_request
 *
 * # Container
 * @property int $container_image_id
 * @property ContainerImage $container_image
 * @property string $command
 * @property string $args
 * @property string $container_image_tag_policy
 * @property string $container_image_tag_value
 * @property string $container_image_pull_policy
 *
 *  # Environment variables
 * @property bool $include_deployment_environment_variables
 *
 * Many
 * @property DeploymentSpecification $deployment_specifications
 */
class K8sCronJob extends Entity {

    public function generateName(Deployment $deployment): string {
        if (strlen($this->name) > 0) {
            return "{$deployment->name}-{$this->name}";
        } else {
            return $deployment->name;
        }
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|K8sCronJob[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
