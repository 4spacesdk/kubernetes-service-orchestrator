<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentSpecificationIngressRulePath
 * @package App\Entities
 * @property int $deployment_specification_ingress_id
 * @property DeploymentSpecificationIngress $deployment_specification_ingress
 * @property string $path
 * @property string $path_type
 * @property string $backend_service_port_name
 */
class DeploymentSpecificationIngressRulePath extends Entity {

    public static function Create(string $path, string $pathType, string $backendServicePortName): DeploymentSpecificationIngressRulePath {
        $item = new DeploymentSpecificationIngressRulePath();
        $item->path = $path;
        $item->path_type = $pathType;
        $item->backend_service_port_name = $backendServicePortName;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationIngressRulePath[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
