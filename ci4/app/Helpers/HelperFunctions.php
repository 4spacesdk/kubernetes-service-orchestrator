<?php
/**
 * Created by PhpStorm.
 * User: martin
 * Date: 26/11/2018
 * Time: 13.25
 */

use App\Helpers\Client;
use Config\Services;
use DebugTool\Data;

if (!function_exists('isSecureIP')) {

    function isSecureIP() {
        $request = \Config\Services::request();
        return in_array($request->getIPAddress(), [
                '34.255.164.48',
                '188.166.164.243',
                '127.0.0.1',
                '192.168.1.102',
                '192.168.1.108',
                '::1'
            ]) || is_cli();
    }

}

if (!function_exists('isHTTPS')) {

    function isHTTPS() {
        return !empty($_SERVER['HTTP_X_SCHEME']) && strtolower($_SERVER['HTTP_X_SCHEME']) == 'https';
    }

}

if (!function_exists('php')) {

    function php($uri) {
        return 'php -d short_open_tag=on -d memory_limit=3072M -d allow_url_fopen=on ' . FCPATH . 'index.php ' . $uri;
    }

}

if (!function_exists('version')) {

    function version(): string {
        return getenv('TAG_NAME') ? getenv('TAG_NAME') : 'latest';
    }

}

if (!function_exists('getIP')) {

    function getIP(): string {
        $ip = Services::request()->getIPAddress();
        if ($ip == '::1') $ip = '192.168.1.108';

        return $ip;
    }

}

if (!function_exists('ipMatch')) {

    /**
     * Checks if a given IP address matches the specified CIDR subnet/s
     *
     * @param string $ip The IP address to check
     * @param mixed $cidrs The IP subnet (string) or subnets (array) in CIDR notation
     * @param string $match optional If provided, will contain the first matched IP subnet
     * @return boolean TRUE if the IP matches a given subnet or FALSE if it does not
     */
    function ipMatch($ip, $cidrs, &$match = null) {
        foreach ((array)$cidrs as $cidr) {
            list($subnet, $mask) = explode('/', $cidr);
            if (((ip2long($ip) & ($mask = ~((1 << (32 - $mask)) - 1))) == (ip2long($subnet) & $mask))) {
                $match = $cidr;
                return true;
            }
        }
        return false;
    }

}

if (!function_exists('strtotime_')) {

    function strtotime_($value) {
        if (is_numeric($value)) return $value;
        $time = strtotime(str_replace(' ', '+', $value));
        if (!$time) {
            $time = strtotime($value);
        }
        return $time;
    }

}

if (!function_exists('datetimezone')) {
    function datetimezone($value): string {
        try {
            $foo = new DateTime($value, new DateTimeZone("Europe/Copenhagen"));
            $foo->setTimeZone(new DateTimeZone("UTC"));
            return $foo->format('c');
        } catch (\Exception $e) {
            return "";
        }
    }
}

if (!function_exists('getFrontendUrl')) {

    function getFrontendUrl(): string {
        if (str_contains(base_url(), 'localhost')) {
            return 'http://localhost:8951';
        } else {
            return str_replace('/api', '', base_url());
        }
    }

}
