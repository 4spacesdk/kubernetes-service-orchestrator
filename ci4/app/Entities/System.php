<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class System
 * @package App\Entities
 * @property int $default_email_service_id
 * @property int $default_database_service_id
 * @property int $default_domain_id
 */
class System extends Entity {

    public static function Get(): System {
        $item = new System();
        $item->find(1);
        if (!$item->exists()) {
            $item->created = date('Y-m-d H:i:s');
            $item->save();
        }
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|System[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
