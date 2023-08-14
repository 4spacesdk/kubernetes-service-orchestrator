<?php namespace App\Interfaces;

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
interface DeploymentStep {

}
