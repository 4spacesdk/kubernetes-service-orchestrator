<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class ZMQEvent
 * @package App\Entities
 * @property string $identifier
 * @property string $event
 * @property string $data
 */
class ZMQEvent extends Entity {

    /**
     * @return \ArrayIterator|Entity[]|\Traversable|ZMQEvent[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
