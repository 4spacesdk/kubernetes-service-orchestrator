<?php namespace App\Entities;

use App\Models\RbacPermissionModel;
use App\Core\Entity;

/**
 * Class RbacPermission
 * @package App\Entities
 * @property string $name
 * @property string $description
 *
 * Many
 * @property RbacRole $rbac_roles
 */
class RbacPermission extends Entity {

    public static function Create(string $name, string $description): RbacPermission {
        /** @var RbacPermission $item */
        $item = (new RbacPermissionModel())
            ->where('name', $name)
            ->find();
        $item->name = $name;
        $item->description = $description;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|RbacPermission[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
