<?php namespace App\Libraries\Kubernetes;

use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sCertificate;
use DebugTool\Data;
use Exception;
use RenokiCo\PhpK8s\KubernetesCluster;
use RenokiCo\PhpK8s\ResourcesList;

class KubeCertificate {

    private string $domain;
    private string $certificateNamespace;
    private string $certificateName;

    public function __construct(string $domain, string $certificateNamespace, string $certificateName) {
        $this->domain = $domain;
        $this->certificateNamespace = $certificateNamespace;
        $this->certificateName = $certificateName;
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
                'name' => $this->certificateName,
                'namespace' => $this->certificateNamespace,
            ],
            'spec' => [
                'secretName' => $this->certificateName,
                'issuerRef' => [
                    'name' => 'letsencrypt-prod',
                ],
                'dnsNames' => [
                    "*.{$this->domain}",
                    "{$this->domain}",
                ],
                'secretTemplate' => [
                    'annotations' => [
                        'kubed.appscode.com/sync' => '',
                    ],
                ]
            ],
        ]);
    }

}
