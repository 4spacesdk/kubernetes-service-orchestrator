<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class DeploymentSpecificationPostCommand
 * @package App\Entities
 * @property int $deploymennt_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property string $name
 * @property string $command
 */
class DeploymentSpecificationPostCommand extends Entity {

    public static function Create(string $name, string $command): DeploymentSpecificationPostCommand {
        $item = new DeploymentSpecificationPostCommand();
        $item->name = $name;
        $item->command = $command;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationPostCommand[]
     */
    public function getIterator() {
        return parent::getIterator();
    }

}
