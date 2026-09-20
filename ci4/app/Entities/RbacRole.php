<?php namespace App\Entities;

use App\Models\RbacPermissionModel;
use App\Models\RbacRoleModel;
use DebugTool\Data;
use App\Core\Entity;

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

        // One at a time. `delete()` takes an entity and reads a single id off it, so handing
        // it the whole collection removed the *first* row's link and left every other one in
        // place - which is not what "replace the set" means, in either direction: calling
        // this twice with the same two permissions left three join rows, and a permission
        // left out of the new set kept its link and was never revoked.
        //
        // The `exists()` guard that used to wrap this is gone with it. It read as the thing
        // stopping an empty collection from deleting everything, and it was not: the delete
        // it guarded would have been `WHERE rbac_role_id = X AND rbac_permission_id IS NULL`,
        // which matches nothing. An empty collection is now simply nothing to loop over.
        foreach ($existingPermissions as $existingPermission) {
            $this->delete($existingPermission);
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
