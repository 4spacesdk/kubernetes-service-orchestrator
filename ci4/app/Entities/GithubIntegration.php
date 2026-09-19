<?php namespace App\Entities;

use App\Core\Entity;
use App\Libraries\Github\BaseGithub;
use App\Models\GithubIntegrationModel;

/**
 * A GitHub App in one organisation, and its installation there.
 *
 * The App used to sit on the System row, one for the whole instance. It is private - see
 * the manifest - and a private App can only be installed on the account that owns it, so
 * that meant one organisation. Now there is one of these per organisation, and an image
 * names the one its repository lives under.
 *
 * **Everything but the name and the organisation comes from GitHub.** The App's id, keys
 * and installation are written by the setup flow and never taken from a request, and the
 * secrets are hidden from every response - the `has_*` fields say whether one is set.
 *
 * **The setup flow is bound to this row by a state nonce.** kso issues it when the
 * operator starts creating or installing the App, GitHub hands it back on the redirect, and
 * it is used up there. A redirect without it changes nothing, so a link sent to the
 * operator's browser can no longer repoint the installation.
 *
 * Class GithubIntegration
 * @package App\Entities
 * @property string $name
 * @property string $organization Empty for an App on a personal account
 *
 * # From GitHub
 * @property int $app_id
 * @property string $client_id
 * @property string $client_secret
 * @property string $private_key
 * @property string $webhook_secret
 * @property string $slug
 * @property int $installation_id
 *
 * # The setup flow in progress, if any
 * @property string $setup_state
 *
 * # Set or not, in place of the secrets themselves
 * @property bool $has_client_secret
 * @property bool $has_private_key
 * @property bool $has_webhook_secret
 *
 * Many
 * @property ContainerImage $container_images
 */
class GithubIntegration extends Entity {

    public const array SecretFields = ['client_secret', 'private_key', 'webhook_secret'];

    /**
     * Written by the setup flow only.
     */
    public const array FromGithub = ['app_id', 'client_id', ...self::SecretFields, 'slug', 'installation_id', 'setup_state'];

    public $hiddenFields = [...self::SecretFields, 'setup_state'];

    public static function post($data) {
        return parent::post(self::withoutWhatGithubWrites($data));
    }

    public static function patch($id, $data) {
        return parent::patch($id, self::withoutWhatGithubWrites($data));
    }

    private static function withoutWhatGithubWrites($data) {
        foreach (self::FromGithub as $field) {
            unset($data[$field]);
        }
        return $data;
    }

    /**
     * The row a setup redirect belongs to, or null. An empty state matches nothing - every
     * row that is not in the middle of a setup has one.
     */
    public static function findBySetupState(?string $state): ?GithubIntegration {
        if (!is_string($state) || !strlen($state)) {
            return null;
        }
        /** @var GithubIntegration $item */
        $item = (new GithubIntegrationModel())->where('setup_state', $state)->find();
        return $item->exists() ? $item->first() : null;
    }

    public function isAppCreated(): bool {
        return (int) $this->app_id > 0 && strlen((string) $this->private_key) > 0;
    }

    public function isInstalled(): bool {
        return $this->isAppCreated() && (int) $this->installation_id > 0;
    }

    /**
     * Start a step of the setup flow, and use up any step already started.
     */
    public function issueSetupState(): string {
        $this->setup_state = bin2hex(random_bytes(24));
        $this->save();
        return $this->setup_state;
    }

    /**
     * The permission request GitHub shows the operator. Read on contents and metadata is all
     * kso needs to look up a commit; anything more would be granted across every repository
     * the App is installed on. Private, because the App is one customer's orchestrator.
     *
     * @return array<string, mixed>
     */
    public function manifest(string $baseUrl): array {
        $baseUrl = rtrim($baseUrl, '/');
        return [
            'name' => 'KSO - ' . (getenv('PROJECT_NAME') ?: 'Orchestrator'),
            'url' => $baseUrl,
            'redirect_url' => $baseUrl . '/githubapp/callback',
            'setup_url' => $baseUrl . '/githubapp/post-install',
            'public' => false,
            'default_permissions' => [
                'contents' => 'read',
                'metadata' => 'read',
            ],
        ];
    }

    /**
     * Where the manifest is posted to, carrying a fresh state for the callback.
     *
     * @throws \Exception for an organisation name GitHub would not accept
     */
    public function createAppUrl(): string {
        $organization = (string) $this->organization;
        if ($organization !== '' && !preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/', $organization)) {
            throw new \Exception("'{$organization}' is not a GitHub organisation name");
        }
        $path = $organization === '' ? 'settings/apps/new' : "organizations/{$organization}/settings/apps/new";
        return "https://github.com/{$path}?state={$this->issueSetupState()}";
    }

    /**
     * Where the operator installs the App, carrying a fresh state for post-install.
     *
     * @throws \Exception before the App exists
     */
    public function installUrl(): string {
        if (!$this->isAppCreated()) {
            throw new \Exception('the GitHub App has not been created yet');
        }
        return 'https://github.com/apps/' . rawurlencode((string) $this->slug) . "/installations/new?state={$this->issueSetupState()}";
    }

    /**
     * Store the App GitHub made from the manifest. A new App has no installation yet, so
     * any earlier one is forgotten.
     *
     * @param array{id: int, client_id: string, client_secret: string, pem: string, webhook_secret: string, slug: string} $app
     */
    public function storeApp(array $app): void {
        $this->app_id = (int) $app['id'];
        $this->client_id = (string) $app['client_id'];
        $this->client_secret = (string) $app['client_secret'];
        $this->private_key = (string) $app['pem'];
        $this->webhook_secret = (string) ($app['webhook_secret'] ?? '');
        $this->slug = (string) $app['slug'];
        $this->installation_id = 0;
        $this->setup_state = '';
        $this->save();
    }

    public function storeInstallation(int $installationId): void {
        $this->installation_id = $installationId;
        $this->setup_state = '';
        $this->save();
        $this->updateOrganizationFromGithub();
    }

    /**
     * Take the organisation from the installation, where GitHub says which account it lives
     * on. An App on a personal account gets the user's login, which is also where a new App
     * would be made. A GitHub that does not answer leaves the field as it was - the
     * installation is what matters, and this is only the label on it.
     */
    public function updateOrganizationFromGithub(): void {
        if (!$this->isInstalled()) {
            return;
        }
        try {
            $account = $this->getClient()->installationAccount($this);
        } catch (\Throwable) {
            return;
        }
        if ($account !== '') {
            $this->organization = $account;
            $this->save();
        }
    }

    public function getClient(): BaseGithub {
        return service('integrations')->github();
    }

    /**
     * The repositories an image can be tied to: the ones the installation sees, archived
     * ones left out, by name.
     *
     * @return array<array{id: int, full_name: string, name: string}>
     * @throws \Exception
     */
    public function getRepositories(): array {
        if (!$this->isInstalled()) {
            throw new \Exception('the GitHub App is not installed yet');
        }

        $repositories = array_values(array_filter(
            $this->getClient()->listRepositories($this),
            fn (array $repository) => !$repository['archived']
        ));
        usort($repositories, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return array_map(fn (array $repository) => [
            'id' => $repository['id'],
            'full_name' => $repository['full_name'],
            'name' => $repository['name'],
        ], $repositories);
    }

    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false, ?array $fieldsFilter = null): array {
        $item = parent::toArray($onlyChanged, $cast, $recursive, $fieldsFilter);

        foreach (self::SecretFields as $field) {
            $item["has_{$field}"] = strlen((string) $this->{$field}) > 0;
        }

        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|GithubIntegration[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
