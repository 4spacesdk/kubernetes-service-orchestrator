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

class RedirectsStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::Redirects;
    }

    public function getName(): string {
        return 'Redirects';
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
        $expectAlias = strlen($deployment->aliases) > 0;
        return $expectAlias
            ? DeploymentStepHelper::Redirects_Found
            : DeploymentStepHelper::Redirects_NotFoundNotExpected;
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
        $expectAlias = strlen($deployment->aliases) > 0;

        $resource = $this->getResource($deployment, true);
        $hasAlias = $resource->exists();
        if ($hasAlias) {
            return $expectAlias
                ? DeploymentStepHelper::Redirects_Found
                : DeploymentStepHelper::Redirects_FoundNotExpected;
        } else {
            return $expectAlias
                ? DeploymentStepHelper::Redirects_NotFound
                : DeploymentStepHelper::Redirects_NotFoundNotExpected;
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

        $ingressStep = new IngressStep();
        if ($ingressStep->getStatus($deployment) != DeploymentStepHelper::Ingress_Found) {
            return 'Missing ingress';
        }
        return null;
    }

    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        if (strlen($deployment->aliases) == 0) {
            $this->startTerminateCommand($deployment);
            return;
        }
        $resource = $this->getResource($deployment, true);
        $resource->createOrUpdate();
    }

    public function startTerminateCommand(Deployment $deployment): void {
        $resource = $this->getResource($deployment, true);
        if ($resource->exists()) {
            $resource->synced();
            $resource->delete();
        }
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
        $resource = new K8sIngress();
        $resource
            ->setName($deployment->name . '-redirects')
            ->setNamespace($deployment->namespace)
            ->setAnnotations([
                'kubernetes.io/ingress.class' => 'nginx',
                'nginx.ingress.kubernetes.io/rewrite-target' => $deployment->getUrl(true, true),
            ]);

        $tlsHosts = [];
        $rules = [];

        if (strlen($deployment->aliases)) {
            $aliases = explode(',', $deployment->aliases);
            $spec = $deployment->findDeploymentSpecification();
            foreach ($aliases as $alias) {
                $url = $spec->getUrl($alias, $deployment->domain);
                $tlsHosts[] = $url;
                $rules[] = [
                    'host' => $url,
                    'http' => [
                        'paths' => [
                            [
                                'path' => strlen($spec->domain_suffix) ? $spec->domain_suffix : '/',
                                'pathType' => 'Prefix',
                                'backend' => [
                                    'service' => [
                                        'name' => 'http-svc',
                                        'port' => [
                                            'name' => 'http',
                                        ],
                                    ],
                                ],
                            ]
                        ]
                    ],
                ];
            }
        }

        $resource
            ->setTls([
                [
                    'hosts' => $tlsHosts,
                    'secretName' => $deployment->domain->certificate_name,
                ]
            ])
            ->setRules($rules);


        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }

}
