<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class DeploymentSpecificationQuickCommand
 * @package App\Entities
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property string $name
 * @property string $command
 */
class DeploymentSpecificationQuickCommand extends Entity {

    public static function Create(string $name, string $command): DeploymentSpecificationQuickCommand {
        $item = new DeploymentSpecificationQuickCommand();
        $item->name = $name;
        $item->command = $command;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationQuickCommand[]
     */
    public function getIterator() {
        return parent::getIterator();
    }

}
