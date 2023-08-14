<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class DeploymentSpecificationServicePort
 * @package App\Entities
 * @property int $deploymennt_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property string $protocol
 * @property string $name
 * @property int $port
 * @property int $target_port
 */
class DeploymentSpecificationServicePort extends Entity {

    public static function Create(string $protocol, string $name, int $port, int $targetPort): DeploymentSpecificationServicePort {
        $item = new DeploymentSpecificationServicePort();
        $item->protocol = $protocol;
        $item->name = $name;
        $item->port = $port;
        $item->target_port = $targetPort;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationServicePort[]
     */
    public function getIterator() {
        return parent::getIterator();
    }

}
