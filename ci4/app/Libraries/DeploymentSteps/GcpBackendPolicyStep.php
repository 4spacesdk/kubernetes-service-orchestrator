<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\Gateway;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sGCPBackendPolicy;
use App\Libraries\Kubernetes\KubeAuth;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;

class GcpBackendPolicyStep extends BaseDeploymentStep {

    /**
     * GKE's own Gateway classes are the only ones whose controller honours a GCPBackendPolicy.
     */
    const string GkeGatewayClassPrefix = 'gke-l7-';

    public function getIdentifier(): string {
        return DeploymentSteps::GcpBackendPolicy;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Deployment;
    }

    public function getName(): string {
        return 'GCP Backend Policy';
    }

    public function getTriggers(): array {
        return [];
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
        return false;
    }

    public function hasKubernetesStatus(): bool {
        return false;
    }

    public function getSuccessStatus(Deployment $deployment): string {
        return $this->getTimeout($deployment) !== null
            ? DeploymentStepHelper::GcpBackendPolicy_Found
            : DeploymentStepHelper::GcpBackendPolicy_NotFoundNotExpected;
    }

    /**
     * @throws \Exception
     */
    public function getPreview(Deployment $deployment): string {
        $resource = $this->getResource($deployment, true);
        $local = $this->getTimeout($deployment) !== null ? $resource->toJson() : null;

        if ($resource->exists()) {
            $exiting = $resource->get();
            $remote = json_decode($exiting->toJson(), true);
            unset($remote['metadata']['uid']);
            unset($remote['metadata']['resourceVersion']);
            unset($remote['metadata']['generation']);
            unset($remote['metadata']['creationTimestamp']);
            unset($remote['metadata']['annotations']['kubectl.kubernetes.io/last-applied-configuration']);
            unset($remote['metadata']['managedFields']);
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
        $expectResource = $this->getTimeout($deployment) !== null;
        $resource = $this->getResource($deployment, true);

        if ($resource->exists()) {
            return $expectResource
                ? DeploymentStepHelper::GcpBackendPolicy_Found
                : DeploymentStepHelper::GcpBackendPolicy_FoundNotExpected;
        } else {
            return $expectResource
                ? DeploymentStepHelper::GcpBackendPolicy_NotFound
                : DeploymentStepHelper::GcpBackendPolicy_NotFoundNotExpected;
        }
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (strlen($deployment->name) == 0) {
            return 'Missing name';
        }
        if (strlen($deployment->namespace) == 0) {
            return 'Missing namespace';
        }

        $serviceStep = new ServiceStep();
        if ($serviceStep->getStatus($deployment) != DeploymentStepHelper::Service_Found) {
            return 'Missing Service';
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        $resource = $this->getResource($deployment, true);

        // Decide from the built object, not a fresh getTimeout() call: re-deriving it here could
        // disagree with what getResource() built and apply a spec-less policy, which the API rejects.
        if ($resource->getAttribute('spec')) {
            $resource->createOrUpdate();
        } else if ($resource->exists()) {
            // No timeout is configured any more, so hand the backend service back to GKE's default.
            $resource->synced();
            $resource->delete();
        }
    }

    /**
     * @throws \Exception
     */
    public function startTerminateCommand(Deployment $deployment): void {
        $resource = $this->getResource($deployment, true);
        if ($resource->exists()) {
            $resource->synced();
            $resource->delete();
        }
    }

    public function getKubernetesEvents(Deployment $deployment): array {
        return [];
    }

    public function getKubernetesStatus(Deployment $deployment): array {
        return [];
    }

    /**
     * The backend service timeout in seconds that applies to this deployment's Service, or null when no
     * policy should exist - either because the timeout is unset or the Service is not behind a GKE Gateway.
     */
    private function getTimeout(Deployment $deployment): ?int {
        if (!$this->isTargetingGkeGateway($deployment)) {
            return null;
        }

        $spec = $deployment->findDeploymentSpecification();
        $timeout = $spec->gateway_backend_timeout;
        if ($timeout === null || (int)$timeout <= 0) {
            return null;
        }
        return (int)$timeout;
    }

    /**
     * A GCPBackendPolicy is only honoured when the Service is fronted by a GKE-managed Gateway. The cluster
     * may well be GKE while a given workspace routes through, say, an Envoy gateway - the policy would be
     * dead weight there.
     */
    private function isTargetingGkeGateway(Deployment $deployment): bool {
        $spec = $deployment->findDeploymentSpecification();
        if ($spec->network_type != \NetworkTypes::GatewayApi) {
            return false;
        }

        if ($deployment->workspace_id && !$deployment->workspace->exists()) {
            $deployment->workspace->find();
        }
        if (!$deployment->workspace->exists()) {
            return false;
        }

        $workspace = $deployment->workspace;
        if (!$workspace->domain->exists()) {
            $workspace->domain->find();
        }
        $domain = $workspace->domain;
        if (!$domain->exists()) {
            return false;
        }

        if (!$domain->gateway->exists()) {
            $domain->gateway->find();
        }
        /** @var Gateway $gateway */
        $gateway = $domain->gateway;
        if (!$gateway->exists()) {
            return false;
        }

        return str_starts_with($gateway->gateway_class_name ?? '', self::GkeGatewayClassPrefix);
    }

    /**
     * The policy lives alongside the Service it targets - targetRef carries no namespace.
     *
     * @throws \Exception
     */
    private function getResource(Deployment $deployment, bool $auth = false): K8sGCPBackendPolicy {
        $resource = new K8sGCPBackendPolicy();
        $resource
            ->setName($deployment->name)
            ->setNamespace($deployment->namespace)
            ->setAnnotations([
                'app.kubernetes.io/managed-by' => '4spaces.kso',
            ]);

        $timeout = $this->getTimeout($deployment);
        if ($timeout !== null) {
            $resource->buildSpec($deployment->name, $timeout);
        }

        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }

}
