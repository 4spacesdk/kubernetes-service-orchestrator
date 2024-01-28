<?php namespace App\Models;

use App\Entities\InitContainerEnvironmentVariable;
use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

class InitContainerEnvironmentVariableModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        InitContainerModel::class,
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

    public function ignoredRestGetOnRelations(): array {
        return [
            InitContainerModel::class,
        ];
    }

}
