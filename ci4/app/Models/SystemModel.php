<?php namespace App\Models;

use OrmExtension\Extensions\Model;
use RestExtension\ResourceModelInterface;

class SystemModel extends Model implements ResourceModelInterface {

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
        return true;
    }

    public function isRestDeleteAllowed($item): bool {
        return false;
    }

    public function appleRestGetManyRelations($items) {

    }

    public function ignoredRestGetOnRelations(): array {
        return [

        ];
    }

}
