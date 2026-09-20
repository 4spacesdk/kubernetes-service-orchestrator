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

    /**
     * `strtotime()`, plus the one repair a time that came through a query string needs: the
     * `+` in an offset like `10:00:00+02:00` is decoded as a space, and `strtotime()` then
     * refuses the value outright.
     *
     * The value is parsed as it stands first, and the spaces are only swapped for `+` when
     * that fails. The other order swapped the space between a date and a time as well, so
     * `2024-05-01 10:00:00` parsed - successfully, which is why the fallback never ran - as
     * midnight at UTC+10, and came back as the day before with the time gone.
     */
    function strtotime_($value) {
        if (is_numeric($value)) return $value;
        $time = strtotime($value);
        if (!$time) {
            $time = strtotime(str_replace(' ', '+', $value));
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
