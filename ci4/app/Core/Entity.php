<?php namespace App\Core;

use RestExtension\ResourceEntityInterface;

class Entity extends \RestExtension\Core\Entity implements ResourceEntityInterface {

    public function getClone(): Entity {
        $className = get_called_class();
        $item = new $className();

        // Skip relations
        $tableFields = [];
        foreach ($this->getTableFields() as $tableField) {
            $tableFields[$tableField] = $tableField;
        }
        $original = array_intersect_key($this->original, $tableFields);
        foreach ($original as $key => $value) {
            $item->{$key} = $value;
        }

        return $item;
    }

}
