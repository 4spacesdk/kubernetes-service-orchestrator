<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class RbacRoleModel extends Model implements ResourceModelInterface {

    public $hasOne = [

    ];

    public $hasMany = [
        RbacPermissionModel::class,
        UserModel::class,
    ];

    public function preRestGet($queryParser, $id) {

    }

    public function postRestGet($queryParser, $items) {

    }

    public function isRestCreationAllowed($item): bool {
        return false;
    }

    public function isRestUpdateAllowed($item): bool {
        return false;
    }

    public function isRestDeleteAllowed($item): bool {
        return false;
    }

    public function appleRestGetManyRelations($items) {

    }

    public function ignoredRestGetOnRelations(): array {
        return [
            UserModel::class,
        ];
    }

}
