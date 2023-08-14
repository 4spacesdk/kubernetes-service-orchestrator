<?php namespace App\Helpers;

class AuthToken {

    public string $accessToken;
    public string $clientId;
    public int $userId;
    public int $expires;
    public string $scope;

    public function __construct(array $data) {
        if (isset($data['access_token'])) {
            $this->accessToken = $data['access_token'];
        }
        if (isset($data['client_id'])) {
            $this->clientId = $data['client_id'];
        }
        if (isset($data['user_id'])) {
            $this->userId = (int)$data['user_id'];
        }
        if (isset($data['expires'])) {
            $this->expires = $data['expires'];
        }
        if (isset($data['scope'])) {
            $this->scope = $data['scope'];
        }
    }

    public function getScopes(): array {
        return strlen($this->scope) ? explode(' ', $this->scope) : [];
    }

}
