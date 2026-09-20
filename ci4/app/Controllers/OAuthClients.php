<?php namespace App\Controllers;

use App\Core\ResourceController;

class OAuthClients extends ResourceController {

    /**
     * No blanket replace. The generic `put()` writes every column of the row, and the ones a
     * request leaves out become null - on this resource that is the client's secret. kso updates
     * with PATCH everywhere, and nothing ever called this; it existed only because the route
     * generator finds the inherited method.
     *
     * @ignore true
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

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
