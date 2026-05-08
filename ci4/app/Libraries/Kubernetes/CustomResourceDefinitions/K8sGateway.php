<?php namespace App\Libraries\Kubernetes\CustomResourceDefinitions;

use App\Entities\Gateway;
use RenokiCo\PhpK8s\Contracts\InteractsWithK8sCluster;
use RenokiCo\PhpK8s\Kinds\K8sResource;

class K8sGateway extends K8sResource implements InteractsWithK8sCluster {

    /**
     * The resource Kind parameter.
     *
     * @var null|string
     */
    protected static $kind = 'Gateway';

    /**
     * The default version for the resource.
     *
     * @var string
     */
    protected static $defaultVersion = 'gateway.networking.k8s.io/v1';

    /**
     * Whether the resource has a namespace.
     *
     * @var bool
     */
    protected static $namespaceable = true;

    /**
     * @param Gateway $gatewayEntity
     * @return $this
     */
    public function buildSpec(Gateway $gatewayEntity): self {
        $listeners = [];

        // HTTP listener
        $listeners[] = [
            'name' => 'http',
            'port' => 80,
            'protocol' => 'HTTP',
            'allowedRoutes' => [
                'namespaces' => ['from' => 'All']
            ]
        ];

        $gatewayEntity->domains->find();
        foreach ($gatewayEntity->domains as $domain) {
            if ($domain->certificate_name) {
                $listeners[] = [
                    'name' => 'https-' . str_replace('.', '-', $domain->name),
                    'port' => 443,
                    'protocol' => 'HTTPS',
                    'tls' => [
                        'mode' => 'Terminate',
                        'certificateRefs' => [
                            [
                                'name' => $domain->certificate_name,
                                'namespace' => $domain->certificate_namespace ?: $gatewayEntity->namespace,
                            ]
                        ]
                    ],
                    'allowedRoutes' => [
                        'namespaces' => ['from' => 'All']
                    ],
                    'hostname' => "*.{$domain->name}",
                ];
            }
        }

        return $this->setAttribute('spec', [
            'gatewayClassName' => $gatewayEntity->gateway_class_name,
            'listeners' => $listeners,
        ]);
    }

}
