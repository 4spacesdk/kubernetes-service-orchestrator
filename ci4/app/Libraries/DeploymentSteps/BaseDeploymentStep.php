<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Libraries\Kubernetes\KubeHelper;
use DebugTool\Data;
use DeploymentStatusTypes;

abstract class BaseDeploymentStep {

    abstract public function getIdentifier(): string;

    abstract public function getLevel(): string;

    abstract public function getName(): string;

    abstract public function getTriggers(): array;

    abstract public function hasPreviewCommand(): bool;

    abstract public function hasStatusCommand(): bool;

    abstract public function hasDeployCommand(): bool;

    abstract public function hasTerminateCommand(): bool;

    abstract public function hasKubernetesEvents(): bool;

    abstract public function hasKubernetesStatus(): bool;

    abstract public function getSuccessStatus(Deployment $deployment): string;

    abstract public function getPreview(Deployment $deployment): string;

    abstract public function getStatus(Deployment $deployment): string | array;

    abstract public function validateDeployCommand(Deployment $deployment): ?string;

    /**
     * @throws \Exception
     */
    abstract public function startDeployCommand(Deployment $deployment, ?string $reason = null): void;

    /**
     * @throws \Exception
     */
    abstract public function startTerminateCommand(Deployment $deployment): void;

    /**
     * Send a resource to the cluster. Every step applies through here.
     *
     * Retries a 409 - see `KubeHelper::Apply()` for why one arrives at all.
     *
     * @throws \RenokiCo\PhpK8s\Exceptions\KubernetesAPIException
     */
    /**
     * A comma separated field as the user typed it, trimmed. `get, list` is how anyone
     * writes it, and the space used to travel into the manifest, where Kubernetes refused
     * the whole rule.
     *
     * @return string[]
     */
    protected static function commaSeparated(string $value): array {
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($part) => $part !== ''));
    }

    protected function apply(\RenokiCo\PhpK8s\Kinds\K8sResource $resource): void {
        KubeHelper::Apply($resource);
    }

    abstract public function getKubernetesEvents(Deployment $deployment): array;

    abstract public function getKubernetesStatus(Deployment $deployment): array;

    public function tryExecuteDeployCommand(Deployment $deployment, ?string $reason = null): ?string {
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
            $deployment->updateStatus(DeploymentStatusTypes::OutOfSync, true);
            $this->startDeployCommand($deployment, $reason);
        } catch (\Throwable $e) {
            $error = KubeHelper::PrintException($e);
            $this->rememberDeployError($deployment, $error);
            return $error;
        }
        $this->rememberDeployError($deployment, null);
        return null;
    }

    /**
     * The cluster's answer to the last deploy that failed, kept until this step deploys again -
     * what the diagnosis reads when a deployment is Degraded after a refused manifest. It was
     * only ever in the response of the request that did it.
     */
    private function rememberDeployError(Deployment $deployment, ?string $error): void {
        if ($error === null && $deployment->last_deploy_error_step !== $this->getName()) {
            return;
        }
        $deployment->last_deploy_error = $error === null ? null : mb_strimwidth($error, 0, 4000, '…');
        $deployment->last_deploy_error_step = $error === null ? null : $this->getName();
        $deployment->last_deploy_error_at = $error === null ? null : date('Y-m-d H:i:s');
        $deployment->save();
    }

    public function tryExecuteTerminateCommand(Deployment $deployment): ?string {
        Data::debug($deployment->namespace, $deployment->name, 'tryExecuteTerminateCommand', get_class($this));
        try {
            $this->startTerminateCommand($deployment);
        } catch (\Throwable $e) {
            return KubeHelper::PrintException($e);
        }
        return null;
    }

    public function toArray(): array {
        return [
            'identifier' => $this->getIdentifier(),
            'level' => $this->getLevel(),
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
