<?php namespace App\Database\Migrations;

use App\Controllers\RbacPermissions;
use App\Controllers\RbacRoles;
use App\Entities\RbacPermission;
use App\Entities\RbacRole;
use App\Entities\User;
use App\Models\UserModel;
use CodeIgniter\Database\Migration;
use DebugTool\Data;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddRbac extends Migration {

    public function up() {
        Table::init('rbac_permissions')
            ->create()
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('description', ColumnTypes::VARCHAR_1023);
        Table::init('rbac_roles')
            ->create()
            ->column('identifier', ColumnTypes::VARCHAR_511)
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('description', ColumnTypes::VARCHAR_1023);
        Table::init('rbac_permissions_rbac_roles')
            ->create()
            ->column('rbac_permission_id', ColumnTypes::INT)->addIndex('rbac_permission_id')
            ->column('rbac_role_id', ColumnTypes::INT)->addIndex('rbac_role_id');

        Table::init('rbac_roles_users')
            ->create()
            ->column('user_id', ColumnTypes::INT)->addIndex('user_id')
            ->column('rbac_role_id', ColumnTypes::INT)->addIndex('rbac_role_id');

        ApiRoute::addResourceControllerGet(RbacRoles::class);
        ApiRoute::addResourceControllerGet(RbacPermissions::class);

        $developer = RbacPermission::Create('developer', 'Developer access to everything');
        $workspacesGet = RbacPermission::Create('workspaces.get', 'Get single workspace');
        $workspacesList = RbacPermission::Create('workspaces.list', 'Get workspace list');
        $workspacesCreate = RbacPermission::Create('workspaces.create', 'Create a workspace');
        $workspacesUpdate = RbacPermission::Create('workspaces.update', 'Update a workspace');
        $workspacesDelete = RbacPermission::Create('workspaces.delete', 'Delete a workspace');
        $userGet = RbacPermission::Create('users.get', 'Get single user');
        $userList = RbacPermission::Create('users.list', 'List users');
        $userCreate = RbacPermission::Create('users.create', 'Create a user');
        $userUpdate = RbacPermission::Create('users.update', 'Update a user');
        $userDelete = RbacPermission::Create('users.delete', 'Delete a user');

        $owner = RbacRole::Create('roles/owner', 'Owner', 'Can do everything');
        $owner->updatePermissions([
            $developer,

            $workspacesGet,
            $workspacesList,
            $workspacesCreate,
            $workspacesUpdate,
            $workspacesDelete,

            $userGet,
            $userList,
            $userCreate,
            $userUpdate,
            $userDelete,
        ]);

        $workspaceCreator = RbacRole::Create('roles/workspaces.creator', 'Workspace Creator', 'Create new workspaces');
        $workspaceCreator->updatePermissions([
            $workspacesGet,
            $workspacesList,
            $workspacesCreate
        ]);

        $userAdmin = RbacRole::Create('roles/users.admin', 'User Admin', 'Manage users');
        $userAdmin->updatePermissions([
            $userGet,
            $userList,
            $userCreate,
            $userUpdate,
            $userDelete,
        ]);

        // Set existing users as owner
        /** @var User $users */
        $users = (new UserModel())->find();
        foreach ($users as $user) {
            $user->save($owner);
        }
    }

    public function down() {

    }

}
