<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentSpecificationHttpProxyRoute
 * @package App\Entities
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property string $path
 * @property int $port
 * @property string $protocol
 */
class DeploymentSpecificationHttpProxyRoute extends Entity {

    public static function Create(string $path, int $port, ?string $protocol): DeploymentSpecificationHttpProxyRoute {
        $item = new DeploymentSpecificationHttpProxyRoute();
        $item->path = $path;
        $item->port = $port;
        $item->protocol = $protocol;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationHttpProxyRoute[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
