<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class System
 * @package App\Entities
 * @property bool $is_network_nginx_ingress_supported
 * @property bool $is_network_istio_supported
 * @property bool $is_network_contour_supported
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
