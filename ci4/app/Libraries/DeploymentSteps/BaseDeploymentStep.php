<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Libraries\Kubernetes\KubeHelper;
use DebugTool\Data;

abstract class BaseDeploymentStep {

    abstract public function getIdentifier(): string;

    abstract public function getName(): string;

    abstract public function hasPreviewCommand(): bool;

    abstract public function hasStatusCommand(): bool;

    abstract public function hasDeployCommand(): bool;

    abstract public function hasTerminateCommand(): bool;

    abstract public function hasKubernetesEvents(): bool;

    abstract public function hasKubernetesStatus(): bool;

    abstract public function getSuccessStatus(): string;

    abstract public function getPreview(Deployment $deployment): string;

    abstract public function getStatus(Deployment $deployment): string;

    abstract public function validateDeployCommand(Deployment $deployment): ?string;

    /**
     * @throws \Exception
     */
    abstract public function startDeployCommand(Deployment $deployment): void;

    /**
     * @throws \Exception
     */
    abstract public function startTerminateCommand(Deployment $deployment): void;

    abstract public function getKubernetesEvents(Deployment $deployment): array;

    abstract public function getKubernetesStatus(Deployment $deployment): array;

    public function tryExecuteDeployCommand(Deployment $deployment): ?string {
        Data::debug($deployment->namespace, $deployment->name, 'tryExecuteDeployCommand', get_class($this));
        if (!$deployment->findDeploymentSpecification()->hasDeploymentStep($deployment, get_class($this))) {
            Data::debug('ignored cause of invalid deployment step for this spec');
            return null;
        }

        $error = $this->validateDeployCommand($deployment);
        if ($error) {
            return $error;
        }
        try {
            $this->startDeployCommand($deployment);
        } catch (\Exception $e) {
            return KubeHelper::PrintException($e);
        }
        return null;
    }

    public function tryExecuteTerminateCommand(Deployment $deployment): ?string {
        Data::debug($deployment->namespace, $deployment->name, 'tryExecuteTerminateCommand', get_class($this));
        try {
            $this->startTerminateCommand($deployment);
        } catch (\Exception $e) {
            return KubeHelper::PrintException($e);
        }
        return null;
    }

    public function toArray(): array {
        return [
            'identifier' => $this->getIdentifier(),
            'name' => $this->getName(),
            'hasPreviewCommand' => $this->hasPreviewCommand(),
            'hasStatusCommand' => $this->hasStatusCommand(),
            'hasDeployCommand' => $this->hasDeployCommand(),
            'hasKubernetesEvents' => $this->hasKubernetesEvents(),
            'hasKubernetesStatus' => $this->hasKubernetesStatus(),
            'hasTerminateCommand' => $this->hasTerminateCommand(),
        ];
    }

}
