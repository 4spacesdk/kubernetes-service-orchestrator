<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\Domain;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\KubeAuth;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sEvent;
use RenokiCo\PhpK8s\Kinds\K8sIngress;

class IngressStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::Ingress;
    }

    public function getName(): string {
        return 'Ingress';
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

    public function getSuccesStatus(): string {
        return DeploymentStepHelper::Ingress_Found;
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
        $resource = $this->getResource($deployment, true);
        if ($resource->exists()) {
            return DeploymentStepHelper::Ingress_Found;
        } else {
            return DeploymentStepHelper::Ingress_NotFound;
        }
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (strlen($deployment->name) == 0) {
            return 'Missing name';
        }
        if (strlen($deployment->namespace) == 0) {
            return 'Missing namespace';
        }

        if (!$deployment->domain_id) {
            return 'Missing domain';
        }
        $domain = new Domain();
        $domain->find($deployment->domain_id);
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

    public function startDeployCommand(Deployment $deployment): void {
        $resource = $this->getResource($deployment, true);
        $resource->createOrUpdate();
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
                'from' => $event->getAttribute('source')['component'],
                'message' => $event->getAttribute('message'),
            ];
        }
        return $events;
    }

    public function getKubernetesStatus(Deployment $deployment): array {
        /** @var K8sIngress $resource */
        $resource = $this->getResource($deployment, true)->get();
        $status = $resource->getAttribute('status');
        return $status;
    }

    /**
     * @throws \Exception
     */
    private function getResource(Deployment $deployment, bool $auth = false): K8sIngress {
        $spec = $deployment->findDeploymentSpecification();

        if (!$deployment->domain->exists()) {
            $deployment->domain->find();
        }

        $resource = new K8sIngress();
        $resource
            ->setName($deployment->name)
            ->setNamespace($deployment->namespace)
            ->setAnnotations([
                'kubernetes.io/ingress.class' => 'nginx',
                'nginx.ingress.kubernetes.io/proxy-body-size' => '50m',
                'nginx.ingress.kubernetes.io/proxy-connect-timeout' => '600',
                'nginx.ingress.kubernetes.io/proxy-read-timeout' => '600',
                'nginx.ingress.kubernetes.io/proxy-send-timeout' => '600',
            ])
            ->setTls([
                [
                    'hosts' => [
                        $deployment->getUrl()
                    ],
                    'secretName' => $deployment->domain->certificate_name,
                ]
            ])
            ->setRules($spec->getIngressRules($deployment));


        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }

}
