<?php namespace App\Entities;

use App\Libraries\CommitIdentificationMethods\BaseCommitIdentificationMethod;
use App\Libraries\CommitIdentificationMethods\EnvironmentVariableCommitIdentification;
use App\Libraries\ContainerRegistries\BaseContainerRegistry;
use App\Libraries\ContainerRegistries\ImageConfig;
use App\Libraries\Kubernetes\SecurityContext;
use App\Libraries\VersionControlSystems\BaseVersionControlSystem;
use App\Libraries\VersionControlSystems\GithubVersionControl;
use App\Core\Entity;

/**
 * Class ContainerImage
 * @package App\Entities
 * @property string $name
 * @property string $url
 * @property string $pull_secret
 * @property string $default_tag
 * @property string $default_image_pull_policy
 *
 * # Registry
 * @property int $container_registry_id
 * @property ContainerRegistry $container_registry
 *
 * # Security Context
 * @property string $security_context_fs_group
 * @property string $security_context_run_as_user
 * @property string $security_context_run_as_group
 * @property bool $security_context_allow_privilege_escalation
 * @property bool $security_context_read_only_root_filesystem
 * @property bool $security_context_run_as_non_root stamped from `image_user` when the image is made or read anew
 * @property bool $security_context_drop_all_capabilities
 * @property bool $security_context_seccomp_runtime_default stamped on when the image is made
 * @property string $image_user The `USER` the registry says `image_user_tag` runs as - "1000:1000", "appuser", empty for root
 * @property string $image_user_tag
 * @property string $image_user_read_at
 * @property string $image_user_error Why the user could not be read, the last time it was tried
 *
 *  # Version Control
 * @property bool $version_control_enabled
 * @property string $version_control_provider
 * @property string $version_control_repository_name
 * @property int $github_integration_id
 * @property GithubIntegration $github_integration
 *
 * # Commit Identification
 * @property bool $commit_identification_enabled
 * @property string $commit_identification_method
 * @property string $commit_identification_environment_variable_name
 *
 * # Computed on REST reads
 * @property int[] $running_deployment_ids
 * @property string $security_advice JSON: a list of {level, text} - see `SecurityAdvice`
 */
class ContainerImage extends Entity {

    /**
     * The client for this image's registry, or null when it has none - an image pulled from
     * a public registry, say - or the provider is one kso does not know.
     *
     * Not `getContainerRegistry()`: CodeIgniter reads a `get<Property>()` method as the getter
     * for that property, and `container_registry` is the relation.
     */
    public function getRegistryClient(): ?BaseContainerRegistry {
        return $this->registry()?->getClient();
    }

    /**
     * The credentials this image is pulled with, as a Docker `config.json`: its registry's
     * pull account, the one kso also turns into a pull secret. Null for an image on a public
     * registry, or a registry without a pull account.
     */
    public function getPullCredentialsDockerConfig(): ?string {
        $registry = $this->registry();

        return $registry !== null && $registry->hasPullCredentials() ? $registry->getDockerConfigJson() : null;
    }

    /**
     * The same pull account as `[username, password]`, for a call kso makes itself - reading the
     * image's config from its registry. Null as above.
     *
     * @return array{0: string, 1: string}|null
     */
    public function getPullCredentials(): ?array {
        $registry = $this->registry();

        return $registry !== null && $registry->hasPullCredentials() ? [(string) $registry->pull_username, (string) $registry->pull_password] : null;
    }

    private ?ContainerRegistry $loadedRegistry = null;

    private ?int $registryLoadedFor = null;

