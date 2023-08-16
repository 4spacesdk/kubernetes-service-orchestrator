<?php namespace App\Config;

/**
 * Class Domains
 * @package App\Config
 */
class Domains {

    public static function getAuthUrl($append = null): string {
        $backend = 'http://localhost/api';

        if ($append) {
            $backend .= '/' . $append;
        }
        return $backend;
    }

}
