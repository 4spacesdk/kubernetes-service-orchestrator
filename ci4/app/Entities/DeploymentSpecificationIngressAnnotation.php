<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentSpecificationIngressAnnotation
 * @package App\Entities
 * @property string $name
 * @property string $value
 * @property int $deployment_specification_ingress_id
 * @property DeploymentSpecificationIngress $deployment_specification_ingress
 */
class DeploymentSpecificationIngressAnnotation extends Entity {

    public static function Create(string $name, string $value): DeploymentSpecificationIngressAnnotation {
        $item = new DeploymentSpecificationIngressAnnotation();
        $item->name = $name;
        $item->value = $value;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationServiceAnnotation[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
