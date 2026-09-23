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
        $gatewayEntity->domains->find();
        $domains = [];
        foreach ($gatewayEntity->domains as $domain) {
            $domains[] = [
                'name' => (string) $domain->name,
                'certificate_name' => (string) $domain->certificate_name,
                'certificate_namespace' => (string) $domain->certificate_namespace,
            ];
        }
        $listeners = self::Listeners($domains, (string) $gatewayEntity->namespace);

        $addresses = [];
        $gatewayEntity->gateway_addresses->find();
        foreach ($gatewayEntity->gateway_addresses as $address) {
            $addresses[] = [
                'type' => $address->type,
                'value' => $address->value,
            ];
        }

        $spec = [
            'gatewayClassName' => $gatewayEntity->gateway_class_name,
            'listeners' => $listeners,
        ];

        if (!empty($addresses)) {
            $spec['addresses'] = $addresses;
        }

        return $this->setAttribute('spec', $spec);
    }

    /**
     * The listeners kso gives a Gateway: plain HTTP on 80, and for each of its domains with a
     * certificate, HTTPS on 443 for the domain and for its subdomains. Nothing else - which is what
     * an import has to warn about, since a Gateway made outside kso usually has more.
     *
     * @param list<array{name: string, certificate_name: string, certificate_namespace: string}> $domains
     * @return list<array>
     */
    public static function Listeners(array $domains, string $gatewayNamespace): array {
        $listeners = [[
            'name' => 'http',
            'port' => 80,
            'protocol' => 'HTTP',
            'allowedRoutes' => ['namespaces' => ['from' => 'All']],
        ]];

        foreach ($domains as $domain) {
            if (!$domain['certificate_name']) {
                continue;
            }
            foreach (['https-' => $domain['name'], 'https-wildcard-' => "*.{$domain['name']}"] as $prefix => $hostname) {
                $listeners[] = [
                    'name' => $prefix . str_replace('.', '-', $domain['name']),
                    'port' => 443,
                    'protocol' => 'HTTPS',
                    'tls' => [
                        'mode' => 'Terminate',
                        'certificateRefs' => [[
                            'name' => $domain['certificate_name'],
                            'namespace' => $domain['certificate_namespace'] ?: $gatewayNamespace,
                        ]],
                    ],
                    'allowedRoutes' => ['namespaces' => ['from' => 'All']],
                    'hostname' => $hostname,
                ];
            }
        }

        return $listeners;
    }

}
