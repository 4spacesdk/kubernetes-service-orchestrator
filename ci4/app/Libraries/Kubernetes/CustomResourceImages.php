<?php namespace App\Libraries\Kubernetes;

/**
 * The images a custom resource names - `image: registry/repository:tag` anywhere in it, such as a
 * RabbitmqCluster's `spec.image` - so one of them can be the specification's container image: then
 * it is tracked under Container images, scanned, and auto updated like any other workload.
 *
 * Read from the manifest as written, line by line: it holds placeholders, and is not YAML until
 * they are filled in.
 */
class CustomResourceImages {

    public const string VersionPlaceholder = '${deployment.version}';

    /**
     * @return list<array{reference: string, repository: string, tag: string}> each image once, in order
     */
    public static function Found(string $manifest): array {
        preg_match_all('/^\s*(?:-\s*)?image:\s*["\']?([^\s"\'#]+)/m', $manifest, $matches);
        $found = [];
        foreach ($matches[1] as $reference) {
            if (isset($found[$reference])) {
                continue;
            }
            [$repository, $tag] = self::Split($reference);
            $found[$reference] = ['reference' => $reference, 'repository' => $repository, 'tag' => $tag];
        }
        return array_values($found);
    }

    /**
     * `registry/repository:tag` as repository and tag - the colon after the last slash, so a
     * registry's port is not taken for a tag. No tag is `latest`, as Kubernetes reads it.
     *
     * @return array{0: string, 1: string}
     */
    public static function Split(string $reference): array {
        $reference = explode('@', $reference)[0];
        $slash = strrpos($reference, '/');
        $colon = strrpos($reference, ':');
        if ($colon !== false && ($slash === false || $colon > $slash)) {
            return [substr($reference, 0, $colon), substr($reference, $colon + 1)];
        }
        return [$reference, 'latest'];
    }

    /**
     * The manifest with the image's tag written as the deployment's version, wherever that image is
     * named with it.
     */
    public static function WithVersionPlaceholder(string $manifest, string $reference): string {
        [$repository] = self::Split($reference);
        return preg_replace_callback(
            '/^(\s*(?:-\s*)?image:\s*["\']?)' . preg_quote($reference, '/') . '(?=["\'\s#]|$)/m',
            fn(array $match) => $match[1] . $repository . ':' . self::VersionPlaceholder,
            $manifest
        );
    }

}
