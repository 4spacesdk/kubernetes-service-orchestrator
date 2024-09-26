<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class PostUpdateActionModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        DeletionModel::class,
        'podio_add_comment_integration' => [
            'class' => PodioIntegrationModel::class,
            'otherField' => 'post_update_action_podio_add_comment_integration',
            'joinSelfAs' => 'id',
            'joinTable' => 'post_update_actions',
        ],
        'podio_field_update_field_reference' => [
            'class' => PodioFieldReferenceModel::class,
            'otherField' => 'post_update_action_podio_field_update_field_reference',
            'joinSelfAs' => 'id',
            'joinTable' => 'post_update_actions',
        ],
    ];

    public $hasMany = [
        PostUpdateActionConditionModel::class,
        DeploymentSpecificationPostUpdateActionModel::class,
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

    public function ignoredRestGetOnRelations(): array {
        return [
            DeploymentSpecificationPostUpdateActionModel::class,
        ];
    }

}
