<?php namespace App\Libraries\ContainerRegistries;

use App\Entities\ContainerImage;
use DebugTool\Data;
use Google\ApiCore\ApiException;
use Google\ApiCore\ValidationException;
use Google\Cloud\ArtifactRegistry\V1beta2\ArtifactRegistryClient;
use Google\Cloud\ArtifactRegistry\V1beta2\Tag;
use Google\Cloud\ArtifactRegistry\V1beta2\Version;
use Google\Cloud\ArtifactRegistry\V1beta2\VersionView;

class GoogleCloudArtifactRegistry extends BaseContainerRegistry {

    private ContainerImage $image;

    public function __construct(ContainerImage $image) {
        $this->image = $image;
    }

    public function getTags(): array {
        $items = [];

        try {
            $artifactRegistryClient = new ArtifactRegistryClient([
                'credentials' => json_decode($this->image->registry_provider_gcloud_credentials, true),
            ]);
            try {
                // Iterate through all elements
                $pagedResponse = $artifactRegistryClient->listTags([
                    'parent' => "projects/{$this->image->registry_provider_gcloud_project}/locations/{$this->image->registry_provider_gcloud_location}/repositories/{$this->image->registry_provider_gcloud_registry_name}/packages/{$this->image->getRegistryRepoName()}"
                ]);
                /** @var Tag $element */
                foreach ($pagedResponse->iterateAllElements() as $element) {
                    $name = explode('/', $element->getName());
                    $items[] = end($name);
                }

                Data::debug('found', count($items), 'tags');
            } catch (ApiException $e) {
                Data::debug($e->getMessage());
            } finally {
                $artifactRegistryClient->close();
            }
        } catch (ValidationException $e) {
            Data::debug($e->getMessage());
        }

        usort($items, fn($a, $b) => version_compare(str_replace('v', '', $a), str_replace('v', '', $b)));

        return $items;
    }

    /**
     * @throws ValidationException
     * @throws ApiException
     */
    public function getVersions(): array {
        $items = [];

        $artifactRegistryClient = new ArtifactRegistryClient([
            'credentials' => json_decode($this->image->registry_provider_gcloud_credentials, true),
            'projectId' => $this->image->registry_provider_gcloud_project,
        ]);
        try {
            // Iterate through all elements
            $pagedResponse = $artifactRegistryClient->listVersions([
                'view' => VersionView::FULL,
                'parent' => "projects/{$this->image->registry_provider_gcloud_project}/locations/{$this->image->registry_provider_gcloud_location}/repositories/{$this->image->registry_provider_gcloud_registry_name}/packages/{$this->image->getRegistryRepoName()}"
            ]);
            /** @var Version $element */
            foreach ($pagedResponse->iterateAllElements() as $element) {
                if ($element->getRelatedTags()->count() == 0) {
                    $name = explode('/', $element->getName());
                    $items[] = end($name);
                }
            }

        } finally {
            $artifactRegistryClient->close();
        }

        return $items;
    }

    /**
     * @throws ValidationException
     * @throws ApiException
     */
    public function deleteVersions(string $version): void {
        $artifactRegistryClient = new ArtifactRegistryClient([
            'credentials' => json_decode($this->image->registry_provider_gcloud_credentials, true),
            'projectId' => $this->image->registry_provider_gcloud_project,
        ]);
        try {
            $artifactRegistryClient->deleteVersion([
                'name' => "projects/{$this->image->registry_provider_gcloud_project}/locations/{$this->image->registry_provider_gcloud_location}/repositories/{$this->image->registry_provider_gcloud_registry_name}/packages/{$this->image->getRegistryRepoName()}/versions/$version",
            ]);
        } finally {
            $artifactRegistryClient->close();
        }
    }

    public function getRepoName(): string {
        $parts = explode('/', $this->image->url);
        return end($parts);
    }

}
