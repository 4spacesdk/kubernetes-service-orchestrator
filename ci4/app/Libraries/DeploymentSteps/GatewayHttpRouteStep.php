<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\Domain;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sHttpRoute;
use App\Libraries\Kubernetes\KubeAuth;
use App\Models\DeploymentModel;
use DebugTool\Data;
use Exception;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sEvent;

class GatewayHttpRouteStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::GatewayHttpRoute;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Workspace;
    }

    public function getName(): string {
        return 'Gateway HTTP Route';
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
        return true;
    }

    public function hasKubernetesStatus(): bool {
        return true;
    }

    public function getSuccessStatus(Deployment $deployment): string {
        return DeploymentStepHelper::GatewayHttpRoute_Found;
    }

    /**
     * @throws \Exception
     */
    public function getPreview(Deployment $deployment): string {
        $resources = $this->getResources($deployment, true);

        $locals = [];
        $remotes = [];

        foreach ($resources as $resource) {
            $locals[] = $resource->toJson();

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
                $remotes[] = json_encode($remote);
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
    public function getStatus(Deployment $deployment): string|array {
        try {
            $resources = $this->getResources($deployment, true);
        } catch (Exception $e) {
            return DeploymentStepHelper::GatewayHttpRoute_Error;
        }

        $result = [];
        foreach ($resources as $resource) {
            $result[] = $resource->exists() ? DeploymentStepHelper::GatewayHttpRoute_Found : DeploymentStepHelper::GatewayHttpRoute_NotFound;
        }

        return $result;
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

        if (!$workspace->domain_id) {
            return 'Missing workspace domain';
        }
        $domain = new Domain();
        $domain->find($workspace->domain_id);
        if (!$domain->exists()) {
            return 'domain no longer exists';
        }
        
        if (!$domain->gateway_id) {
            return 'domain has no gateway assigned';
        }
        $domain->gateway->find();
        if (!$domain->gateway->exists()) {
            return 'assigned gateway no longer exists';
        }

        $namespaceStep = new NamespaceStep();
        if ($namespaceStep->getStatus($deployment) != DeploymentStepHelper::Namespace_Found) {
            return 'Missing Namespace';
        }

        return null;
    }

    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        $resources = $this->getResources($deployment, true);
        foreach ($resources as $resource) {
            $resource->createOrUpdate();
        }
    }

    public function startTerminateCommand(Deployment $deployment): void {
        $resources = $this->getResources($deployment, true);
        foreach ($resources as $resource) {
            $resource->synced();
            $resource->delete();
        }
    }

    /**
     * @throws \Exception
     */
    public function getKubernetesEvents(Deployment $deployment): array {
        $resources = $this->getResources($deployment, true);
        $events = [];

        foreach ($resources as $resource) {
            /** @var K8sEvent $event */
            foreach ($resource->getEvents() as $event) {
                $events[] = [
                    'count' => $event->getAttribute('count'),
                    'type' => $event->getAttribute('type'),
                    'reason' => $event->getAttribute('reason'),
                    'date' => date('Y-m-d H:i:s', strtotime_($event->getAttribute('lastTimestamp'))),
                    'from' => $event->getAttribute('source')['component'],
                    'message' => $event->getAttribute('message'),
                ];
            }
        }

        return $events;
    }

    /**
     * @throws KubernetesAPIException
     * @throws \Exception
     */
    public function getKubernetesStatus(Deployment $deployment): array {
        $resources = $this->getResources($deployment, true);
        $status = [];
        foreach ($resources as $resource) {
            $status[] = $resource->get()->getAttribute('status');
        }
        return $status;
    }

    /**
     * @return K8sHttpRoute[]
     * @throws \Exception
     */
    private function getResources(Deployment $deployment, bool $auth = false): array {
        if ($deployment->workspace_id && !$deployment->workspace->exists()) {
            $deployment->workspace->find();
        }
        if (!$deployment->workspace->exists()) {
            throw new \Exception('This step require workspace');
        }
        $workspace = $deployment->workspace;
        if (!$workspace->domain->exists()) {
            $workspace->domain->find();
        }
        $domain = $deployment->workspace->domain;
        if (!$domain->gateway->exists()) {
            $domain->gateway->find();
        }
        $gateway = $domain->gateway;

        if (!$gateway->exists()) {
            return [];
        }

        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('workspace_id', $workspace->id)
            ->find();
        $fqdns = [];
        foreach ($deployments as $deployment) {
            $url = $deployment->findDeploymentSpecification()->getUrl($workspace->subdomain, $domain);
            if (!isset($fqdns[$url])) {
                $fqdns[$url] = new Deployment();
            }
            $fqdns[$url]->add($deployment);
        }

        /** @var K8sHttpRoute[] $resources */
        $resources = [];

        foreach ($fqdns as $fqdn => $deployments) {
            $resource = new K8sHttpRoute();
            $resource
                ->setName($fqdn)
                ->setNamespace($workspace->namespace)
                ->setAnnotations([
                    'app.kubernetes.io/managed-by' => '4spaces.kso',
                ]);

            $parentRef = [
                'name' => $gateway->name,
                'namespace' => $gateway->namespace,
            ];

            if ($domain->https_redirect) {
                $parentRef['sectionName'] = 'https-wildcard-' . str_replace('.', '-', $domain->name);
            }

            $resource->setAttribute('spec', [
                'parentRefs' => [$parentRef],
                'hostnames' => [$fqdn],
                'rules' => $this->getHttpRouteRules($deployments),
            ]);

            $resources[] = $resource;
        }

        if ($auth) {
            $auth = new KubeAuth();
            $cluster = $auth->authenticate();
            foreach ($resources as $resource) {
                $resource->onCluster($cluster);
            }
        }

        return $resources;
    }

    private function getHttpRouteRules(Deployment $deployments): array {
        $rules = [];

        foreach ($deployments as $deployment) {
            $spec = $deployment->findDeploymentSpecification();
            if ($spec->network_type == \NetworkTypes::GatewayApi) {
                foreach ($spec->getHttpProxyRoutes() as $route) {
                    $rule = [
                        'matches' => [
                            [
                                'path' => [
                                    'type' => 'PathPrefix',
                                    'value' => $route->path,
                                ]
                            ]
                        ],
                        'backendRefs' => [
                            [
                                'name' => $deployment->name,
                                'port' => $spec->workload_type == \WorkloadTypes::KNativeService
                                            ? 80 // KNative only listen for ports 80
                                            : (int)$route->port,
                            ]
                        ],
                    ];
                    
                    // TODO: Add support for filters, timeouts etc if needed by Gateway API
                    
                    $rules[] = $rule;
                }
            }
        }

        return $rules;
    }

}
