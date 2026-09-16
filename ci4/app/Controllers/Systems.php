<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\System;
use DebugTool\Data;
use OrmExtension\Extensions\Entity;

class Systems extends ResourceController {

    /**
     * The System entity holds the GitHub App credentials. The default implementation
     * returns the whole entity, which handed the private key back to the browser on
     * every save of the System page.
     *
     * @param Entity|System $item
     */
    public function _setResource($item) {
        if ($item instanceof System) {
            Data::set('resource', $item->toPublicArray());
            return;
        }
        parent::_setResource($item);
    }


    /**
     * @ignore true
     * @param $id
     * @return void
     */
    public function get($id = 0) {
    }

    /**
     * @ignore true
     * @param $id
     * @return void
     */
    public function post($id = 0) {
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
    public function delete($id = 0) {
    }

}
