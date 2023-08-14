<?php namespace App\Libraries\MicroServices;

use AuthExtension\Entities\OAuthClient;

class AuthServiceMock {

    public static function CreateOAuthClient(): array {
        $client = new OAuthClient();
        $client->client_id = bin2hex(random_bytes(16));
        $client->client_secret = bin2hex(random_bytes(16));
        $client->grant_types = 'client_credentials';
        $client->insert();
        return [
            'client_id' => $client->client_id,
            'client_secret' => $client->client_secret,
        ];
    }

}
