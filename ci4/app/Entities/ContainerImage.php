<?php namespace App\Entities;

use App\Libraries\ContainerRegistries\AzureContainerRegistry;
use App\Libraries\ContainerRegistries\BaseContainerRegistry;
use App\Libraries\ContainerRegistries\GoogleCloudArtifactRegistry;
use App\Libraries\GoogleCloud\GoogleCloudPubSub;
use App\Libraries\Kubernetes\KubeHelper;
use RestExtension\Core\Entity;

/**
 * Class ContainerImage
 * @package App\Entities
 * @property string $name
 * @property string $url
 * @property string $pull_secret
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
                        'kso-' . KubeHelper::GetMyHostname() . '.' . KubeHelper::GetMyNamespace()
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

    public function getRepoName(): string {
        return $this->getContainerRegistry()->getRepoName();
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|ContainerImage[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
