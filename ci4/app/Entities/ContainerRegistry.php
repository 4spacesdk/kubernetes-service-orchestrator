<?php namespace App\Entities;

use App\Core\Entity;
use App\Entities\Concerns\EncryptsFields;
use App\Libraries\ContainerRegistries\BaseContainerRegistry;
use App\Models\ContainerImageModel;
use App\Libraries\Integrations\IntegrationFactory;

/**
 * A connection to a container registry, shared by every image that lives in it.
 *
 * The credentials used to sit on each image, so one key was stored once per image and a
 * rotation meant editing all of them. Now an image points here.
 *
 * **The secrets are write-only.** They are hidden from every response - the `has_*` fields
 * say whether one is set - and a PATCH that leaves one out or sends it empty keeps what is
 * stored, so a form can be saved without re-entering them.
 *
 * Class ContainerRegistry
 * @package App\Entities
 * @property string $name
 * @property string $provider
 *
 * # Google Cloud Artifact Registry
 * @property string $gcloud_project
 * @property string $gcloud_location
 * @property string $gcloud_registry_name
 * @property string $gcloud_credentials
 *
 * # Azure Container Registry
 * @property string $azure_registry_name
 * @property string $azure_tenant
 * @property string $azure_client_id
 * @property string $azure_client_secret
 * @property string $azure_subscription_id
 * @property string $azure_resource_group
 *
 * # Harbor
 * @property string $harbor_url
 * @property string $harbor_username
 * @property string $harbor_password
 *
 * # Pulling, from inside the cluster. Its own, read-only login - see getDockerConfigJson().
 * @property string $pull_username
 * @property string $pull_password
 *
 * # Auto update
 * @property bool $events_enabled
 * @property string $webhook_secret
 *
 * # Set or not, in place of the secrets themselves
 * @property bool $has_gcloud_credentials
 * @property bool $has_azure_client_secret
 * @property bool $has_harbor_password
 * @property bool $has_webhook_secret
 * @property bool $has_pull_password
 *
 * Many
 * @property ContainerImage $container_images
 */
class ContainerRegistry extends Entity {

    public const array EncryptedFields = [...self::SecretFields, self::WebhookSecret];

    use EncryptsFields;

    public const array SecretFields = ['gcloud_credentials', 'azure_client_secret', 'harbor_password', 'pull_password'];

    /**
     * Made by kso when it sets up a webhook, and never taken from a request - a secret the
     * caller chose is one the caller knows.
     */
    public const string WebhookSecret = 'webhook_secret';

    public $hiddenFields = [...self::SecretFields, self::WebhookSecret];

    public static function post($data) {
        unset($data[self::WebhookSecret]);

        /** @var ContainerRegistry $item */
        $item = parent::post($data);
        $item->postSave($data);
        return $item;
    }

    public static function patch($id, $data) {
        unset($data[self::WebhookSecret]);
        foreach (self::SecretFields as $field) {
            if (array_key_exists($field, $data) && !strlen((string) $data[$field])) {
                unset($data[$field]);
            }
        }

        /** @var ContainerRegistry $item */
        $item = parent::patch($id, $data);
        $item->postSave($data);
        return $item;
    }

    /**
     * Turning events on for an Artifact Registry sets them up at once: a topic and a
     * subscription in Google, which the cron job pulls from, and nothing that can fail for
     * lack of a webhook url. Harbor and Azure are set up with their own action, because
     * that writes to the registry and needs rights the connection may not have.
     */
    private function postSave(array $data): void {
        if (isset($data['events_enabled']) && $this->events_enabled
            && $this->provider === \ContainerRegistries::ArtifactContainerRegistry) {
            $this->getClient()?->setupEvents('', '', []);
        }
    }

    /**
     * Make the registry tell kso about new tags, and turn events on.
     *
     * A webhook gets a secret of its own, made here the first time and kept after that, so
     * running this again - after an import into a new project, say - does not break the
     * webhooks already set up. The url carries this connection's id, which is how the
     * webhook endpoint knows which secret to check.
     *
     * @throws \Exception
     */
    public function setupEvents(string $apiBaseUrl): string {
        $client = $this->getClient();
        if ($client === null) {
            throw new \Exception("unsupported provider '{$this->provider}'");
        }

        if (!strlen((string) $this->webhook_secret)) {
            $this->webhook_secret = bin2hex(random_bytes(24));
        }

        $imageUrls = array_column(
            (new ContainerImageModel())->select('url')->where('container_registry_id', $this->id)->find()->allToArray(),
            'url'
        );

        $message = $client->setupEvents($this->webhookUrl($apiBaseUrl), $this->webhook_secret, $imageUrls);

        $this->events_enabled = true;
        $this->save();

        return $message;
    }

