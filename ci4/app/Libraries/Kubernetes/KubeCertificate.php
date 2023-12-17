<?php namespace App\Libraries\Kubernetes;

use App\Entities\Domain;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sCertificate;
use DebugTool\Data;
use Exception;
use RenokiCo\PhpK8s\KubernetesCluster;
use RenokiCo\PhpK8s\ResourcesList;

class KubeCertificate {

    private Domain $domain;

    public function __construct(Domain $domain) {
        $this->domain = $domain;
    }

    public function apply(KubernetesCluster $cluster): void {
        $certificate = $this->getResource($cluster);
        try {
            $certificate->exists() ? $certificate->update() : $certificate->create();
        } catch(Exception $e) {
            KubeHelper::PrintException($e);
        }
    }

    public function delete(KubernetesCluster $cluster): void {
        $certificate = $this->getResource($cluster);
        $certificate->delete();
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

    private function getResource(KubernetesCluster $cluster): K8sCertificate {
        return new K8sCertificate($cluster, [
            'metadata' => [
                'name' => $this->domain->certificate_name,
                'namespace' => $this->domain->certificate_namespace,
            ],
            'spec' => [
                'secretName' => $this->domain->certificate_name,
                'issuerRef' => [
                    'name' => $this->domain->issuer_ref_name,
                ],
                'dnsNames' => [
                    "*.{$this->domain->name}",
                    "{$this->domain->name}",
                ],
                'secretTemplate' => [
                    'annotations' => [
                        'kubed.appscode.com/sync' => '',
                        'reflector.v1.k8s.emberstack.com/reflection-allowed' => 'true',
                        'reflector.v1.k8s.emberstack.com/reflection-allowed-namespaces' => '',
                        'reflector.v1.k8s.emberstack.com/reflection-auto-enabled' => 'true',
                    ],
                ]
            ],
        ]);
    }

}
