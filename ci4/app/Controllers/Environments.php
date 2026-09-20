<?php namespace App\Controllers;

use DebugTool\Data;

class Environments extends \App\Core\BaseController {

    /**
     * @route /environments
     * @method get
     * @custom true
     * @responseSchema EnvironmentsGetResponse
     */
    public function get() {
        $environments = array_map(fn (string $name) => ['name' => $name], \Environments::All());

        // `count` beside `resources`, like every other collection in the API - see
        // `ResourceController::_setRawResources()`, which this cannot use because this is
        // not a resource controller. A generated client reads the total off the envelope,
        // and this one answered with the field simply missing.
        Data::set('count', count($environments));
        Data::set('resources', $environments);

        $this->success();
    }

    public function requireAuth(string $method): bool {
        return true;
    }
}
