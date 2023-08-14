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
        Data::set('resources', array_map(fn (string $name) => ['name' => $name], \Environments::All()));
        $this->success();
    }

    public function requireAuth(string $method): bool {
        return true;
    }
}
