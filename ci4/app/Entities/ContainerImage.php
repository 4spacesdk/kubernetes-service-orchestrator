<?php namespace App\Entities;

use App\Libraries\CommitIdentificationMethods\BaseCommitIdentificationMethod;
use App\Libraries\CommitIdentificationMethods\EnvironmentVariableCommitIdentification;
use App\Libraries\ContainerRegistries\BaseContainerRegistry;
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

    private ?ContainerRegistry $loadedRegistry = null;

    private ?int $registryLoadedFor = null;

    /**
     * The image's registry connection, or null when it has none or it is gone.
     *
     * Loaded by id, once. `find()` on the `container_registry` relation can only be asked
     * once: the first call uses up the join, and a second is a query without it that
     * answers with the first connection in the table.
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

    public function getVersionControlSystem(): ?BaseVersionControlSystem {
        return service('integrations')->versionControlSystem($this);
    }

    public function getCommitIdentification(): ?BaseCommitIdentificationMethod {
        return service('integrations')->commitIdentification($this);
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|ContainerImage[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
