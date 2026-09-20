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

    /**
     * True when it was applied, otherwise the reason it was not.
     *
     * The reason used to be formatted and thrown away, so a certificate the api server
     * refused looked exactly like one it accepted - a domain pointing at a namespace that
     * has been deleted, say. `KubeIstioGateway` next door always answered this way.
     */
    public function apply(KubernetesCluster $cluster): bool|string {
        $certificate = $this->getResource($cluster);
        try {
            $certificate->exists() ? $certificate->update() : $certificate->create();
        } catch (Exception $e) {
            return KubeHelper::PrintException($e);
        }
        return true;
    }

    /**
     * True when it is gone, otherwise the reason it is not.
     *
     * The resource is built from the domain's fields rather than read from the cluster, so
     * php-k8s does not consider it synced - and its `delete()` starts with
     * `if (! $this->isSynced()) return true;`. This used to answer success without sending
     * a request at all. Marking it synced is what the deployment steps do.
     */
    public function delete(KubernetesCluster $cluster): bool|string {
        $certificate = $this->getResource($cluster);
        try {
            $certificate->synced()->delete();
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
            // cert-manager writes the status, so there is none until its controller has been
            // by - which is every certificate asked for a moment ago, and every certificate
            // in a cluster without cert-manager. Null used to fail the `array` this returns,
            // and it took the nightly expiry job with it.
            return $certificate->get()->getAttribute('status') ?? [];
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