    /**
     * The image's registry connection, or null when it has none or it is gone.
     *
     * Loaded by id, once. This was written when a second `find()` on the
     * `container_registry` relation answered with the first connection in the table; the
     * ORM keeps the relation's condition across queries now, so it is no longer what stands
     * between an image and somebody else's registry. It stays because it is still one query
     * per image rather than one per ask, and because "no registry" and "registry is gone"
     * arrive here as the same null on purpose.
     */
    private function registry(): ?ContainerRegistry {
        if (!$this->container_registry_id) {
            return null;
        }
        if ($this->registryLoadedFor !== (int) $this->container_registry_id) {
            $registry = new ContainerRegistry();
            $registry->find($this->container_registry_id);
            $this->loadedRegistry = $registry->exists() ? $registry : null;
            $this->registryLoadedFor = (int) $this->container_registry_id;
        }
        return $this->loadedRegistry;
    }

    /**
     * The secrets a pod pulling this image names: the one kso makes for its registry, if
     * the registry has a pull login, and the one named on the image, if any. Both,
     * so moving an image over to a kso-made secret does not need a moment where it has none.
     *
     * @return string[]
     */
    public function getPullSecretNames(): array {
        $names = [];
        $registry = $this->registry();
        if ($registry?->hasPullCredentials()) {
            $names[] = $registry->getPullSecretName();
        }
        if (strlen((string) $this->pull_secret)) {
            $names[] = $this->pull_secret;
        }
        return $names;
    }

    /**
     * @return string[] Empty when the image has no registry to ask.
     * @throws \Exception When the registry could not be read, with its reason.
     */
    public function getTags(): array {
        return $this->getRegistryClient()?->getTags($this->url) ?? [];
    }

    /**
     * @return array<array{name: string, pushed_at: ?string}> Empty when the image has no registry to ask.
     * @throws \Exception When the registry could not be read, with its reason.
     */
    public function getTagDetails(): array {
        return $this->getRegistryClient()?->getTagDetails($this->url) ?? [];
    }

    /**
     * Read the `USER` a tag runs as from the registry, and stamp `run_as_non_root` from it - on
     * when it is a number other than 0, or when the image sets such a user itself; off otherwise.
     * This is how an image is made secure when it is made, and how it follows an image that
     * changed: read anew, it is stamped anew. See `SecurityContext`.
     *
     * The tag asked for, else the image's default, else its newest. A registry that cannot be
     * read changes no setting, and says why in `image_user_error`.
     *
     * @return bool Whether it was read
     */
    public function readUser(?string $tag = null): bool {
        $this->image_user_read_at = date('Y-m-d H:i:s');
        try {
            $tag = $tag ?: ($this->default_tag ?: (array_slice($this->getTags(), -1)[0] ?? 'latest'));
            $user = ImageConfig::User($this, $tag);
        } catch (\Throwable $e) {
            $this->image_user_error = $e->getMessage();
            $this->save();
            return false;
        }

        $this->image_user = $user;
        $this->image_user_tag = $tag;
        $this->image_user_error = null;
        $this->security_context_run_as_non_root = SecurityContext::IsNonRootUser($user)
            || SecurityContext::IsNonRootUser((string) $this->security_context_run_as_user);
        $this->save();

        return true;
    }

    /**
     * The short sha of the commit this deployment's image was built from, or null when the
     * image has no commit identification set up - which is what every image starts as - or
     * when nothing came back.
     */
    public function getCommitShortSha(Deployment $deployment): ?string {
        $shortSha = $this->getCommitIdentification()?->getCommitShortSha($deployment);

        return strlen((string) $shortSha) ? $shortSha : null;
    }

    public function getVersionControlSystem(): ?BaseVersionControlSystem {
        return service('integrations')->versionControlSystem($this);
    }

    public function getCommitIdentification(): ?BaseCommitIdentificationMethod {
        return service('integrations')->commitIdentification($this);
    }

    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false, ?array $fieldsFilter = null): array {
        $item = parent::toArray($onlyChanged, $cast, $recursive, $fieldsFilter);

        if (isset($this->running_deployment_ids)) {
            $item['running_deployment_ids'] = $this->running_deployment_ids;
        }
        if (isset($this->security_advice)) {
            $item['security_advice'] = $this->security_advice;
        }

        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|ContainerImage[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
