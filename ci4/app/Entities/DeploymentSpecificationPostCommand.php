<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentSpecificationPostCommand
 * @package App\Entities
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property string $name
 * @property string $command
 * @property bool $all_pods
 */
class DeploymentSpecificationPostCommand extends Entity {

    public static function Create(string $name, string $command, bool $allPods): DeploymentSpecificationPostCommand {
        $item = new DeploymentSpecificationPostCommand();
        $item->name = $name;
        $item->command = $command;
        $item->all_pods = $allPods;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationPostCommand[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
