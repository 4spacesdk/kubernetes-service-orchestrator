<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\DatabaseService;
use DebugTool\Data;

class DatabaseServices extends ResourceController {

    /**
     * PUT is switched off; the UI saves with PATCH.
     *
     * `@ignore true` keeps it out of `api_routes`, so this body is never entered. A PUT
     * that was routed would go through the trait's `put()`, which replaces every column -
     * a partial body would blank the password on a service the deployments depend on.
     *
     * @ignore true
     * @codeCoverageIgnore
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
            // A service that is not there cannot be connected to, and that is a "no" rather
            // than an error - the row can be deleted between the list loading and somebody
            // pressing the button. Without the guard the lookup's empty entity reached
            // `prepareConnection()`, where `match ($this->driver)` has no arm for null;
            // `UnhandledMatchError` is an `\Error`, so `testConnection()`'s
            // `catch (\Exception|DatabaseException)` - which turns every other failure into
            // a `false` - let it past as a 500.
            'value' => $item->exists() && $item->testConnection(),
        ]);

        $this->success();
    }

}
