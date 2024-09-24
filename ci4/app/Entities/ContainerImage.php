<?php namespace App\Entities;

use App\Libraries\GoogleCloud\GoogleCloudPubSub;
use App\Libraries\Kubernetes\KubeHelper;
use DebugTool\Data;
use Google\ApiCore\ApiException;
use Google\ApiCore\ValidationException;
use Google\Cloud\ArtifactRegistry\V1beta2\ArtifactRegistryClient;
use Google\Cloud\ArtifactRegistry\V1beta2\Tag;
use RestExtension\Core\Entity;

/**
 * Class ContainerImage
 * @package App\Entities
 * @property string $name
 * @property string $url
 * @property string $pull_secret
 * @property bool $registry_subscribe
 * @property string $registry_provider
 * @property string $registry_provider_project
 * @property string $registry_provider_location
 * @property string $registry_provider_name
 * @property string $registry_provider_credentials
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
                    $googleCloudPubSub = new GoogleCloudPubSub($this->registry_provider_project, $this->registry_provider_credentials);
                    $googleCloudPubSub->ensureTopic('gcr');
                    $googleCloudPubSub->ensureSubscription(
                        'gcr',
                        'kso-' . KubeHelper::GetMyHostname() . '.' . KubeHelper::GetMyNamespace()
                    );
                    break;
            }
        }
    }

    public function getTags(): array {
        $items = [];

        $repoParts = explode('/', $this->url);
        $repo = end($repoParts);

        if (strlen($this->registry_provider)) {
            switch ($this->registry_provider) {
                case \ContainerRegistries::ArtifactContainerRegistry:
                    try {
                        $artifactRegistryClient = new ArtifactRegistryClient([
                            'credentials' => json_decode($this->registry_provider_credentials, true),
                        ]);
                        try {
                            Data::debug(get_class($this), "projects/{$this->registry_provider_project}/locations/{$this->registry_provider_location}/repositories/{$this->registry_provider_name}/packages/{$repo}");
                            // Iterate through all elements
                            $pagedResponse = $artifactRegistryClient->listTags([
                                'parent' => "projects/{$this->registry_provider_project}/locations/{$this->registry_provider_location}/repositories/{$this->registry_provider_name}/packages/{$repo}"
                            ]);
                            /** @var Tag $element */
                            foreach ($pagedResponse->iterateAllElements() as $element) {
                                $name = explode('/', $element->getName());
                                $items[] = end($name);
                            }

                            Data::debug('found', count($items), 'tags');
                        } catch (ApiException $e) {
                            Data::debug(get_class($this), $e->getMessage());
                        } finally {
                            $artifactRegistryClient->close();
                        }
                    } catch (ValidationException $e) {
                        Data::debug(get_class($this), $e->getMessage());
                    }

                    usort($items, fn($a, $b) => version_compare(str_replace('v', '', $a), str_replace('v', '', $b)));
            }
        } else {
            Data::debug(get_class($this), $this->name, 'does not have registry provider');
        }

        return $items;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|ContainerImage[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
