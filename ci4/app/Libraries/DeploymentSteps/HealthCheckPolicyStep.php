<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\DeploymentSpecificationServicePort;
use App\Entities\Gateway;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sHealthCheckPolicy;
use App\Libraries\Kubernetes\KubeAuth;
use App\Models\DeploymentSpecificationServicePortModel;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;

class HealthCheckPolicyStep extends BaseDeploymentStep {

    /**
     * GKE's own Gateway classes are the only ones whose controller honours a HealthCheckPolicy.
     */
    const string GkeGatewayClassPrefix = 'gke-l7-';

    public function getIdentifier(): string {
        return DeploymentSteps::HealthCheckPolicy;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Deployment;
    }

    public function getName(): string {
        return 'Health Check Policy';
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
        return $this->getPolicyType($deployment)
            ? DeploymentStepHelper::HealthCheckPolicy_Found
            : DeploymentStepHelper::HealthCheckPolicy_NotFoundNotExpected;
    }

    /**
     * @throws \Exception
     */
    public function getPreview(Deployment $deployment): string {
        $resource = $this->getResource($deployment, true);
        $local = $this->getPolicyType($deployment) ? $resource->toJson() : null;

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
        $expectResource = $this->getPolicyType($deployment) !== null;
        $resource = $this->getResource($deployment, true);

        if ($resource->exists()) {
            return $expectResource
                ? DeploymentStepHelper::HealthCheckPolicy_Found
                : DeploymentStepHelper::HealthCheckPolicy_FoundNotExpected;
        } else {
            return $expectResource
                ? DeploymentStepHelper::HealthCheckPolicy_NotFound
                : DeploymentStepHelper::HealthCheckPolicy_NotFoundNotExpected;
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

        if ($this->getPolicyType($deployment)) {
            $resource->createOrUpdate();
        } else if ($resource->exists()) {
            // The spec no longer asks for a policy, so hand the Service back to GKE's default health check.
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
     * Resolve the single health check config that applies to every port of this deployment's Service.
     *
     * A HealthCheckPolicy has no per-port selector, so one config has to cover all of them. A port that
     * does not speak HTTP can never pass an HTTP check, while an HTTP port passes a TCP check just fine.
     * A single TCP port therefore forces the whole policy to TCP.
     *
     * @return string|null \HealthCheckTypes::Tcp, \HealthCheckTypes::Http, or null when no port asked for one.
     */
    private function getPolicyType(Deployment $deployment): ?string {
        if (!$this->isTargetingGkeGateway($deployment)) {
            return null;
        }

        $types = [];
        foreach ($this->getServicePorts($deployment) as $port) {
            $types[] = $port->health_check_type;
        }

        if (in_array(\HealthCheckTypes::Tcp, $types)) {
            return \HealthCheckTypes::Tcp;
        }
        if (in_array(\HealthCheckTypes::Http, $types)) {
            return \HealthCheckTypes::Http;
        }
        return null;
    }

    /**
     * The request path of the first HTTP port that names one. Only meaningful for an HTTP policy.
     */
    private function getRequestPath(Deployment $deployment): ?string {
        foreach ($this->getServicePorts($deployment) as $port) {
            if ($port->health_check_type == \HealthCheckTypes::Http && strlen($port->health_check_path ?? '')) {
                return $port->health_check_path;
            }
        }
        return null;
    }

    private function getServicePorts(Deployment $deployment): DeploymentSpecificationServicePort {
        $spec = $deployment->findDeploymentSpecification();

        /** @var DeploymentSpecificationServicePort $ports */
        $ports = (new DeploymentSpecificationServicePortModel())
            ->where('deployment_specification_id', $spec->id)
            ->find();
        return $ports;
    }

    /**
     * A HealthCheckPolicy is only honoured when the Service is fronted by a GKE-managed Gateway. The cluster
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
    private function getResource(Deployment $deployment, bool $auth = false): K8sHealthCheckPolicy {
        $resource = new K8sHealthCheckPolicy();
        $resource
            ->setName($deployment->name)
            ->setNamespace($deployment->namespace)
            ->setAnnotations([
                'app.kubernetes.io/managed-by' => '4spaces.kso',
            ]);

        $type = $this->getPolicyType($deployment);
        if ($type) {
            $resource->buildSpec($deployment->name, $type, $this->getRequestPath($deployment));
        }

        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }

}
