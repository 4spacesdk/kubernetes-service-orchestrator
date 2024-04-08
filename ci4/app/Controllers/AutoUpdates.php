<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\AutoUpdate;

class AutoUpdates extends ResourceController {

    /**
     * @route /auto-updates/{id}/approve
     * @method put
     * @custom true
     * @param int $id
     * @return void
     */
    public function approve(int $id): void {
        $item = new AutoUpdate();
        $item->find($id);
        if ($item->exists()) {
            $item->approve();
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @ignore true
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

    /**
     * @ignore true
     * @param $id
     * @return void
     */
    public function patch($id = 0) {
    }

    /**
     * @ignore true
     * @param $id
     * @return void
     */
    public function delete($id = 0) {
    }

}
