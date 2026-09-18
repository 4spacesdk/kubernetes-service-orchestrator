<?php namespace App\Models;

use App\Entities\ContainerRegistry;
use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class ContainerRegistryModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        DeletionModel::class,
    ];

    public $hasMany = [
        ContainerImageModel::class,
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

    /**
     * Not while an image uses it. The image would keep pointing at a deleted connection
     * and lose its tags and its events without saying why.
     *
     * Asked without a row, the answer is the policy: deleting is allowed.
     *
     * @param ContainerRegistry|null $item
     */
    public function isRestDeleteAllowed($item): bool {
        if ($item === null) {
            return true;
        }
        return (new ContainerImageModel())->where('container_registry_id', $item->id)->countAllResults() === 0;
    }

    public function appleRestGetManyRelations($items) {

    }

}
