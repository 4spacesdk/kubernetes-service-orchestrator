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
     * The four inherited REST verbs, switched off.
     *
     * `@ignore true` is read by the API parser, so the route generator and swagger leave
     * these verbs out - and no migration wrote them into `api_routes` either, which is the
     * half that actually decides. The annotation does not remove a row that is already
     * there; `Workspaces` and `Deployments` carry the same annotation and are routed
     * anyway. See SEC-11.
     *
     * GET is the one that matters here: a listing goes through `_setResources()`, which is
     * not overridden, and would hand out the whole entity. `SystemsApiTest` pins the routes
     * that do exist.
     *
     * @ignore true
     * @codeCoverageIgnore
     * @param $id
     * @return void
     */
    public function get($id = 0) {
    }

    /**
     * @ignore true
     * @codeCoverageIgnore
     * @param $id
     * @return void
     */
    public function post($id = 0) {
    }

    /**
     * @ignore true
     * @codeCoverageIgnore
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

    /**
     * @ignore true
     * @codeCoverageIgnore
     * @param $id
     * @return void
     */
    public function delete($id = 0) {
    }

}
