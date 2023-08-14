<?php namespace App\Controllers;

use DebugTool\Data;

class KeelPolicies extends \App\Core\BaseController {

    /**
     * @route /keel_policies
     * @method get
     * @custom true
     * @responseSchema KeelPoliciesGetResponse
     */
    public function get() {
        Data::set('resources', array_map(fn (string $name) => ['name' => $name], \KeelPolicies::All()));
        $this->success();
    }

    public function requireAuth(string $method): bool {
        return true;
    }
}
