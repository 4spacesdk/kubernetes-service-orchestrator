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
            // Never return the whole System entity here. This endpoint is served without
            // authentication.
            'system' => System::Get()->toPublicArray(),
            'pushServiceUrl' => env('CENTRIFUGO_EXTERNAL_URL'),
            'certManagerIssuerDefaultName' => env('CERT_MANAGER_ISSUER_DEFAULT_NAME'),
            'imagePullSecretDefaultName' => env('IMAGE_PULL_SECRET_DEFAULT_NAME'),
        ];

        if (RestRequest::getInstance()->userId) {
            Client::SetUser(new User((array)RestRequest::getInstance()->userData));
            Client::SetToken(RestRequest::getInstance()->token);
            $data['user'] = Client::$user->toArray();
        }

        return Services::response()->setJSON($data);
    }

}
