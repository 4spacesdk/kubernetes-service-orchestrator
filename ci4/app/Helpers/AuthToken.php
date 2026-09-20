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
            // Cast like the user id above: the auth extension runs the column through
            // `strtotime()`, which answers `false` for a timestamp it cannot read, and
            // `false` on an `int` property is a fatal error. Zero is the epoch, so such a
            // token reads as long expired - the safe direction.
            $this->expires = (int) $data['expires'];
        }
        if (isset($data['scope'])) {
            $this->scope = $data['scope'];
        }
    }

    /**
     * A token that carries no scope claim at all grants nothing, the same as one whose
     * claim is empty. The properties stay without defaults - see the constructor - so this
     * asks rather than reading an uninitialised one, which is a fatal error and turns the
     * answer "no" into a 500.
     *
     * @return string[]
     */
    public function getScopes(): array {
        if (!isset($this->scope) || !strlen($this->scope)) {
            return [];
        }

        return explode(' ', $this->scope);
    }

}
