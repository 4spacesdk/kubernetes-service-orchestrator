<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\DatabaseService;
use DebugTool\Data;

class DatabaseServices extends ResourceController {

    /**
     * @ignore true
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

    /**
     * @route /database-services/{id}/test-connection
     * @method get
     * @param int $id
     * @return void
     * @custom true
     * @responseSchema BoolInterface
     */
    public function testConnection($id = 0): void {
        $item = new DatabaseService();
        $item->find($id);

        Data::set('resource', [
            'value' => $item->testConnection(),
        ]);

        $this->success();
    }

}
