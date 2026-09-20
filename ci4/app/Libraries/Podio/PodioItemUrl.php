<?php namespace App\Libraries\Podio;

/**
 * The item an url points at.
 *
 * A Podio task link ends in `items/<id>`, and the url itself is pulled out of a commit
 * message - so it is regularly something else, or nothing at all when the message has no
 * link in it. Three places used to split it with `explode('items/', $url)` and read element
 * 1 straight away, which is `Undefined array key 1` for every one of those cases.
 */
class PodioItemUrl {

    public static function itemId(?string $url): ?string {
        $parts = explode('items/', (string) $url);
        if (count($parts) < 2) {
            return null;
        }

        $itemId = trim(end($parts), '/');

        return $itemId === '' ? null : $itemId;
    }

}
