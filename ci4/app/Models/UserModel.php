<?php namespace App\Models;

use RestExtension\Ordering\QueryOrder;
use RestExtension\ResourceModelInterface;

class UserModel extends \RestExtension\Models\UserModel implements ResourceModelInterface {

    public $hasOne = [
        DeletionModel::class,
    ];

    public $hasMany = [
        OAuthClientModel::class => [
            'class' => OAuthClientModel::class,
            'otherField' => UserModel::class,
            'joinTable' => 'oauth_clients',
            'joinOtherAs' => 'id',
            'joinSelfAs' => 'user_id',
        ],
        RbacRoleModel::class,
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

    public function applyOrder(QueryOrder $order) {
        if($order->property == 'name') {
            $this
                ->orderBy('first_name', $order->direction)
                ->orderBy('last_name', $order->direction);
            return;
        }
        parent::applyOrder($order);
    }

}
