<?php namespace App\Controllers;

use App\Entities\KeelHookQueueItem;

class KeelHookQueueItems extends \App\Core\ResourceController {

    /**
     * @route /keel-hook-queue-items/{id}/rerun
     * @method post
     * @custom true
     * @param int $id
     * @return void
     */
    public function rerun(int $id): void {
        $item = new KeelHookQueueItem();
        $item->find($id);
        $item->run();
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @ignore true
     * @return void
     */
    public function post() {
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
    public function delete($id) {
    }

    public function requireAuth(string $method): bool {
        return false;
    }
}
