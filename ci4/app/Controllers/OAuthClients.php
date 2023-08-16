<?php namespace App\Controllers;

use App\Core\ResourceController;

class OAuthClients extends ResourceController {

    public function requireAuth(string $method): bool {
        return true;
    }

    /**
     * @route /o_auth_clients/{id}
     * @method get
     * @custom true
     * @param string $id
     */
    public function get($id = 0) {
        parent::get($id);
    }

    /**
     * @route /o_auth_clients/{id}
     * @method patch
     * @custom true
     * @param string $id
     */
    public function patch($id = 0) {
        parent::patch($id);
    }

    /**
     * @route /o_auth_clients/{id}
     * @method delete
     * @custom true
     * @param string $id
     */
    public function delete($id = 0) {
        parent::delete($id);
    }

}
