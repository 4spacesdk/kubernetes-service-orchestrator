<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class PodioIntegrationModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        DeletionModel::class,
    ];

    public $hasMany = [
        'post_update_action_podio_add_comment_integration' => [
            'class' => PostUpdateActionModel::class,
            'otherField' => 'podio_add_comment_integration',
            'joinTable' => 'post_update_actions',
            'joinSelfAs' => 'podio_add_comment_integration_id',
        ],
        PodioFieldReferenceModel::class,
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
