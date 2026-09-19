<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\System;
use DebugTool\Data;
use OrmExtension\Extensions\Entity;

class Systems extends ResourceController {

    /**
     * Every System response goes through the allow list. The default implementation
     * returns the whole entity, which handed the GitHub App private key back to the browser
     * on every save of the System page while the App lived there.
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
     * The same for `PATCH /systems` without an id, which answers with a list. That route
     * was the third way the private key got out.
     *
     * @param Entity|System|int $items
     */
    public function _setResources($items) {
        if ($items instanceof System) {
            Data::set('count', $items->count());
            Data::set('resources', array_map(fn (System $item) => $item->toPublicArray(), iterator_to_array($items)));
            return;
        }
        parent::_setResources($items);
    }


    /**
     * The four inherited REST verbs, switched off.
     *
     * `@ignore true` is read by the API parser, so the route generator and swagger leave
     * these verbs out - and no migration wrote them into `api_routes` either, which is the
     * half that actually decides. The annotation does not remove a row that is already
     * there; `Workspaces` and `Deployments` carry the same annotation and are routed
     * anyway.
     *
     * `SystemsApiTest` pins the routes that do exist.
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
