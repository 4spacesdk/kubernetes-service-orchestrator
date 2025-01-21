<?php namespace App\Libraries\Kubernetes;

use App\Entities\Domain;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8SIstioGateway;
use Exception;
use RenokiCo\PhpK8s\KubernetesCluster;
use RenokiCo\PhpK8s\ResourcesList;

class KubeIstioGateway {

    private Domain $domain;

    public function __construct(Domain $domain) {
        $this->domain = $domain;
    }

    public function apply(KubernetesCluster $cluster): bool|string {
        $certificate = $this->getResource($cluster);
        try {
            $certificate->exists() ? $certificate->update() : $certificate->create();
        } catch (Exception $e) {
            return KubeHelper::PrintException($e);
        }
        return true;
    }

    public function delete(KubernetesCluster $cluster): bool|string {
        $certificate = $this->getResource($cluster);
        try {
            $certificate->delete();
        } catch (Exception $e) {
            return KubeHelper::PrintException($e);
        }
        return true;
    }

    public function getEvents(KubernetesCluster $cluster): ResourcesList {
        $certificate = $this->getResource($cluster);
        if ($certificate->exists()) {
            return $certificate->getEvents();
        } else {
            return new ResourcesList();
        }
    }

    public function getStatus(KubernetesCluster $cluster): array {
        $certificate = $this->getResource($cluster);
        if ($certificate->exists()) {
            return $certificate->get()->getAttribute('status');
        } else {
            return [];
        }
    }

    private function getResource(KubernetesCluster $cluster): K8SIstioGateway {
        return new K8SIstioGateway($cluster, [
            'metadata' => [
                'name' => $this->domain->getIstioGatewayName(),
                'namespace' => $this->domain->certificate_namespace,
                'labels' => [
                    'app.kubernetes.io/managed-by' => '4spaces.kso',
                    '4spaces.kso/domain-ref' => "{$this->domain->name}"
                ],
            ],
            'spec' => [
                'selector' => [
                    'app' => 'istio-ingressgateway',
                    'istio' => 'ingressgateway',
                ],
                'servers' => [
                    [
                        'hosts' => [
                            "*.{$this->domain->name}"
                        ],
                        'port' => [
                            'name' => 'http',
                            'number' => 80,
                            'protocol' => 'HTTP',
                        ],
                        'tls' => [
                            'httpsRedirect' => true
                        ],
                    ],
                    [
                        'hosts' => [
                            "*.{$this->domain->name}"
                        ],
                        'port' => [
                            'name' => 'https',
                            'number' => 443,
                            'protocol' => 'HTTPS',
                        ],
                        'tls' => [
                            'credentialName' => "{$this->domain->name}",
                            'mode' => 'SIMPLE',
                        ],
                    ],
                ],
            ],
        ]);
    }

}
