<?php namespace App\Controllers;

use App\Libraries\OpenApi;
use DebugTool\Data;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 26/11/2018
 * Time: 15.35
 */
class Swagger extends \App\Core\BaseController {

    public function requireAuth(string $method): bool {
        return false;
    }

    public function index($scope = null) {
        echo view('Swagger/index', ['scope' => $scope, 'scopes' => []]);
    }

    public function openapi($scope = null) {
        \DebugTool\Data::debug($scope);
        try {
            $json = OpenApi::run($scope);
            foreach ($json as $key => $value) {
                Data::set($key, $value);
            }
            $this->success();
        } catch (\ReflectionException $e) {
            $this->fail($e->getMessage());
        }
    }

}
