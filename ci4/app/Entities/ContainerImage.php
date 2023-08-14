<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class ContainerImage
 * @package App\Entities
 * @property string $name
 * @property string $url
 */
class ContainerImage extends Entity {

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|ContainerImage[]
     */
    public function getIterator() {
        return parent::getIterator();
    }

}
