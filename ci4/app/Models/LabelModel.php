<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class LabelModel extends Model implements ResourceModelInterface {

    public $hasOne = [

    ];

    public $hasMany = [
        WorkspaceModel::class,
        WorkspaceTemplateModel::class,
        DeploymentModel::class,
        DeploymentsLabelModel::class,
        DeploymentSpecificationModel::class,
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

    /**
     * Not reachable, and kept anyway.
     *
     * `applyRestGetOneRelations()` is the only caller and it runs on a by-id read, which
     * a label has no route for - so this never runs today and the lines below never
     * count as covered. It is kept because it says something the trait's default does not:
     * the day this resource is routed, the relation named here is one a by-id read should
     * not drag along. The nine models that overrode this with the default's own `[]` were
     * removed instead.
     */
    public function ignoredRestGetOnRelations(): array {
        return [
            WorkspaceModel::class,
        ];
    }

}
