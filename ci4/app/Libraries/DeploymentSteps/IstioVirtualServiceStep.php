<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\Domain;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sIstioVirtualService;
use App\Libraries\Kubernetes\KubeAuth;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sEvent;

class IstioVirtualServiceStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::IstioVirtualService;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Deployment;
    }

    public function getName(): string {
        return 'Istio Virtual Service';
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
        return true;
    }

    public function hasKubernetesEvents(): bool {
        return true;
    }

    public function hasKubernetesStatus(): bool {
        return true;
    }

    public function getSuccessStatus(Deployment $deployment): string {
        return DeploymentStepHelper::IstioVirtualService_Found;
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
            unset($remote['metadata']['annotations']['kubectl.kubernetes.io/last-applied-configuration']);
            unset($remote['metadata']['generation']);
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
        return $resource->exists() ? DeploymentStepHelper::IstioVirtualService_Found : DeploymentStepHelper::IstioVirtualService_NotFound;
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (strlen($deployment->name) == 0) {
            return 'Missing name';
        }
        if (strlen($deployment->namespace) == 0) {
            return 'Missing namespace';
        }

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
        if (!$domain->enable_istio_gateway) {
            return 'domain istio gateway not enabled';
        }

        $namespaceStep = new NamespaceStep();
        if ($namespaceStep->getStatus($deployment) != DeploymentStepHelper::Namespace_Found) {
            return 'Missing Namespace';
        }

        $serviceStep = new ServiceStep();
        if ($serviceStep->getStatus($deployment) != DeploymentStepHelper::Service_Found) {
            return 'Missing Service';
        }
        return null;
    }

    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        $resource = $this->getResource($deployment, true);
        $resource->createOrUpdate();
    }

    public function startTerminateCommand(Deployment $deployment): void {
        $resource = $this->getResource($deployment, true);
        $resource->synced();
        $resource->delete();
    }

    /**
     * @throws \Exception
     */
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
                'from' => $event->getAttribute('source')['component'],
                'message' => $event->getAttribute('message'),
            ];
        }
        return $events;
    }

    /**
     * @throws KubernetesAPIException
     * @throws \Exception
     */
    public function getKubernetesStatus(Deployment $deployment): array {
        /** @var K8sIstioVirtualService $resource */
        $resource = $this->getResource($deployment, true)->get();
        $status = $resource->getAttribute('status');
        return $status;
    }

    /**
     * @throws \Exception
     */
    private function getResource(Deployment $deployment, bool $auth = false): K8sIstioVirtualService {
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

        $resource = new K8sIstioVirtualService();
        $resource
            ->setName($deployment->name)
            ->setNamespace($deployment->namespace)
            ->setAnnotations([
                'app.kubernetes.io/managed-by' => '4spaces.kso',
            ])
            ->setAttribute('spec', [
                'gateways' => [
                    "{$domain->certificate_namespace}/{$domain->getIstioGatewayName()}",
                ],
                'hosts' => [
                    $deployment->getUrl(),
                ],
                'http' => [
                    [
                        'match' => [
                            [
                                'uri' => [
                                    'prefix' => '/api'
                                ],
                            ],
                        ],
                        'rewrite' => [
                            'authority' => "{$deployment->name}.{$deployment->namespace}.svc.cluster.local",
                        ],
                        'route' => [
                            [
                                'destination' => [
                                    'host' => "{$deployment->name}.{$deployment->namespace}.svc.cluster.local",
                                    'port' => [
                                        'number' => 80,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        if ($auth) {
            $auth = new KubeAuth();
            $cluster = $auth->authenticate();
            $resource->onCluster($cluster);
        }

        return $resource;
    }

}
