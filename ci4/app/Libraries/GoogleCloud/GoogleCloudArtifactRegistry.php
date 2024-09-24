<?php namespace App\Libraries\GoogleCloud;

use Google\ApiCore\ApiException;
use Google\ApiCore\ValidationException;
use Google\Cloud\ArtifactRegistry\V1beta2\ArtifactRegistryClient;
use Google\Cloud\ArtifactRegistry\V1beta2\Version;
use Google\Cloud\ArtifactRegistry\V1beta2\VersionView;

class GoogleCloudArtifactRegistry {

    private string $repo;

    public function __construct(string $repo) {
        $parts = explode('/', $repo);
        $this->repo = end($parts);
    }

    /**
     * @throws ValidationException
     * @throws ApiException
     */
    public function getVersions(): array {
        $items = [];

        $project = getenv('GCLOUD_PROJECT_ID');
        $artifactRegistryClient = new ArtifactRegistryClient([
            'credentials' => json_decode(base64_decode(getenv('GCLOUD_SERVICE_KEY_FILE')), true),
            'projectId' => $project,
        ]);
        try {
            // Iterate through all elements
            $pagedResponse = $artifactRegistryClient->listVersions([
                'view' => VersionView::FULL,
                'parent' => "projects/$project/locations/europe/repositories/eu.gcr.io/packages/{$this->repo}"
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
        $project = getenv('GCLOUD_PROJECT_ID');
        $artifactRegistryClient = new ArtifactRegistryClient([
            'credentials' => json_decode(base64_decode(getenv('GCLOUD_SERVICE_KEY_FILE')), true),
            'projectId' => $project,
        ]);
        try {
            $artifactRegistryClient->deleteVersion([
                'name' => "projects/$project/locations/europe/repositories/eu.gcr.io/packages/{$this->repo}/versions/$version",
            ]);
        } finally {
            $artifactRegistryClient->close();
        }
    }

}
