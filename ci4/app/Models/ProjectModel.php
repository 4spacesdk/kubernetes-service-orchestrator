<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class ProjectModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        DeletionModel::class,
    ];

    public $hasMany = [
        WorkspaceModel::class,
        WorkspaceTemplateModel::class,
        UserModel::class,
    ];

    /**
     * Read by id, a project is its fields and its members - not every workspace in it, which
     * the workspace list does with a filter.
     */
    public function ignoredRestGetOnRelations(): array {
        return [
            WorkspaceModel::class,
            WorkspaceTemplateModel::class,
        ];
    }

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
