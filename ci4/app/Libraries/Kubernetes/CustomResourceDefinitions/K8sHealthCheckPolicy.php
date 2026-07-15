<?php namespace App\Libraries\Kubernetes\CustomResourceDefinitions;

use RenokiCo\PhpK8s\Contracts\InteractsWithK8sCluster;
use RenokiCo\PhpK8s\Kinds\K8sResource;

class K8sHealthCheckPolicy extends K8sResource implements InteractsWithK8sCluster {

    /**
     * The resource Kind parameter.
     *
     * @var null|string
     */
    protected static $kind = 'HealthCheckPolicy';

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
     * A HealthCheckPolicy targets a Service as a whole. GKE has no per-port selector, so the
     * config given here applies to every port of the Service that a Gateway health checks.
     *
     * @param string $serviceName Name of the Service to target. Must live in this policy's namespace.
     * @param string $type \HealthCheckTypes::Tcp or \HealthCheckTypes::Http
     * @param string|null $requestPath Only used for HTTP. Defaults to '/'.
     * @return $this
     */
    public function buildSpec(string $serviceName, string $type, ?string $requestPath = null): self {
        $config = match ($type) {
            \HealthCheckTypes::Http => [
                'type' => 'HTTP',
                'httpHealthCheck' => [
                    'portSpecification' => 'USE_SERVING_PORT',
                    'requestPath' => strlen($requestPath ?? '') ? $requestPath : '/',
                ],
            ],
            default => [
                'type' => 'TCP',
                'tcpHealthCheck' => [
                    'portSpecification' => 'USE_SERVING_PORT',
                ],
            ],
        };

        return $this->setAttribute('spec', [
            'default' => [
                'config' => $config,
            ],
            'targetRef' => [
                'group' => '', // Core group for Services
                'kind' => 'Service',
                'name' => $serviceName,
            ],
        ]);
    }

}
