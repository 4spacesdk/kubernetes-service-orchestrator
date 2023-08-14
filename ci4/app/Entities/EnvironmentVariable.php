<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class EnvironmentVariable
 * @package App\Entities
 * @property int $deployment_id
 * @property Deployment $deployment
 * @property string $name
 * @property string $value
 *
 * Many
 * @property Deployment $deployments
 */
class EnvironmentVariable extends Entity {

    public static function Create(string $name, string $value): EnvironmentVariable {
        $item = new EnvironmentVariable();
        $item->name = $name;
        $item->value = $value;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|EnvironmentVariable[]
     */
    public function getIterator() {
        return parent::getIterator();
    }

}
