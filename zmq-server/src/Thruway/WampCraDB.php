<?php namespace App\Thruway;

use Thruway\Authentication\WampCraUserDbInterface;

class WampCraDB implements WampCraUserDbInterface {

    /**
     * This should take a authid string as the argument and return
     * an associative array with authid, key, and salt.
     *
     * If salt is non-null, the key is the salted version of the password.
     *
     * @param string $authid
     * @return mixed
     */
    public function get($authid) {
        return [
            'authid' => $authid,
            'key' => '3,fqm34f34kf340f3k4qf9'
        ];
    }

}
