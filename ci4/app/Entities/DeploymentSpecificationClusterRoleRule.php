<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class DeploymentSpecificationClusterRoleRule
 * @package App\Entities
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property string $api_group
 * @property string $resource
 * @property string $verbs # comma-separated
 */
class DeploymentSpecificationClusterRoleRule extends Entity {

    public static function Create(string $apiGroup, string $resource, string $verbs): DeploymentSpecificationClusterRoleRule {
        $item = new DeploymentSpecificationClusterRoleRule();
        $item->api_group = $apiGroup;
        $item->resource = $resource;
        $item->verbs = $verbs;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationClusterRoleRule[]
     */
    public function getIterator() {
        return parent::getIterator();
    }

}
