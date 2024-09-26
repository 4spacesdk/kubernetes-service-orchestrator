<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class DeploymentSpecificationPostUpdateAction
 * @package App\Entities
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property int $post_update_action_id
 * @property PostUpdateAction $post_update_action
 * @property int $position
 */
class DeploymentSpecificationPostUpdateAction extends Entity {

    public static function Create(int $postUpdateActionId, int $pos): DeploymentSpecificationPostUpdateAction {
        $item = new DeploymentSpecificationPostUpdateAction();
        $item->post_update_action_id = $postUpdateActionId;
        $item->position = $pos;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationPostUpdateAction[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
