<?php namespace App\Entities;

use App\Interfaces\IngressRulePath;
use App\Models\DeploymentSpecificationIngressRulePathModel;
use App\Core\Entity;

/**
 * Class DeploymentSpecificationIngress
 * @package App\Entities
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property string $ingress_class
 * @property int $proxy_body_size
 * @property int $proxy_connect_timeout
 * @property int $proxy_read_timeout
 * @property int $proxy_send_timeout
 * @property bool $ssl_redirect
 * @property bool $enable_tls
 *
 * Many
 * @property DeploymentSpecificationIngressRulePath $deployment_specification_ingress_rule_paths
 */
class DeploymentSpecificationIngress extends Entity {

    public static function Create(string $ingressClass, int $proxyBodySize,
                                  int $proxyConnectTimeout, int $proxyReadTimeout,
                                  int $proxySendTimeout, bool $sslRedirect, bool $enableTls,
                                  array $paths): DeploymentSpecificationIngress {
        $item = new DeploymentSpecificationIngress();
        $item->ingress_class = $ingressClass;
        $item->proxy_body_size = $proxyBodySize;
        $item->proxy_connect_timeout = $proxyConnectTimeout;
        $item->proxy_read_timeout = $proxyReadTimeout;
        $item->proxy_send_timeout = $proxySendTimeout;
        $item->ssl_redirect = $sslRedirect;
        $item->enable_tls = $enableTls;
        $item->save();

        /** @var IngressRulePath[] $paths */

        $rulePaths = new DeploymentSpecificationIngressRulePath();
        $rulePaths->all = array_map(
            fn($data) => DeploymentSpecificationIngressRulePath::Create(
                $data->path,
                $data->pathType,
                $data->backendServicePortName,
            ),
            $paths
        );

        $item->save($rulePaths);

        return $item;
    }

    public function getIngressRules(Deployment $deployment): array {
        $paths = [];
        /** @var DeploymentSpecificationIngressRulePath $ingressRulePaths */
        $ingressRulePaths = (new DeploymentSpecificationIngressRulePathModel())
            ->where('deployment_specification_ingress_id', $this->id)
            ->find();
        foreach ($ingressRulePaths as $ingressRulePath) {
            $paths[] = [
                'path' => $ingressRulePath->path,
                'pathType' => $ingressRulePath->path_type,
                'backend' => [
                    'service' => [
                        'name' => $deployment->name,
                        'port' => [
                            'name' => $ingressRulePath->backend_service_port_name,
                        ],
                    ],
                ],
            ];
        }

        return [
            [
                'host' => $deployment->getUrl(),
                'http' => [
                    'paths' => $paths,
                ],
            ]
        ];
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationIngress[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
