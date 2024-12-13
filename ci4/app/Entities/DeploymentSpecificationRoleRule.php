<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentSpecificationRoleRule
 * @package App\Entities
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property string $api_group
 * @property string $resource
 * @property string $verbs # comma-separated
 */
class DeploymentSpecificationRoleRule extends Entity {

    public static function Create(string $apiGroup, string $resource, string $verbs): DeploymentSpecificationRoleRule {
        $item = new DeploymentSpecificationRoleRule();
        $item->api_group = $apiGroup;
        $item->resource = $resource;
        $item->verbs = $verbs;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationClusterRoleRule[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
