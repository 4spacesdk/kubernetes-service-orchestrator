<?php namespace App\Entities;

use App\Core\Entity;

class Deletion extends Entity {

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|Deletion[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
