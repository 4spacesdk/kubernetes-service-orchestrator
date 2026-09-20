<?php namespace App\Libraries\ContainerRegistries;

/**
 * An image reference as a registry writes it, split into the image and its tag.
 *
 * The tag is what follows the last colon - but only when that colon comes after the last
 * slash. A registry reachable on a port puts a colon in the host as well
 * (`registry.example.org:5000/team/api:v2.0.0`), and splitting on the first one made the
 * host the image and the rest the tag. Nothing matched, and no update was created.
 */
class ImageReference {

    /**
     * @return array{0: string, 1: ?string} The image, and its tag when the reference has one.
     */
    public static function split(?string $reference): array {
        $reference = (string) $reference;
        $colon = strrpos($reference, ':');

        if ($colon === false || $colon < (strrpos($reference, '/') ?: 0)) {
            return [$reference, null];
        }

        $tag = substr($reference, $colon + 1);

        return $tag === '' ? [$reference, null] : [substr($reference, 0, $colon), $tag];
    }

}
