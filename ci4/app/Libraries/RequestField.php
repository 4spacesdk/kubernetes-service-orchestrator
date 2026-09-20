<?php namespace App\Libraries;

/**
 * A field from a request body, under either spelling.
 *
 * The volume endpoints take their fields the way the columns are named - `mount_path` -
 * where every other collection on the same controllers takes camelCase. A client that
 * followed the pattern used to hit an undefined property and a 500, which says nothing
 * about the field it got wrong. Both spellings are read instead.
 */
class RequestField {

    public static function read(object $data, string $snakeCase, mixed $default = null): mixed {
        $camelCase = lcfirst(str_replace('_', '', ucwords($snakeCase, '_')));

        return $data->{$snakeCase} ?? $data->{$camelCase} ?? $default;
    }

}
