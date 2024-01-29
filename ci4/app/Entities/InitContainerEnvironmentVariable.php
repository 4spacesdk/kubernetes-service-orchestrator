<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class InitContainerEnvironmentVariable
 * @package App\Entities
 * @property int $init_container_id
 * @property InitContainer $init_container
 * @property string $name
 * @property string $value
 */
class InitContainerEnvironmentVariable extends Entity {

    public static function Create(string $name, string $value): InitContainerEnvironmentVariable {
        $item = new InitContainerEnvironmentVariable();
        $item->name = $name;
        $item->value = $value;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|InitContainerEnvironmentVariable[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
