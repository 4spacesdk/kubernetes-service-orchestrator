<?php namespace App\Entities;

use App\Models\RbacPermissionModel;
use App\Models\RbacRoleModel;
use DebugTool\Data;
use RestExtension\Core\Entity;

/**
 * Class RbacRole
 * @package App\Entities
 * @property string $name
 * @property string $identifier
 * @property string $description
 *
 *  Many
 * @property User $users
 * @property RbacPermission $rbac_permissions
 */
class RbacRole extends Entity {

    public static function Create(string $identifier, string $name, string $description): RbacRole {
        /** @var RbacRole $item */
        $item = (new RbacRoleModel())
            ->where('identifier', $identifier)
            ->find();
        $item->identifier = $identifier;
        $item->name = $name;
        $item->description = $description;
        $item->save();
        return $item;
    }

    /**
     * @param RbacPermission[] $permissions
     * @return void
     */
    public function updatePermissions(array $permissions): void {
        /** @var RbacPermission $existingPermissions */
        $existingPermissions = (new RbacPermissionModel())
            ->whereRelated(RbacRoleModel::class, 'id', $this->id)
            ->find();
        if ($existingPermissions->exists()) {
            $this->delete($existingPermissions);
        }
        foreach ($permissions as $permission) {
            $this->save($permission);
        }
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|RbacRole[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
