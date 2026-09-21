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

    /**
     * Only in development, unless an installation turns it on with `SWAGGER_ENABLED=true`.
     *
     * Both routes answer without a token, and have to: the page fetches the document before
     * anyone can sign in through it. The document is every endpoint, every model and every
     * field of the API - a map of the attack surface - and building it reflects over the
     * whole code base on each call, several seconds of work for anyone who asks.
     */
    public static function IsEnabled(): bool {
        return ENVIRONMENT === 'development' || strtolower((string) getenv('SWAGGER_ENABLED')) === 'true';
    }

    public function index($scope = null) {
        if (!self::IsEnabled()) {
            $this->fail('Not found', 404);
            return;
        }
        echo view('Swagger/index', ['scope' => $scope, 'scopes' => []]);
    }

    public function openapi($scope = null) {
        if (!self::IsEnabled()) {
            $this->fail('Not found', 404);
            return;
        }
        \DebugTool\Data::debug($scope);
        try {
            $json = OpenApi::run($scope);
            foreach ($json as $key => $value) {
                Data::set($key, $value);
            }
            $this->success();
        } catch (\ReflectionException $e) {
            $this->fail($e->getMessage());
            return;
        }
    }

}
