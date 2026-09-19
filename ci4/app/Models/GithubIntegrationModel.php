<?php namespace App\Models;

use App\Entities\GithubIntegration;
use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class GithubIntegrationModel extends Model implements ResourceModelInterface {

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
     * Not while an image uses it. The image would keep pointing at a deleted integration
     * and lose its commit messages without saying why.
     *
     * Asked without a row, the answer is the policy: deleting is allowed.
     *
     * @param GithubIntegration|null $item
     */
    public function isRestDeleteAllowed($item): bool {
        if ($item === null) {
            return true;
        }
        return (new ContainerImageModel())->where('github_integration_id', $item->id)->countAllResults() === 0;
    }

    public function appleRestGetManyRelations($items) {

    }

}
