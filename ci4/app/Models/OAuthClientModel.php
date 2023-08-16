<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class OAuthClientModel extends Model implements ResourceModelInterface {

    protected $primaryKey = 'client_id';

    public function getTableName() {
        return 'oauth_clients';
    }

    public $hasOne = [
        UserModel::class => [
            'class' => UserModel::class,
            'otherField' => OAuthClientModel::class,
            'joinTable' => 'oauth_clients',
            'joinOtherAs' => 'user_id',
            'joinSelfAs' => 'id',
        ],
    ];

    public $hasMany = [

    ];

    public function preRestGet($queryParser, $id) {

    }

    public function postRestGet($queryParser, $items) {

    }

    public function isRestCreationAllowed($item): bool {
        return true;
    }

    public function isRestUpdateAllowed($item): bool {
        return true;
    }

    public function isRestDeleteAllowed($item): bool {
        return true;
    }

    public function appleRestGetManyRelations($items) {

    }
}
