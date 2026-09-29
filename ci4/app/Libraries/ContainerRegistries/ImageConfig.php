<?php namespace App\Libraries\ContainerRegistries;

use App\Entities\ContainerImage;
use App\Libraries\OutboundUrl;

/**
 * An image's config - what its Dockerfile set, `USER` among it - read from its registry over the
 * registry API every provider speaks (OCI distribution, `/v2/`), without pulling a layer: the
 * manifest, and the config blob it names. Seconds, where a Trivy scan pulls the whole image.
 *
 * Logs in with the registry connection's pull credentials when it has them, anonymously otherwise
 * - so a public image needs no connection, and a private one without pull credentials is refused
 * with the registry's reason.
 *
 * Redirects are followed here rather than by curl: a blob is usually served from the registry's
 * storage, and every hop goes through `OutboundUrl` like any url kso calls. The login is not sent
 * to another host - storage takes a signed url, and would refuse or keep it.
 */
class ImageConfig {

    private const array Accept = [
        'application/vnd.oci.image.index.v1+json',
        'application/vnd.docker.distribution.manifest.list.v2+json',
        'application/vnd.oci.image.manifest.v1+json',
        'application/vnd.docker.distribution.manifest.v2+json',
    ];

    /**
     * How one request is made - one, redirects are followed around it. The test suite puts a
     * registry of its own here.
     *
     * @var null|\Closure(string $url, array<string, string> $headers): array{0: int, 1: array<string, string>, 2: string}
     *   The status, the headers by lower-cased name, and the body
     */
    public static ?\Closure $http = null;

    /**
     * The `USER` the tag runs as, as the Dockerfile wrote it - "1000:1000", "appuser" - or empty
     * when it sets none, which is root.
     *
     * @throws \RuntimeException with the registry's reason
     */
    public static function User(ContainerImage $image, string $tag): string {
        return (string) (self::Read($image, $tag)['config']['User'] ?? '');
    }

    /**
     * @return array<string, mixed> The config blob
     * @throws \RuntimeException
     */
    public static function Read(ContainerImage $image, string $tag): array {
        [$host, $repository] = self::Split((string) $image->url);
        $session = new ImageConfigSession($host, $repository, $image->getPullCredentials());

        $manifest = $session->json("/manifests/{$tag}", self::Accept);
        if (isset($manifest['manifests'])) {
            // An index - one image per platform. The one a node in the cluster would run.
            $chosen = null;
            foreach ($manifest['manifests'] as $entry) {
                if (($entry['platform']['os'] ?? '') === 'linux' && ($entry['platform']['architecture'] ?? '') === 'amd64') {
                    $chosen = $entry;
                    break;
                }
            }
            $chosen ??= $manifest['manifests'][0] ?? null;
            if ($chosen === null) {
                throw new \RuntimeException("{$image->url}:{$tag} lists no image");
            }
            $manifest = $session->json("/manifests/{$chosen['digest']}", self::Accept);
        }

        $digest = $manifest['config']['digest'] ?? null;
        if (!$digest) {
            throw new \RuntimeException("{$image->url}:{$tag} has no config");
        }

        return $session->json("/blobs/{$digest}", ['application/json', '*/*']);
    }

    /**
     * The host to call and the repository in it. An image without a registry host is Docker
     * Hub's, and one without a namespace there is an official image, under `library/`.
     *
     * @return array{0: string, 1: string}
     */
    public static function Split(string $url): array {
        $parts = explode('/', trim($url), 2);
        $first = $parts[0];
        if (count($parts) === 2 && (str_contains($first, '.') || str_contains($first, ':') || $first === 'localhost')) {
            return [$first === 'docker.io' ? 'registry-1.docker.io' : $first, $parts[1]];
        }
        return ['registry-1.docker.io', str_contains($url, '/') ? $url : "library/{$url}"];
    }

    /**
     * A GET, following redirects - each through `OutboundUrl`, and without the login when it
     * leaves the host it was sent to.
     *
     * @param array<string, string> $headers
     * @return array{0: int, 1: array<string, string>, 2: string}
     */
    public static function Get(string $url, array $headers): array {
        $sentTo = parse_url($url, PHP_URL_HOST);
        for ($hops = 0; $hops < 5; $hops++) {
            [$status, $received, $body] = self::$http !== null ? (self::$http)($url, $headers) : self::Request($url, $headers);

            if (!in_array($status, [301, 302, 303, 307, 308], true) || !isset($received['location'])) {
                return [$status, $received, $body];
            }
            $next = $received['location'];
            if (!preg_match('#^https?://#i', $next)) {
                $next = preg_replace('#^(https?://[^/]+).*$#i', '$1', $url) . '/' . ltrim($next, '/');
            }
            if (parse_url($next, PHP_URL_HOST) !== $sentTo) {
                unset($headers['Authorization']);
            }
            $url = $next;
        }

        throw new \RuntimeException("{$url} redirects too many times");
    }

    /**
     * @param array<string, string> $headers
     * @return array{0: int, 1: array<string, string>, 2: string}
     */
    private static function Request(string $url, array $headers): array {
        $ch = curl_init();
        OutboundUrl::Apply($ch, $url);
        $received = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array_map(fn ($name, $value) => "{$name}: {$value}", array_keys($headers), $headers),
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$received) {
                $pair = explode(':', $line, 2);
                if (count($pair) === 2) {
                    $received[strtolower(trim($pair[0]))] = trim($pair[1]);
                }
                return strlen($line);
            },
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new \RuntimeException("{$url} could not be reached: {$error}");
        }

        return [$status, $received, (string) $body];
    }

}
