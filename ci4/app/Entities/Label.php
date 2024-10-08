<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class Label
 * @package App\Entities
 * @property string $name
 * @property string $value
 */
class Label extends Entity {

    public static function Create(string $name, string $value): Label {
        $item = new Label();
        $item->name = $name;
        $item->value = $value;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|Label[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
