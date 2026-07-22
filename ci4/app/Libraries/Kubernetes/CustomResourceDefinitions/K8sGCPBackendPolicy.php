<?php namespace App\Libraries\Kubernetes\CustomResourceDefinitions;

use RenokiCo\PhpK8s\Contracts\InteractsWithK8sCluster;
use RenokiCo\PhpK8s\Kinds\K8sResource;

class K8sGCPBackendPolicy extends K8sResource implements InteractsWithK8sCluster {

    /**
     * The resource Kind parameter.
     *
     * @var null|string
     */
    protected static $kind = 'GCPBackendPolicy';

    /**
     * The default version for the resource.
     *
     * @var string
     */
    protected static $defaultVersion = 'networking.gke.io/v1';

    /**
     * Whether the resource has a namespace.
     *
     * @var bool
     */
    protected static $namespaceable = true;

    /**
     * A GCPBackendPolicy configures the GCP backend service that sits behind a Service. The one setting
     * we manage here is timeoutSec - the backend service response timeout, which defaults to 30s and
     * otherwise cuts off any request that runs longer than that once it goes through a GKE Gateway.
     *
     * @param string $serviceName Name of the Service to target. Must live in this policy's namespace.
     * @param int $timeoutSec Backend service response timeout in seconds.
     * @return $this
     */
    public function buildSpec(string $serviceName, int $timeoutSec): self {
        return $this->setAttribute('spec', [
            'default' => [
                'timeoutSec' => $timeoutSec,
            ],
            'targetRef' => [
                'group' => '', // Core group for Services
                'kind' => 'Service',
                'name' => $serviceName,
            ],
        ]);
    }

}