    /**
     * Whether kso makes the pull secret for this registry. Only with a login of its
     * own: the one kso reads tags and sets webhooks up with can do more than pull, and a
     * pull secret is readable by anyone who may read secrets in the namespace.
     */
    public function hasPullCredentials(): bool {
        return strlen((string) $this->pull_username) > 0 && strlen((string) $this->pull_password) > 0;
    }

    public function getPullSecretName(): string {
        return "kso-registry-{$this->id}";
    }

    /**
     * The `.dockerconfigjson` of a `kubernetes.io/dockerconfigjson` secret: one login, for
     * the host the images are pulled from.
     */
    public function getDockerConfigJson(): string {
        $host = $this->getClient()?->getRegistryHost() ?? '';
        return json_encode(['auths' => [$host => [
            'username' => $this->pull_username,
            'password' => $this->pull_password,
            'auth' => base64_encode("{$this->pull_username}:{$this->pull_password}"),
        ]]]);
    }

    public function webhookUrl(string $apiBaseUrl): string {
        $path = match ($this->provider) {
            \ContainerRegistries::AzureContainerRegistry => 'azure-container-registry',
            \ContainerRegistries::Harbor => 'harbor',
            default => null,
        };
        return $path === null ? '' : rtrim($apiBaseUrl, '/') . "/auto-updates/webhooks/{$path}/{$this->id}";
    }

    /**
     * Whether a webhook call carries this connection's secret. A connection that never had
     * a webhook set up by kso has no secret and accepts nothing.
     */
    public function acceptsWebhook(?string $authorization): bool {
        return strlen((string) $this->webhook_secret) > 0
            && is_string($authorization)
            && hash_equals("Bearer {$this->webhook_secret}", $authorization);
    }

    public function getClient(): ?BaseContainerRegistry {
        return service('integrations')->containerRegistry($this);
    }

    /**
     * What the registry holds, each with the image already made from it, if any. An image
     * belongs to a repository by its url, whichever connection it points at.
     *
     * @return array<array{name: string, url: string, container_image_id: ?int}>
     * @throws \Exception
     */
    public function getRepositories(): array {
        $client = $this->getClient();
        if ($client === null) {
            throw new \Exception("unsupported provider '{$this->provider}'");
        }

        $repositories = $client->listRepositories();
        $urls = array_column($repositories, 'url');
        $existing = $urls === [] ? [] : array_column(
            (new ContainerImageModel())->select('id, url')->whereIn('url', $urls)->find()->allToArray(),
            'id',
            'url'
        );

        return array_map(fn (array $repository) => [
            ...$repository,
            'container_image_id' => isset($existing[$repository['url']]) ? (int) $existing[$repository['url']] : null,
        ], $repositories);
    }

    /**
     * Make an image for each named repository that does not have one yet.
     *
     * The names are looked up in the registry again rather than trusted: the url an image
     * gets comes from the registry, never from the request. A name the registry does not
     * list is skipped, as is one that already has an image.
     *
     * @param string[] $names
     * @throws \Exception
     */
    public function importRepositories(array $names): ContainerImage {
        $created = new ContainerImage();
        foreach ($this->getRepositories() as $repository) {
            if (!in_array($repository['name'], $names, true) || $repository['container_image_id'] !== null) {
                continue;
            }

            $image = new ContainerImage();
            $image->name = $repository['name'];
            $image->url = $repository['url'];
            $image->container_registry_id = $this->id;
            $image->pull_secret = (string) env('IMAGE_PULL_SECRET_DEFAULT_NAME');
            $image->save();
            $created->add($image);
        }
        return $created;
    }

    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false, ?array $fieldsFilter = null): array {
        $item = parent::toArray($onlyChanged, $cast, $recursive, $fieldsFilter);

        foreach ([...self::SecretFields, self::WebhookSecret] as $field) {
            $item["has_{$field}"] = strlen((string) $this->{$field}) > 0;
        }

        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|ContainerRegistry[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
