<?php namespace App\Controllers;

use App\Entities\System;
use App\Entities\User;
use App\Helpers\Client;
use Config\Services;
use RestExtension\RestRequest;

class Settings extends \App\Core\BaseController {

    public function requireAuth(string $method): bool {
        return false;
    }

    public function index() {
        $data = [
            'system' => System::Get()->toArray(),
            'pushServiceUrl' => getenv('ZMQ_EXTERNAL_URL'),
        ];

        if (RestRequest::getInstance()->userId) {
            Client::SetUser(new User((array)RestRequest::getInstance()->userData));
            Client::SetToken(RestRequest::getInstance()->token);
            $data['user'] = Client::$user->toArray();
        }

        return Services::response()->setJSON($data);
    }

}
