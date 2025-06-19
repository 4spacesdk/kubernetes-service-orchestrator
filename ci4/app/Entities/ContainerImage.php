<?php namespace App\Entities;

use App\Libraries\CommitIdentificationMethods\BaseCommitIdentificationMethod;
use App\Libraries\CommitIdentificationMethods\EnvironmentVariableCommitIdentification;
use App\Libraries\ContainerRegistries\AzureContainerRegistry;
use App\Libraries\ContainerRegistries\BaseContainerRegistry;
use App\Libraries\ContainerRegistries\GoogleCloudArtifactRegistry;
use App\Libraries\GoogleCloud\GoogleCloudPubSub;
use App\Libraries\Kubernetes\KubeHelper;
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
 *
 * # Registry Settings
 * @property bool $registry_subscribe
 * @property string $registry_provider
 * @property string $registry_provider_gcloud_registry_name
 * @property string $registry_provider_gcloud_project
 * @property string $registry_provider_gcloud_location
 * @property string $registry_provider_gcloud_credentials
 * @property string $registry_provider_azure_registry_name
 * @property string $registry_provider_azure_tenant
 * @property string $registry_provider_azure_client_id
 * @property string $registry_provider_azure_client_secret
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
 * @property string $version_control_provider_github_auth_token
 * @property string $version_control_provider_github_auth_user
 *
 * # Commit Identification
 * @property bool $commit_identification_enabled
 * @property string $commit_identification_method
 * @property string $commit_identification_environment_variable_name
 */
class ContainerImage extends Entity {

    public static function post($data) {
        /** @var ContainerImage $item */
        $item = parent::post($data);
        $item->postSave($data);
        return $item;
    }

    public static function patch($id, $data) {
        /** @var ContainerImage $item */
        $item = parent::patch($id, $data);
        $item->postSave($data);
        return $item;
    }

    private function postSave(array $data): void {
        if (isset($data['registry_subscribe'])
            && $this->registry_subscribe && strlen($this->registry_provider)) {
            switch ($this->registry_provider) {
                case \ContainerRegistries::ArtifactContainerRegistry:
                    $googleCloudPubSub = new GoogleCloudPubSub($this->registry_provider_gcloud_project, $this->registry_provider_gcloud_credentials);
                    $googleCloudPubSub->ensureTopic('gcr');
                    $googleCloudPubSub->ensureSubscription(
                        'gcr',
                        str_replace(' ', '_', strtolower(getenv('PROJECT_NAME'))) . '.kso-' . KubeHelper::GetMyHostname() . '.' . KubeHelper::GetMyNamespace()
                    );
                    break;
            }
        }
    }

    public function getContainerRegistry(): ?BaseContainerRegistry {
        switch ($this->registry_provider) {
            case \ContainerRegistries::ArtifactContainerRegistry:
                return new GoogleCloudArtifactRegistry($this);
            case \ContainerRegistries::AzureContainerRegistry:
                return new AzureContainerRegistry($this);
        }
        return null;
    }

    public function getTags(): array {
        return $this->getContainerRegistry()->getTags();
    }

    public function getRegistryRepoName(): string {
        return $this->getContainerRegistry()->getRepoName();
    }

    public function getVersionControlSystem(): ?BaseVersionControlSystem {
        switch ($this->version_control_provider) {
            case \VersionControlProviders::GitHub:
                return new GithubVersionControl($this);
        }
        return null;
    }

    public function getCommitIdentification(): ?BaseCommitIdentificationMethod {
        switch ($this->commit_identification_method) {
            case \CommitIdentificationMethods::EnvironmentVariable:
                return new EnvironmentVariableCommitIdentification($this);
        }
        return null;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|ContainerImage[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
