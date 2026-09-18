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
     * to its own registry, not to name any image it likes (INT-1c).
     */
    public function hasImage(string $imageUrl): bool {
        return str_starts_with($imageUrl, $this->getUrlPrefix() . '/');
    }

    /**
     * Make the registry tell kso about new tags (INT-1c). A webhook is to call `$webhookUrl`
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
     * @return string[] Oldest version first.
     */
    public abstract function getTags(string $url): array;

    /**
     * Prove the connection works. Returns what the registry answered, in words; throws
     * with the reason when it does not.
     *
     * @throws \Exception
     */
    public abstract function testConnection(): string;

    /**
     * Every repository in the connection, for creating images from (INT-1b).
     *
     * @return array<array{name: string, url: string}> `url` is what an image in it is pulled as.
     * @throws \Exception
     */
    public abstract function listRepositories(): array;

    /**
     * @param string[] $tags
     * @return string[]
     */
    protected static function sortVersions(array $tags): array {
        usort($tags, fn($a, $b) => version_compare(str_replace('v', '', $a), str_replace('v', '', $b)));
        return $tags;
    }

}
