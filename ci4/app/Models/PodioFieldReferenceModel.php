<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class PodioFieldReferenceModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        PodioIntegrationModel::class,
    ];

    public $hasMany = [
        'post_update_action_podio_field_update_field_reference' => [
            'class' => PostUpdateActionModel::class,
            'otherField' => 'podio_field_update_field_reference',
            'joinTable' => 'post_update_actions',
            'joinSelfAs' => 'podio_field_update_field_reference_id',
        ],
        PostUpdateActionConditionModel::class,
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
