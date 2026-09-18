<?php namespace App\Libraries\ContainerRegistries;

use App\Libraries\GoogleCloud\GcrSubscription;
use DebugTool\Data;
use Google\ApiCore\ApiException;
use Google\ApiCore\ValidationException;
use Google\Cloud\ArtifactRegistry\V1beta2\ArtifactRegistryClient;
use Google\Cloud\ArtifactRegistry\V1beta2\Tag;

class GoogleCloudArtifactRegistry extends BaseContainerRegistry {

    /**
     * Where every image in this repository lives: `{location}-docker.pkg.dev/{project}/{repository}`.
     *
     * Except a gcr.io-domain repository, the kind Container Registry was migrated into.
     * Its images keep their old urls, `eu.gcr.io/{project}/{image}`, and the repository is
     * named after the host.
     */
    public function getUrlPrefix(): string {
        $repository = $this->registry->gcloud_registry_name;
        if (preg_match('/(^|\.)gcr\.io$/', $repository)) {
            return "{$repository}/{$this->registry->gcloud_project}";
        }
        return "{$this->registry->gcloud_location}-docker.pkg.dev/{$this->registry->gcloud_project}/{$repository}";
    }

    /**
     * The image path after the repository, with its slashes. Artifact Registry names a
     * package by that whole path - the api wants the slashes escaped, see `packageName()`.
     *
     * An url that does not start with the prefix falls back to its last segment, which is
     * all this used to take, so an image written some other way resolves as it did before.
     */
    public function getRepoName(string $url): string {
        $prefix = $this->getUrlPrefix() . '/';
        if (str_starts_with($url, $prefix)) {
            return substr($url, strlen($prefix));
        }

        $parts = explode('/', $url);
        return end($parts);
    }

    private function repositoryName(): string {
        return "projects/{$this->registry->gcloud_project}/locations/{$this->registry->gcloud_location}/repositories/{$this->registry->gcloud_registry_name}";
    }

    public function packageName(string $url): string {
        return $this->repositoryName() . '/packages/' . str_replace('/', '%2F', $this->getRepoName($url));
    }

    /**
     * Not measured: this is the network call itself. What kso decides before and after
     * it is tested through the fake behind `BaseContainerRegistry` - see the strategy note in the
     * test setup. Marking it keeps the coverage number about code we chose to test.
     *
     * @codeCoverageIgnore
     */
    public function getTags(string $url): array {
        $items = [];

        try {
            $client = $this->client();
            try {
                $pagedResponse = $client->listTags(['parent' => $this->packageName($url)]);
                /** @var Tag $element */
                foreach ($pagedResponse->iterateAllElements() as $element) {
                    $name = explode('/', $element->getName());
                    $items[] = end($name);
                }

                Data::debug('found', count($items), 'tags');
            } catch (ApiException $e) {
                Data::debug($e->getMessage());
            } finally {
                $client->close();
            }
        } catch (ValidationException $e) {
            Data::debug($e->getMessage());
        }

        return self::sortVersions($items);
    }

    /**
     * @codeCoverageIgnore
     */
    public function listRepositories(): array {
        $client = $this->client();
        try {
            $items = [];
            foreach ($client->listPackages(['parent' => $this->repositoryName()])->iterateAllElements() as $package) {
                $items[] = $this->repository(urldecode(substr($package->getName(), strrpos($package->getName(), '/packages/') + 10)));
            }
            return $items;
        } finally {
            $client->close();
        }
    }

    /**
     * @return array{name: string, url: string}
     */
    public function repository(string $path): array {
        return ['name' => $path, 'url' => "{$this->getUrlPrefix()}/{$path}"];
    }

    /**
     * Pub/Sub, not a webhook: the registry publishes to a topic in the project, and the cron
     * job pulls from a subscription with the connection's own key. Nothing reaches kso from
     * outside, so there is no secret to check.
     */
    public function setupEvents(string $webhookUrl, string $secret, array $imageUrls): string {
        $pubSub = service('integrations')->pubSub();
        $credentials = (string) $this->registry->gcloud_credentials;
        $pubSub->ensureTopic($this->registry->gcloud_project, $credentials, GcrSubscription::TOPIC);
        $pubSub->ensureSubscription($this->registry->gcloud_project, $credentials, GcrSubscription::TOPIC, GcrSubscription::name());
        return "Subscribed to {$this->registry->gcloud_project}/" . GcrSubscription::TOPIC;
    }

    /**
     * @codeCoverageIgnore
     */
    public function testConnection(): string {
        $client = $this->client();
        try {
            $client->getRepository($this->repositoryName());
        } finally {
            $client->close();
        }
        return "Found {$this->getUrlPrefix()}";
    }

    /**
     * @codeCoverageIgnore
     * @throws ValidationException
     */
    protected function client(): ArtifactRegistryClient {
        return new ArtifactRegistryClient([
            'credentials' => json_decode((string) $this->registry->gcloud_credentials, true),
        ]);
    }

}
