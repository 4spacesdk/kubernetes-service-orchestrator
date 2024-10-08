<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentSpecificationServiceAnnotation
 * @package App\Entities
 * @property string $name
 * @property string $value
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 */
class DeploymentSpecificationServiceAnnotation extends Entity {

    public static function Create(string $name, string $value): DeploymentSpecificationServiceAnnotation {
        $item = new DeploymentSpecificationServiceAnnotation();
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
