<?php namespace App\Entities;

use App\Core\Entity;

/**
 * @property string $identifier
 * @property string $name
 * @property bool $hasPreviewCommand
 * @property bool $hasStatusCommand
 * @property bool $hasDeployCommand
 * @property bool $hasTerminateCommand
 * @property bool $hasKubernetesEvents
 * @property bool $hasKubernetesStatus
 */
class DeploymentStep extends Entity {

}
