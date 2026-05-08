<?php namespace App\Libraries\GatewaySteps;

use App\Entities\Gateway;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sGateway;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sHttpRoute;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sReferenceGrant;
use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\KubeHelper;
use DebugTool\Data;
use Exception;
use RenokiCo\PhpK8s\Kinds\K8sEvent;

class GatewayStep {

    public function getPreview(Gateway $gateway): string {
        $resources = $this->getAllResources($gateway);
        $local = [];
        $remote = [];

        foreach ($resources as $resource) {
            $local[] = $resource->toJson();

            if ($resource->exists()) {
                $exiting = $resource->get();
                $remoteData = json_decode($exiting->toJson(), true);
                unset($remoteData['metadata']['uid']);
                unset($remoteData['metadata']['resourceVersion']);
                unset($remoteData['metadata']['generation']);
                unset($remoteData['metadata']['creationTimestamp']);
                unset($remoteData['metadata']['annotations']['kubectl.kubernetes.io/last-applied-configuration']);
                unset($remoteData['metadata']['managedFields']);
                unset($remoteData['status']);
                $remote[] = json_encode($remoteData);
            }
        }

        return json_encode([
            'local' => $local,
            'remote' => $remote,
        ]);
    }

    public function deploy(Gateway $gateway): void {
        $resources = $this->getAllResources($gateway);
        foreach ($resources as $resource) {
            $resource->createOrUpdate();
        }
    }

    public function terminate(Gateway $gateway): void {
        $resources = $this->getAllResources($gateway);
        $resources = array_reverse($resources);
        foreach ($resources as $resource) {
            if ($resource->exists()) {
                $resource->synced();
                $resource->delete();
            }
        }
    }

    public function getStatus(Gateway $gateway): string {
        try {
            $resource = $this->getResource($gateway);
            return $resource->exists() ? 'found' : 'not-found';
        } catch (Exception $e) {
            return 'error';
        }
    }

    public function getKubernetesEvents(Gateway $gateway): array {
        $resource = $this->getResource($gateway);
        if (!$resource->exists()) {
            return [];
        }

        $auth = new KubeAuth();
        $cluster = $auth->authenticate();

        return $cluster->event()
            ->where('involvedObject.kind', 'Gateway')
            ->where('involvedObject.name', $gateway->name)
            ->where('involvedObject.namespace', $gateway->namespace)
            ->all()
            ->map(fn(K8sEvent $event) => [
                'type' => $event->getAttribute('type'),
                'reason' => $event->getAttribute('reason'),
                'message' => $event->getAttribute('message'),
                'lastTimestamp' => $event->getAttribute('lastTimestamp'),
                'count' => $event->getAttribute('count'),
            ])
            ->toArray();
    }

    public function getKubernetesStatus(Gateway $gateway): array {
        $resource = $this->getResource($gateway);
        if (!$resource->exists()) {
            return [];
        }

        $resource = $resource->get();
        return $resource->getAttribute('status') ?: [];
    }

    private function getAllResources(Gateway $gateway): array {
        $resources = [];
        $resources[] = $this->getResource($gateway);

        $auth = new KubeAuth();
        $cluster = $auth->authenticate();

        $namespacesWithGrants = [];
        $gateway->domains->find();
        foreach ($gateway->domains as $domain) {
            if ($domain->certificate_name && $domain->certificate_namespace) {
                if ($domain->certificate_namespace !== $gateway->namespace) {
                    if (!in_array($domain->certificate_namespace, $namespacesWithGrants)) {
                        $grant = new K8sReferenceGrant();
                        $grant
                            ->onCluster($cluster)
                            ->setName('kso-' . $gateway->name . '-grant')
                            ->setNamespace($domain->certificate_namespace)
                            ->setAnnotations([
                                'app.kubernetes.io/managed-by' => '4spaces.kso',
                            ])
                            ->buildSpec($gateway->namespace);

                        $resources[] = $grant;
                        $namespacesWithGrants[] = $domain->certificate_namespace;
                    }
                }
            }

            if ($domain->https_redirect) {
                $redirectResource = new K8sHttpRoute();
                $redirectResource
                    ->onCluster($cluster)
                    ->setName('kso-redirect-' . str_replace('.', '-', $domain->name))
                    ->setNamespace($gateway->namespace)
                    ->setAnnotations([
                        'app.kubernetes.io/managed-by' => '4spaces.kso',
                    ])
                    ->setAttribute('spec', [
                        'parentRefs' => [
                            [
                                'name' => $gateway->name,
                                'namespace' => $gateway->namespace,
                                'sectionName' => 'http',
                            ]
                        ],
                        'hostnames' => [$domain->name, "*." . $domain->name],
                        'rules' => [
                            [
                                'filters' => [
                                    [
                                        'type' => 'RequestRedirect',
                                        'requestRedirect' => [
                                            'scheme' => 'https',
                                            'statusCode' => 301,
                                        ]
                                    ]
                                ]
                            ]
                        ],
                    ]);
                $resources[] = $redirectResource;
            }
        }

        return $resources;
    }

    private function getResource(Gateway $gateway): K8sGateway {
        $auth = new KubeAuth();
        $cluster = $auth->authenticate();

        $resource = new K8sGateway();
        $resource
            ->onCluster($cluster)
            ->setName($gateway->name)
            ->setNamespace($gateway->namespace)
            ->setAnnotations([
                'app.kubernetes.io/managed-by' => '4spaces.kso',
            ])
            ->buildSpec($gateway);

        return $resource;
    }
}
