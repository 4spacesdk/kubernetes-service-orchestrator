<?php namespace App\Tests\Database\Integrations;

use App\DatabaseTestCase;
use App\Fixtures;
use App\Libraries\ContainerRegistries\AzureContainerRegistry;
use App\Libraries\ContainerRegistries\GoogleCloudArtifactRegistry;
use App\Libraries\ContainerRegistries\HarborRegistry;
use App\Libraries\VersionControlSystems\GithubVersionControl;

/**
 * The part of the registry and version control clients that is not a network call.
 *
 * Each client turns the image url into the names its API wants, and each does it with
 * `substr`, `strpos` and `explode` against a prefix stored on the registry connection. That arithmetic is
 * the only thing in these files that can be wrong without anyone noticing: a repo name that
 * comes out one character short still looks like a repo name, and the failure appears as
 * "no tags found" rather than as an error.
 *
 * **Everything else in those classes is marked out of coverage** - see the note on each
 * method. This is what is left when the network calls are taken away, and it is worth
 * holding on to.
 */
class RegistryNamesTest extends DatabaseTestCase {

    public function testHarborSplitsTheProjectFromTheRepository(): void {
        $registry = new HarborRegistry(Fixtures::containerRegistry(['harbor_url' => 'harbor.example.org']));

        $this->assertSame('team-a', $registry->getProjectName('harbor.example.org/team-a/api'));
        $this->assertSame('api', $registry->getRepoName('harbor.example.org/team-a/api'));
    }

    /**
     * A repository nested below the project keeps its slashes - Harbor takes a path there,
     * and only the first segment is the project.
     */
    public function testHarborKeepsAPathInsideTheRepositoryName(): void {
        $registry = new HarborRegistry(Fixtures::containerRegistry(['harbor_url' => 'harbor.example.org']));

        $this->assertSame('team-a', $registry->getProjectName('harbor.example.org/team-a/group/api'));
        $this->assertSame('group/api', $registry->getRepoName('harbor.example.org/team-a/group/api'));
    }

    public function testAzureStripsTheRegistryName(): void {
        $registry = new AzureContainerRegistry(Fixtures::containerRegistry([
            'provider' => \ContainerRegistries::AzureContainerRegistry,
            'azure_registry_name' => 'acme.azurecr.io',
        ]));

        $this->assertSame('team/api', $registry->getRepoName('acme.azurecr.io/team/api'));
    }

    public function testArtifactRegistryStripsTheRepositoryPrefix(): void {
        $registry = $this->artifactRegistry('a-repo');

        $this->assertSame('europe-docker.pkg.dev/a-project/a-repo', $registry->getUrlPrefix());
        $this->assertSame('api', $registry->getRepoName('europe-docker.pkg.dev/a-project/a-repo/api'));
    }

    /**
     * It used to take the last segment and nothing else, so `team/api` was looked up as
     * `api` and found no tags. The api wants the slash escaped.
     */
    public function testArtifactRegistryKeepsAPathAndEscapesItForTheApi(): void {
        $registry = $this->artifactRegistry('a-repo');
        $url = 'europe-docker.pkg.dev/a-project/a-repo/team/api';

        $this->assertSame('team/api', $registry->getRepoName($url));
        $this->assertSame(
            'projects/a-project/locations/europe/repositories/a-repo/packages/team%2Fapi',
            $registry->packageName($url)
        );
    }

    /**
     * A gcr.io-domain repository - what Container Registry was migrated into - keeps the
     * old image urls, which start with the host and the project.
     */
    public function testAGcrDomainRepositoryUsesTheOldUrlForm(): void {
        $registry = $this->artifactRegistry('eu.gcr.io');

        $this->assertSame('eu.gcr.io/a-project', $registry->getUrlPrefix());
        $this->assertSame('admin-client', $registry->getRepoName('eu.gcr.io/a-project/admin-client'));
    }

    /**
     * An url written some other way resolves as it always did, to its last segment.
     */
    public function testAnUrlOutsideThePrefixFallsBackToItsLastSegment(): void {
        $this->assertSame('api', $this->artifactRegistry('a-repo')->getRepoName('somewhere.else/x/api'));
    }

    public function testGithubSplitsOwnerFromRepository(): void {
        $image = Fixtures::containerImage(['version_control_repository_name' => '4spacesdk/kso']);

        $this->assertSame(['4spacesdk', 'kso'], $this->ownerAndRepo($image));
    }

    /**
     * A name without a slash has no owner, and the whole string is taken as the repository
     * rather than the call being refused. Today's behaviour, and it is why a misconfigured
     * image fails at GitHub rather than in kso.
     */
    public function testANameWithoutAnOwnerIsTakenAsARepositoryOnItsOwn(): void {
        $image = Fixtures::containerImage(['version_control_repository_name' => 'kso']);

        $this->assertSame(['', 'kso'], $this->ownerAndRepo($image));
    }

    private function artifactRegistry(string $repository): GoogleCloudArtifactRegistry {
        return new GoogleCloudArtifactRegistry(Fixtures::containerRegistry([
            'provider' => \ContainerRegistries::ArtifactContainerRegistry,
            'gcloud_project' => 'a-project',
            'gcloud_location' => 'europe',
            'gcloud_registry_name' => $repository,
        ]));
    }

    /**
     * @return array<int, string>
     */
    private function ownerAndRepo(\App\Entities\ContainerImage $image): array {
        $method = (new \ReflectionClass(GithubVersionControl::class))->getMethod('getOwnerAndRepo');

        return $method->invoke(new GithubVersionControl($image));
    }

}
