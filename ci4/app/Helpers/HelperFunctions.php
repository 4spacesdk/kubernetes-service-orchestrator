<?php
/**
 * Created by PhpStorm.
 * User: martin
 * Date: 26/11/2018
 * Time: 13.25
 */

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

    function getFrontendUrl(?string $path = null): string {
        if (str_contains(base_url(), 'localhost')
            || (strlen(getenv('DEV_REMOTE_BASE_URL'))
                && str_contains(base_url(), getenv('DEV_REMOTE_BASE_URL')))) {
            $url = 'http://localhost:8951';
        } else {
            $url = str_replace('/api', '', base_url());
        }

        if (str_ends_with($url, '/')) {
            $url = substr($url, 0, -1);
        }

        if ($path && strlen($path) > 0) {
            $url .= str_starts_with($path, '/') ? $path : ('/' . $path);
        }

        return $url;
    }

}
