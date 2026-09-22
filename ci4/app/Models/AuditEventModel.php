<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

/**
 * Written by `Audit` only; read through the API.
 */
class AuditEventModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        UserModel::class,
    ];

    public $hasMany = [

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

}
