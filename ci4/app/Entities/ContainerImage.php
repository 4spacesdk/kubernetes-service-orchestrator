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
        if (!$this->container_registry_id) {
            return null;
        }
        $this->container_registry->find();
        if (!$this->container_registry->exists()) {
            return null;
        }
        return $this->container_registry->getClient();
    }

    /**
     * The secrets a pod pulling this image names: the one kso makes for its registry, if
     * the registry has a pull login, and the one named on the image, if any (INT-1d). Both,
     * so moving an image over to a kso-made secret does not need a moment where it has none.
     *
     * @return string[]
     */
    public function getPullSecretNames(): array {
        $names = [];
        if ($this->container_registry_id) {
            $this->container_registry->find();
            if ($this->container_registry->exists() && $this->container_registry->hasPullCredentials()) {
                $names[] = $this->container_registry->getPullSecretName();
            }
        }
        if (strlen((string) $this->pull_secret)) {
            $names[] = $this->pull_secret;
        }
        return $names;
    }

    /**
     * @return string[] Empty when the image has no registry to ask.
     */
    public function getTags(): array {
        return $this->getRegistryClient()?->getTags($this->url) ?? [];
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
