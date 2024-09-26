<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class PostUpdateActionCondition
 * @package App\Entities
 * @property int $post_update_action_id
 * @property PostUpdateAction $post_update_action
 * @property string $type
 * @property int $podio_field_reference_id
 * @property PodioFieldReference $podio_field_reference
 * @property string $value
 */
class PostUpdateActionCondition extends Entity {

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|PostUpdateActionCondition[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
