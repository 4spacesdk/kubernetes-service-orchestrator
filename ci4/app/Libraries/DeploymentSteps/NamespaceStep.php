<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\KubeHelper;
use DebugTool\Data;
use Exception;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sNamespace;
use RenokiCo\PhpK8s\Kinds\K8sServiceAccount;

class NamespaceStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::Namespace;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Workspace;
    }

    public function getName(): string {
        return 'Namespace';
    }

    public function getTriggers(): array {
        return [

        ];
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
        return true;
    }

    public function getSuccessStatus(Deployment $deployment): string {
        return DeploymentStepHelper::Namespace_Found;
    }

    /**
     * @throws \Exception
     */
    public function getPreview(Deployment $deployment): string {
        $resource = $this->getResource($deployment, true);
        $local = $resource->toJson();

        if ($resource->exists()) {
            $exiting = $resource->get();
            $remote = json_decode($exiting->toJson(), true);
            unset($remote['metadata']['uid']);
            unset($remote['metadata']['resourceVersion']);
            unset($remote['metadata']['creationTimestamp']);
            unset($remote['metadata']['labels']);
            // Only the annotation kso sets itself; anything else was put there by somebody else.
            $mark = array_intersect_key($remote['metadata']['annotations'] ?? [], [KubeHelper::InstallationAnnotation => true]);
            unset($remote['metadata']['annotations']);
            if ($mark) {
                $remote['metadata']['annotations'] = $mark;
            }
            unset($remote['metadata']['managedFields']);
            unset($remote['spec']);
            unset($remote['status']);
            $remote = json_encode($remote);
        }

        return json_encode([
            'local' => $local,
            'remote' => $remote ?? null,
        ]);
    }

    /**
     * @throws KubernetesAPIException
     * @throws \Exception
     */
    public function getStatus(Deployment $deployment): string {
        try {
            // `exists()` is inside the try, and that is the whole of it: building the
            // resource touches nothing, and the call to the cluster is this line. With the
            // try around the line above only, `Namespace_Error` was returned for a
            // misconfigured client and never for the case it reads as - a cluster that
            // answers 401, which went straight out and failed the status panel instead.
            $resource = $this->getResource($deployment, true);

            return $resource->exists()
                ? DeploymentStepHelper::Namespace_Found
                : DeploymentStepHelper::Namespace_NotFound;
        } catch (\Throwable $e) {
            return DeploymentStepHelper::Namespace_Error;
        }
    }

    /**
     * Why this deployment's namespace cannot be built on, or null when it can.
     *
     * Every step that needs a namespace used to ask `getStatus() != Namespace_Found` and
     * report **"Missing Namespace"** for anything that was not a plain yes - thirteen
     * copies of the same two lines. `Namespace_Error` means the question could not be put
     * to the cluster at all, so an operator whose credentials had expired was sent looking
     * for a namespace that was never the problem.
     *
     * The deployment's own configuration is asked about first, because `getResource()`
     * throws for a deployment with no workspace exactly as it does for a cluster that will
     * not answer, and `getStatus()` cannot tell those two apart afterwards.
     */
    public function reasonItCannotBeUsed(Deployment $deployment): ?string {
        $invalid = $this->validateDeployCommand($deployment);
        if ($invalid !== null) {
            return $invalid;
        }

        // Asked here rather than read off `getStatus()`, which answers in constants for a
        // panel that draws marks. This one is read by a person deciding what to do about it,
        // and "401 Unauthorized" is the whole of the answer - `Namespace_Error` would say
        // only that something went wrong.
        try {
            $exists = $this->getResource($deployment, true)->exists();
        } catch (\Throwable $e) {
            return KubeHelper::PrintException($e);
        }

        return $exists ? null : 'Missing Namespace';
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if ($deployment->workspace_id && !$deployment->workspace->exists()) {
            $deployment->workspace->find();
        }
        if (!$deployment->workspace->exists()) {
            return 'Missing workspace';
        }

        $workspace = $deployment->workspace;

        if (strlen($workspace->namespace) == 0) {
            return 'Missing workspace namespace';
        }

        return null;
    }

    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        $resource = $this->getResource($deployment, true);
        $this->apply($resource);
    }

    public function startTerminateCommand(Deployment $deployment): void {
        throw new \Exception('Namespaces must be deleted manually');
    }

    public function getKubernetesEvents(Deployment $deployment): array {
        return [];
    }

    public function getKubernetesStatus(Deployment $deployment): array {
        /** @var K8sNamespace $resource */
        $resource = $this->getResource($deployment, true)->get();
        // An empty list, not null: a resource whose controller has not written a status yet
        // - or a kind that has none at all, like a ConfigMap - would otherwise fail the
        // `array` this returns, and the whole status panel died on it.
        return $resource->getAttribute('status') ?? [];
    }

    /**
     * @throws \Exception
     */
    protected function getResource(Deployment $deployment, bool $auth = false): K8sNamespace {
        if ($deployment->workspace_id && !$deployment->workspace->exists()) {
            $deployment->workspace->find();
        }
        if (!$deployment->workspace->exists()) {
            throw new \Exception('This step require workspace');
        }
        $workspace = $deployment->workspace;

        $resource = new K8sNamespace();
        $resource
            ->setName($workspace->namespace)
            // Which kso's it is. Everything a workspace has lives in its namespace, so the mark
            // here speaks for all of it.
            ->setAnnotations(KubeHelper::Marked());

        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }

}
