<?php namespace App\Config;

use Thruway\Logging\Logger;

/**
 * Class Config
 * @package App
 */
class Auth {

    public static function authorize(string $accessToken) {
        $url = Domains::getAuthUrl('connect/check?');
        $url .= http_build_query(['access_token' => $accessToken]);
        Logger::info(null, "Auth::authorize... " . $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $raw = curl_exec($ch);
        $json = json_decode($raw);
        if (!$json) {
            Logger::warning(null, 'Auth failed!');
            Logger::warning(null, curl_error($ch));
            Logger::warning(null, $raw);
        }
        curl_close($ch);

        return $json;
    }

}
