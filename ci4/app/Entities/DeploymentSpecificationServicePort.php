<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentSpecificationServicePort
 * @package App\Entities
 * @property int $deploymennt_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property string $protocol
 * @property string $name
 * @property int $port
 * @property int $target_port
 * @property string $health_check_type
 * @property string $health_check_path
 */
class DeploymentSpecificationServicePort extends Entity {

    public static function Create(string $protocol, string $name, int $port, int $targetPort,
                                  ?string $healthCheckType = null, ?string $healthCheckPath = null): DeploymentSpecificationServicePort {
        $item = new DeploymentSpecificationServicePort();
        $item->protocol = $protocol;
        $item->name = $name;
        $item->port = $port;
        $item->target_port = $targetPort;
        $item->health_check_type = $healthCheckType;
        $item->health_check_path = $healthCheckPath;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationServicePort[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
