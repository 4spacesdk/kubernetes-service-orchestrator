<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentsLabel
 * @package App\Entities
 * @property int $deployment_id
 * @property Deployment $deployment
 * @property int $label_id
 * @property Label $label
 */
class DeploymentsLabel extends Entity {

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentsLabel[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
