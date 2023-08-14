<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Helpers\Client;

class Users extends ResourceController {

    /**
     * @route /users/me
     * @method get
     * @custom true
     */
    public function me() {
        $this->_setResource(Client::$user);
        $this->success();
    }

}
