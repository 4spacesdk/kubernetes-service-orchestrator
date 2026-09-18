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
 * `substr`, `strpos` and `explode` against a prefix stored on the image. That arithmetic is
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
        $registry = new HarborRegistry(Fixtures::containerImage([
            'url' => 'harbor.example.org/team-a/api',
            'registry_provider_harbor_url' => 'harbor.example.org',
        ]));

        $this->assertSame('team-a', $registry->getProjectName());
        $this->assertSame('api', $registry->getRepoName());
    }

    /**
     * A repository nested below the project keeps its slashes - Harbor takes a path there,
     * and only the first segment is the project.
     */
    public function testHarborKeepsAPathInsideTheRepositoryName(): void {
        $registry = new HarborRegistry(Fixtures::containerImage([
            'url' => 'harbor.example.org/team-a/group/api',
            'registry_provider_harbor_url' => 'harbor.example.org',
        ]));

        $this->assertSame('team-a', $registry->getProjectName());
        $this->assertSame('group/api', $registry->getRepoName());
    }

    public function testAzureStripsTheRegistryName(): void {
        $registry = new AzureContainerRegistry(Fixtures::containerImage([
            'url' => 'acme.azurecr.io/team/api',
            'registry_provider_azure_registry_name' => 'acme.azurecr.io',
        ]));

        $this->assertSame('team/api', $registry->getRepoName());
    }

    /**
     * Artifact Registry takes the last segment and nothing else, where the other two work
     * from a configured prefix. Three registries, three different rules.
     */
    public function testArtifactRegistryTakesTheLastSegment(): void {
        $registry = new GoogleCloudArtifactRegistry(Fixtures::containerImage([
            'url' => 'europe-docker.pkg.dev/a-project/a-repo/api',
        ]));

        $this->assertSame('api', $registry->getRepoName());
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

    /**
     * @return array<int, string>
     */
    private function ownerAndRepo(\App\Entities\ContainerImage $image): array {
        $method = (new \ReflectionClass(GithubVersionControl::class))->getMethod('getOwnerAndRepo');

        return $method->invoke(new GithubVersionControl($image));
    }

}
