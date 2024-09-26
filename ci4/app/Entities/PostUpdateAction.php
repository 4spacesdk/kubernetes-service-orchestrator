<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class PostUpdateAction
 * @package App\Entities
 * @property string $name
 * @property string $type
 *
 * # Type: Podio, Add Comment
 * @property int $podio_add_comment_integration_id
 * @property PodioIntegration $podio_add_comment_integration
 * @property string $podio_add_comment_value
 *
 *  # Type: Podio, Field Update
 * @property int $podio_field_update_field_reference_id
 * @property PodioFieldReference $podio_field_update_field_reference
 * @property string $podio_field_update_value
 *
 * Many
 * @property PostUpdateActionCondition $post_update_action_conditions
 * @property DeploymentSpecification $deployment_specifications
 */
class PostUpdateAction extends Entity {

    public function updateConditions(PostUpdateActionCondition $values): void {
        $this->post_update_action_conditions->find()->deleteAll();
        $this->save($values);
        $this->post_update_action_conditions = $values;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|PostUpdateAction[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
