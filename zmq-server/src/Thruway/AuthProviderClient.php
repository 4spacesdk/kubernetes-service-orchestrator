<?php namespace App\Thruway;

use App\Config\Auth;
use Thruway\Authentication\AbstractAuthProviderClient;
use Thruway\Logging\Logger;

/**
 * Class AuthProviderClient
 * @package App\Thruway
 */
class AuthProviderClient extends AbstractAuthProviderClient {

    /**
     * @return string
     */
    public function getMethodName() {
        return 'boris';
    }

    /**
     * {@inheritDoc}
     */
    public function processAuthenticate($signature, $extra = null) {
        Logger::info($this, "processAuthenticate... " . $signature);

        $auth = Auth::authorize($signature);
        if (isset($auth->authorized) && $auth->authorized) {
            return ["SUCCESS", $auth];
        }
        return ["FAILURE"];

    }
}
