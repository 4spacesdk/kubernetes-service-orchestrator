<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

/**
 * Written by `Audit` only; read through the API.
 *
 * No relation to the user, only `user_id`: a relation makes the ORM read `audit_events` whenever
 * it reads `users`, and the migrations before the trail's own save users - a fresh installation
 * stopped there. The app names the users from its own list.
 */
class AuditEventModel extends Model implements ResourceModelInterface {

    public $hasOne = [

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
