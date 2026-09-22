<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\EnvironmentVariable;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use App\Libraries\Kubernetes\ContainerEnvironment;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sCustomResource;
use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\SecretPreview;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sEvent;
use RenokiCo\PhpK8s\Kinds\K8sResource;

class CustomResourceStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::CustomResource;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Deployment;
    }

    public function getName(): string {
        return 'Custom Resource';
    }

    public function getTriggers(): array {
        return [
            DeploymentStepTriggers::Deployment_CustomResource_Updated,
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
        return true;
    }

    public function hasKubernetesEvents(): bool {
        return true;
    }

    public function hasKubernetesStatus(): bool {
        return true;
    }

    public function getSuccessStatus(Deployment $deployment): string {
        return DeploymentStepHelper::CustomResource_Found;
    }

    /**
     * @throws \Exception
     */
    public function getPreview(Deployment $deployment): string {
        $resource = $this->getResource($deployment, true);
        // Without the passwords: the preview is shown in the UI. The remote one leaves out
        // `spec` below, so it has none to show.
        $local = $this->build($this->parseManifest($deployment, hidePasswords: true), $deployment)->toJson();

        if ($resource->exists()) {
            $exiting = $resource->get();
            $remote = json_decode($exiting->toJson(), true);
            unset($remote['metadata']['uid']);
            unset($remote['metadata']['resourceVersion']);
            unset($remote['metadata']['creationTimestamp']);
            unset($remote['metadata']['labels']);
            unset($remote['metadata']['annotations']);
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
        $resource = $this->getResource($deployment, true);
        try {
            if ($resource->exists()) {
                return DeploymentStepHelper::CustomResource_Found;
            } else {
                return DeploymentStepHelper::CustomResource_NotFound;
            }
        } catch (\Exception $e) {
            Data::debug($e->getMessage());
            return DeploymentStepHelper::CustomResource_NotFound;
        }
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (strlen($deployment->namespace) == 0) {
            return 'Missing namespace';
        }

        try {
            $this->parseManifest($deployment);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return null;
    }

    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        $resource = $this->getResource($deployment, true);
        $this->apply($resource);
    }

    public function startTerminateCommand(Deployment $deployment): void {
        $resource = $this->getResource($deployment, true);
        $resource->synced();
        $resource->delete();
    }

    public function getKubernetesEvents(Deployment $deployment): array {
        $resource = $this->getResource($deployment, true);
        $events = [];
        /** @var K8sEvent $event */
        foreach ($resource->getEvents() as $event) {
            $events[] = [
                'count' => $event->getAttribute('count'),
                'type' => $event->getAttribute('type'),
                'reason' => $event->getAttribute('reason'),
                'date' => date('Y-m-d H:i:s', strtotime_($event->getAttribute('lastTimestamp'))),
                'from' => $event->getAttribute('source')['component'] ?? '',
                'message' => $event->getAttribute('message'),
            ];
        }
        return $events;
    }

    public function getKubernetesStatus(Deployment $deployment): array {
        $resource = $this->getResource($deployment, true);
        if (!$resource->exists()) {
            // Every other step's status method asks first; this one used to throw from
            // inside php-k8s on a resource that is not deployed.
            return [];
        }

        /** @var K8sResource $resource */
        $resource = $resource->get();
        // An empty list, not null: a resource whose controller has not written a status yet
        // - or a kind that has none at all, like a ConfigMap - would otherwise fail the
        // `array` this returns, and the whole status panel died on it.
        return $resource->getAttribute('status') ?? [];
    }

    /**
     * The specification's manifest, as the user wrote it, with the deployment's variables
     * applied.
     *
     * Everything that can be wrong with it says so here. An empty field used to parse to
     * null and die in the constructor on a `TypeError`, and a malformed one came out as the
     * parser's own warning - neither pointed at the field the user has to fix.
     *
     * @param bool $hidePasswords the passwords kso fills in shown as hidden, for the preview
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function parseManifest(Deployment $deployment, bool $hidePasswords = false): array {
        $template = (string) $deployment->findDeploymentSpecification()->custom_resource;
        if ($hidePasswords) {
            $template = str_replace(ContainerEnvironment::SecretPlaceholders, SecretPreview::Hidden, $template);
        }
        $text = EnvironmentVariable::ApplyVariablesToString($template, $deployment);
        if (trim($text) === '') {
            throw new \Exception('Missing custom resource');
        }

        try {
            // A parse error arrives as a warning, which CodeIgniter's handler turns into an
            // ErrorException - an Error, not an Exception, in some versions.
            $yaml = yaml_parse($text);
        } catch (\Throwable $e) {
            throw new \Exception('The custom resource is not valid YAML: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($yaml)) {
            throw new \Exception('The custom resource is not valid YAML');
        }

        return $yaml;
    }

    /**
     * @throws \Exception
     */
    /**
     * The custom resource as the cluster has it, or null when it is not there.
     *
     * The health check reads the conditions the operator writes on it - kso did not write the
     * manifest and cannot know what it means, so what the operator says about it is all there is.
     */
    public function findInTheCluster(Deployment $deployment): ?array {
        $resource = $this->getResource($deployment, true);
        return $resource->exists() ? $resource->get()->toArray() : null;
    }

    protected function getResource(Deployment $deployment, bool $auth = false): K8sResource {
        $resource = $this->build($this->parseManifest($deployment), $deployment);

        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }

    /**
     * @param array<string, mixed> $yaml
     */
    private function build(array $yaml, Deployment $deployment): K8sCustomResource {
        $resource = new K8sCustomResource(null, $yaml);

        // A manifest that does not say where it goes goes to the workspace it belongs to.
        // php-k8s would otherwise fall back to its own default, which is the literal
        // namespace `default` - so every workspace's resource landed in one namespace
        // shared by the cluster, under the same name, and the last one deployed won.
        //
        // A manifest that *does* name a namespace is still honoured, including one outside
        // the workspace: the manifest comes from an operator, not a customer.
        if (!isset($yaml['metadata']['namespace'])) {
            $resource->setNamespace($deployment->namespace);
        }

        return $resource;
    }

}
