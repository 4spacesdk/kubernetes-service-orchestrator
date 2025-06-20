<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentSpecificationInitContainer
 * @package App\Entities
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property int $init_container_id
 * @property InitContainer $init_container
 * @property int $position
 * @property bool $include_in_migration_job
 */
class DeploymentSpecificationInitContainer extends Entity {

    public static function Create(int $initContainerId, int $pos, bool $includeInMigrationJob): DeploymentSpecificationInitContainer {
        $item = new DeploymentSpecificationInitContainer();
        $item->init_container_id = $initContainerId;
        $item->position = $pos;
        $item->include_in_migration_job = $includeInMigrationJob;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationInitContainer[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
