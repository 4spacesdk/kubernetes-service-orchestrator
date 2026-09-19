<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\ContainerRegistry;
use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\KubeHelper;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sSecret;

/**
 * The pull secrets kso makes from a registry connection's pull login, one per
 * registry the deployment's images come from, named after the connection.
 *
 * Several deployments in a namespace share one, so terminating a deployment leaves it.
 */
class RegistryPullSecretStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::RegistryPullSecret;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Deployment;
    }

    public function getName(): string {
        return 'Registry Pull Secrets';
    }

    /**
     * Whatever makes a pod roll out makes the secret first, so a pod never names one that
     * is not there yet - after a pull login is added to a connection, say. It also puts a
     * rotated login in place on the next rollout.
     */
    public function getTriggers(): array {
        return array_values(array_unique([
            ...(new DeploymentStep())->getTriggers(),
            ...(new KServiceStep())->getTriggers(),
            ...(new MigrationJobStep())->getTriggers(),
        ]));
    }

    public function hasPreviewCommand(): bool {
        return true;
    }

    public function hasStatusCommand(): bool {
        return true;
    }

    public function hasDeployCommand(): bool {
        return true;
    }

    public function hasTerminateCommand(): bool {
        return false;
    }

    public function hasKubernetesEvents(): bool {
        return false;
    }

    public function hasKubernetesStatus(): bool {
        return false;
    }

    public function getSuccessStatus(Deployment $deployment): string {
        return DeploymentStepHelper::RegistryPullSecret_Found;
    }

    /**
     * Without the logins: the preview is shown in the UI, which never shows a secret.
     *
     * @throws \Exception
     */
    public function getPreview(Deployment $deployment): string {
        $locals = [];
        $remotes = [];
        foreach ($this->getResources($deployment, true) as $resource) {
            $local = json_decode($resource->toJson(), true);
            $local['data'] = self::Redacted($local['data'] ?? []);
            $locals[] = json_encode($local);

            if ($resource->exists()) {
                $remote = json_decode($resource->get()->toJson(), true);
                $remotes[] = json_encode([
                    'kind' => $remote['kind'] ?? null,
                    'apiVersion' => $remote['apiVersion'] ?? null,
                    'metadata' => [
                        'name' => $remote['metadata']['name'] ?? null,
                        'namespace' => $remote['metadata']['namespace'] ?? null,
                    ],
                    'type' => $remote['type'] ?? null,
                    'data' => self::Redacted($remote['data'] ?? []),
                ]);
            }
        }

        return json_encode([
            'local' => $locals,
            'remote' => $remotes,
        ]);
    }

    /**
     * @throws KubernetesAPIException
     * @throws \Exception
     */
    public function getStatus(Deployment $deployment): string | array {
        return array_map(
            fn (K8sSecret $resource) => $resource->exists()
                ? DeploymentStepHelper::RegistryPullSecret_Found
                : DeploymentStepHelper::RegistryPullSecret_NotFound,
            $this->getResources($deployment, true)
        );
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (strlen($deployment->namespace) == 0) {
            return 'Missing namespace';
        }

        $namespaceStep = new NamespaceStep();
        try {
            if ($namespaceStep->getStatus($deployment) != DeploymentStepHelper::Namespace_Found) {
                return 'Missing Namespace';
            }
        } catch (KubernetesAPIException $e) {
            return KubeHelper::PrintException($e);
        }

        return null;
    }

    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        foreach ($this->getResources($deployment, true) as $resource) {
            $this->apply($resource);
        }
    }

    /**
     * @codeCoverageIgnore Never called: hasTerminateCommand() is false.
     */
    public function startTerminateCommand(Deployment $deployment): void {
    }

    public function getKubernetesEvents(Deployment $deployment): array {
        return [];
    }

    public function getKubernetesStatus(Deployment $deployment): array {
        return [];
    }

    /**
     * @return K8sSecret[]
     * @throws \Exception
     */
    protected function getResources(Deployment $deployment, bool $auth = false): array {
        $registries = $deployment->findDeploymentSpecification()->getPullSecretRegistries($deployment);

        $cluster = $auth && count($registries) > 0 ? (new KubeAuth())->authenticate() : null;

        return array_map(function (ContainerRegistry $registry) use ($deployment, $cluster) {
            $resource = new K8sSecret();
            $resource
                ->setName($registry->getPullSecretName())
                ->setNamespace($deployment->namespace)
                ->setLabels(['app.kubernetes.io/managed-by' => 'kso'])
                ->setAttribute('type', 'kubernetes.io/dockerconfigjson')
                ->setData(['.dockerconfigjson' => $registry->getDockerConfigJson()]);
            if ($cluster) {
                $resource->onCluster($cluster);
            }
            return $resource;
        }, $registries);
    }

    /**
     * @param array<string, string> $data
     * @return array<string, string>
     */
    private static function Redacted(array $data): array {
        return array_map(fn () => '(hidden)', $data);
    }

}
