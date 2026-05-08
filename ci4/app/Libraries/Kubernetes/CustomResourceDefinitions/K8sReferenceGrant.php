<?php namespace App\Libraries\Kubernetes\CustomResourceDefinitions;

use RenokiCo\PhpK8s\Contracts\InteractsWithK8sCluster;
use RenokiCo\PhpK8s\Kinds\K8sResource;

class K8sReferenceGrant extends K8sResource implements InteractsWithK8sCluster {

    /**
     * The resource Kind parameter.
     *
     * @var null|string
     */
    protected static $kind = 'ReferenceGrant';

    /**
     * The default version for the resource.
     *
     * @var string
     */
    protected static $defaultVersion = 'gateway.networking.k8s.io/v1beta1';

    /**
     * Wether the resource has a namespace.
     *
     * @var bool
     */
    protected static $namespaceable = true;

    /**
     * @param string $fromNamespace
     * @return $this
     */
    public function buildSpec(string $fromNamespace): self {
        return $this->setAttribute('spec', [
            'from' => [[
                'group' => 'gateway.networking.k8s.io',
                'kind' => 'Gateway',
                'namespace' => $fromNamespace,
            ]],
            'to' => [[
                'group' => '', // Core group for Secrets
                'kind' => 'Secret',
            ]],
        ]);
    }

}
