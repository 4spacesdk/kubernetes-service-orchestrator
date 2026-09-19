<?php namespace App\Libraries\ContainerRegistries;

use App\Entities\ContainerRegistry;

/**
 * One registry provider, talking to one connection.
 *
 * Built from the connection rather than from an image, so it can be asked things that are
 * not about any one image - whether the credentials work, which repositories exist. What
 * is about an image takes the image's url.
 */
abstract class BaseContainerRegistry {

    public function __construct(protected ContainerRegistry $registry) {}

    /**
     * What every image url in this registry starts with, without a trailing slash.
     */
    public abstract function getUrlPrefix(): string;

    /**
     * The host an image is pulled from, which is what a docker config names a login by.
     */
    public function getRegistryHost(): string {
        return explode('/', $this->getUrlPrefix())[0];
    }

    /**
     * Whether an image url is one of this registry's. A webhook is trusted to report pushes
     * to its own registry, not to name any image it likes.
     */
    public function hasImage(string $imageUrl): bool {
        return str_starts_with($imageUrl, $this->getUrlPrefix() . '/');
    }

    /**
     * Make the registry tell kso about new tags. A webhook is to call `$webhookUrl`
     * with `Authorization: Bearer $secret`; a registry that kso pulls from instead ignores
     * both. The image urls say where the images live, for a registry that is set up per
     * project. Returns what was done, in words.
     *
     * @param string[] $imageUrls
     * @throws \Exception
     */
    public abstract function setupEvents(string $webhookUrl, string $secret, array $imageUrls): string;

    /**
     * The repository an image url names, in the form this provider's API wants it.
     */
    public abstract function getRepoName(string $url): string;

    /**
     * Throws with the registry's reason rather than answering an empty list, so a refused
     * login cannot be mistaken for an image without tags.
     *
     * @return string[] Oldest version first.
     * @throws \Exception
     */
    public function getTags(string $url): array {
        return array_column($this->getTagDetails($url), 'name');
    }

    /**
     * Each tag with when the image it points at was pushed.
     *
     * @return array<array{name: string, pushed_at: ?string}> Oldest version first. `pushed_at`
     *     is ISO 8601 in UTC, null when the registry does not say.
     * @throws \Exception
     */
    public function getTagDetails(string $url): array {
        $details = $this->fetchTagDetails($url);
        usort($details, fn($a, $b) => self::compareVersions($a['name'], $b['name']));
        return $details;
    }

    /**
     * @return array<array{name: string, pushed_at: ?string}> In any order.
     * @throws \Exception With the registry's reason.
     */
    protected abstract function fetchTagDetails(string $url): array;

    /**
     * Prove the connection works. Returns what the registry answered, in words; throws
     * with the reason when it does not.
     *
     * @throws \Exception
     */
    public abstract function testConnection(): string;

    /**
     * Every repository in the connection, for creating images from.
     *
     * @return array<array{name: string, url: string}> `url` is what an image in it is pulled as.
     * @throws \Exception
     */
    public abstract function listRepositories(): array;

    private static function compareVersions(string $a, string $b): int {
        return version_compare(str_replace('v', '', $a), str_replace('v', '', $b));
    }

    /**
     * A registry's time as ISO 8601 in UTC, so the three providers answer alike.
     */
    protected static function isoTime(?string $time): ?string {
        if (!$time) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($time))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception) {
            return null;
        }
    }

}
