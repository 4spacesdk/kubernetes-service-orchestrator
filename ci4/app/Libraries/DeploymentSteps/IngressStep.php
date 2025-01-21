<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\DeploymentSpecificationIngress;
use App\Entities\Domain;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\KubeAuth;
use App\Models\DeploymentSpecificationIngressModel;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sEvent;
use RenokiCo\PhpK8s\Kinds\K8sIngress;

class IngressStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::Ingress;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Deployment;
    }

    public function getName(): string {
        return 'Ingress';
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
        return DeploymentStepHelper::Ingress_Found;
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
    public function getStatus(Deployment $deployment): string {
        $resources = $this->getResources($deployment, true);

        $found = true;
        foreach ($resources as $resource) {
            if (!$resource->exists()) {
                $found = false;
                break;
            }
        }

        return $found ? DeploymentStepHelper::Ingress_Found : DeploymentStepHelper::Ingress_NotFound;
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
     * @return K8sIngress[]
     * @throws \Exception
     */
    private function getResources(Deployment $deployment, bool $auth = false): array {
        $spec = $deployment->findDeploymentSpecification();

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

        /** @var DeploymentSpecificationIngress $ingresses */
        $ingresses = (new DeploymentSpecificationIngressModel())
            ->where('deployment_specification_id', $spec->id)
            ->find();

        $resources = [];

        foreach ($ingresses as $index => $ingress) {

            $resource = new K8sIngress();
            $resource
                ->setName($deployment->name . ($index == 0 ? '' : '-'.$index))
                ->setNamespace($deployment->namespace)
                ->setAnnotations([
                    'nginx.ingress.kubernetes.io/proxy-body-size' => "{$ingress->proxy_body_size}m",
                    'nginx.ingress.kubernetes.io/proxy-connect-timeout' => (string)$ingress->proxy_connect_timeout,
                    'nginx.ingress.kubernetes.io/proxy-read-timeout' => (string)$ingress->proxy_read_timeout,
                    'nginx.ingress.kubernetes.io/proxy-send-timeout' => (string)$ingress->proxy_send_timeout,
                    'nginx.ingress.kubernetes.io/ssl-redirect' => $ingress->ssl_redirect ? 'true' : 'false',
                    ...$ingress->getIngressAnnotations(),
                ])
                ->setSpec('ingressClassName', $ingress->ingress_class)
                ->setRules($ingress->getIngressRules($deployment));

            if ($ingress->enable_tls) {
                $resource->setTls([
                    [
                        'hosts' => [
                            $deployment->getUrl(),
                        ],
                        'secretName' => $domain->certificate_name,
                    ]
                ]);
            }

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

}
