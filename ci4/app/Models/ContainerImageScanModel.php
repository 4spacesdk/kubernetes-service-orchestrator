<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;

/**
 * Written by the scanner only; read through the API.
 */
class ContainerImageScanModel extends Model implements ResourceModelInterface {

    public $hasOne = [
        ContainerImageModel::class,
    ];

    public $hasMany = [

    ];

    /**
     * A list carries the counts, not the findings: an image can have hundreds, and a list of
     * every running tag would carry all of them. `container_image_scans/{id}` has them.
     */
    public function preRestGet($queryParser, $id) {
        if (!$id && !$queryParser->isCount()) {
            $this->select(implode(', ', array_diff($this->getTableFields(), ['findings'])));
        }
    }

    public function postRestGet($queryParser, $items) {

    }

    public function isRestCreationAllowed($item): bool {
        return false;
    }

    public function isRestUpdateAllowed($item): bool {
        return false;
    }

    public function isRestDeleteAllowed($item): bool {
        return false;
    }

    public function appleRestGetManyRelations($items) {

    }

}
