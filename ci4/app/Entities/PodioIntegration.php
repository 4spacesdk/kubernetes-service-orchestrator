<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class PodioIntegration
 * @package App\Entities
 * @property string $name
 * @property string $client_id
 * @property string $client_secret
 * @property string $app_id
 * @property string $app_token
 *
 * Many
 * @property PodioFieldReference $podio_field_references
 */
class PodioIntegration extends Entity {

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|PodioIntegration[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
