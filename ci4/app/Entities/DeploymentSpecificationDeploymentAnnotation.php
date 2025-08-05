<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentSpecificationDeploymentAnnotation
 * @package App\Entities
 * @property string $name
 * @property string $value
 * @property string $level
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 */
class DeploymentSpecificationDeploymentAnnotation extends Entity {

    public static function Create(string $level, string $name, string $value): DeploymentSpecificationDeploymentAnnotation {
        $item = new DeploymentSpecificationDeploymentAnnotation();
        $item->level = $level;
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
