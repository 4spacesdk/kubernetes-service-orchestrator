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
        $me = Client::$user;
        $me->rbac_roles->find();
        foreach ($me->rbac_roles as $role) {
            $role->rbac_permissions->find();
        }
        $this->_setResource($me);
        $this->success();
    }

}
