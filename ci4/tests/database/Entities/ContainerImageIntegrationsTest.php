<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Fixtures;
use App\Libraries\ContainerRegistries\AzureContainerRegistry;
use App\Libraries\ContainerRegistries\GoogleCloudArtifactRegistry;
use App\Libraries\ContainerRegistries\HarborRegistry;
use App\Libraries\Github\GithubApi;
use App\Libraries\GoogleCloud\GcrSubscription;
use App\Libraries\GoogleCloud\PubSubApi;
use App\Libraries\Podio\PodioApi;
use App\Libraries\VersionControlSystems\GithubVersionControl;
use App\Tests\Fakes\FakeIntegrations;

/**
 * Which outside system a container image resolves to, and what happens when it resolves
 * to nothing.
 *
 * The three lookups used to be `switch` statements inside the entity, which put a network
 * client one method call away from a data class. They are a service now, so this file can
 * check both halves: that the real factory picks the right implementation, and that the
 * code above it copes when there is no implementation to pick.
 */
class ContainerImageIntegrationsTest extends DatabaseTestCase {

    public function tearDown(): void {
        FakeIntegrations::uninstall();

        parent::tearDown();
    }

    // <editor-fold desc="The real factory">

    public function testEachRegistryProviderResolvesToItsOwnImplementation(): void {
        $expected = [
            \ContainerRegistries::ArtifactContainerRegistry => GoogleCloudArtifactRegistry::class,
            \ContainerRegistries::AzureContainerRegistry => AzureContainerRegistry::class,
            \ContainerRegistries::Harbor => HarborRegistry::class,
        ];

        foreach ($expected as $provider => $class) {
            $image = $this->imageIn(['provider' => $provider]);

            $this->assertInstanceOf($class, $image->getRegistryClient(), $provider);
        }
    }

    /**
     * There is one way to talk to Podio, so this resolves without asking the image - but
     * it still has to resolve to the real one. Without this, a factory wired to a test
     * double would go unnoticed, since every test that uses Podio installs its own.
     */
    public function testPodioResolvesToTheRealClient(): void {
        $this->assertInstanceOf(PodioApi::class, service('integrations')->podio());
    }

    /**
     * Same reason as Podio: every test that touches Pub/Sub installs a fake, so nothing
     * else would notice if the factory were wired to one permanently.
     */
    public function testPubSubResolvesToTheRealClient(): void {
        $this->assertInstanceOf(PubSubApi::class, service('integrations')->pubSub());
    }

    /**
     * Same again for GitHub: the setup flow's tests all install a fake.
     */
    public function testGithubResolvesToTheRealClient(): void {
        $this->assertInstanceOf(GithubApi::class, service('integrations')->github());
    }

    /**
     * The only commit identification method there is, and the factory has to pick it - the
     * class it resolves to is what runs a job inside the customer's container to read an
     * environment variable back out.
     */
    public function testTheEnvironmentVariableMethodResolvesToItsImplementation(): void {
        $image = Fixtures::containerImage([
            'commit_identification_method' => \CommitIdentificationMethods::EnvironmentVariable,
        ]);

        $this->assertInstanceOf(
            \App\Libraries\CommitIdentificationMethods\EnvironmentVariableCommitIdentification::class,
            $image->getCommitIdentification()
        );
    }

    public function testGithubResolvesToTheGithubImplementation(): void {
        $image = Fixtures::containerImage(['version_control_provider' => \VersionControlProviders::GitHub]);

        $this->assertInstanceOf(GithubVersionControl::class, $image->getVersionControlSystem());
    }

    /**
     * An image with nothing configured resolves to null on all three - an image pulled from
     * a public registry has no connection at all.
     */
    public function testAnImageWithoutIntegrationsResolvesToNothing(): void {
        $image = Fixtures::containerImage([
            'version_control_provider' => '',
            'commit_identification_method' => '',
        ]);

        $this->assertNull($image->getRegistryClient());
        $this->assertNull($image->getVersionControlSystem());
        $this->assertNull($image->getCommitIdentification());
    }

    /**
     * A provider that is not one of the three is treated as none at all, rather than
     * throwing. That matters because the column is free text.
     */
    public function testAnUnknownProviderIsTreatedAsNone(): void {
        $image = $this->imageIn(['provider' => 'some-registry-we-do-not-support']);

        $this->assertNull($image->getRegistryClient());
    }

    /**
     * A connection is soft-deleted, so an image could in principle point at one that is
     * gone. The model refuses to delete one that is in use; this is the fallback.
     */
    public function testAnImageWhoseConnectionIsGoneHasNoRegistry(): void {
        $image = $this->imageIn();
        $this->db->table('container_registries')->where('id', $image->container_registry_id)->delete();

        $this->assertNull($image->getRegistryClient());
    }

    /**
     * Asking twice is the same registry twice. `find()` on a relation that is already
     * loaded is a query without the join, so the second ask got whichever connection came
     * first in the table - found by FEAT-1, whose endpoint asks once to check and once for
     * the tags.
     */
    public function testAskingTwiceGivesTheSameRegistry(): void {
        Fixtures::containerRegistry(['provider' => \ContainerRegistries::ArtifactContainerRegistry]);
        $image = $this->imageIn(['provider' => \ContainerRegistries::Harbor]);

        $image->getRegistryClient();

        $this->assertInstanceOf(HarborRegistry::class, $image->getRegistryClient());
    }

    /**
     * The same, for a connection that is gone: the second ask must not find another one.
     */
    public function testAskingTwiceAfterTheRegistryIsGoneStillGivesNone(): void {
        Fixtures::containerRegistry(['provider' => \ContainerRegistries::ArtifactContainerRegistry]);
        $image = $this->imageIn();
        $this->db->table('container_registries')->where('id', $image->container_registry_id)->delete();

        $image->getRegistryClient();

        $this->assertNull($image->getRegistryClient());
        $this->assertSame([], $image->getPullSecretNames());
    }

    // </editor-fold>

    // <editor-fold desc="With the outside systems faked">

    public function testTagsComeFromTheRegistry(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = ['1.0.0', '1.1.0', 'latest'];
        $image = $this->imageIn();

        $this->assertSame(['1.0.0', '1.1.0', 'latest'], $image->getTags());
        $this->assertSame('team/app', $image->getRegistryClient()->getRepoName($image->url));
    }

    /**
     * No registry, no tags. This used to end in a call on null - an image from a public
     * registry has no connection, so it is an ordinary case, not an error.
     */
    public function testTagsOnAnImageWithoutARegistryAreEmpty(): void {
        $this->assertSame([], Fixtures::containerImage()->getTags());
    }

    /**
     * The fake is asked, not the real registry - which is the whole point, since the real
     * one would open a connection from inside a test.
     */
    public function testTheFakeIsWhatGetsAsked(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = [];
        $image = $this->imageIn(['name' => 'the-registry']);

        $image->getTags();

        $this->assertSame(['the-registry'], $fakes->registryLookups);
    }

    // </editor-fold>

    // <editor-fold desc="Subscribing to registry pushes">

    /**
     * Turning on events is what makes an Artifact Registry tell kso about new tags, and it
     * happens while the connection is being saved - so saving one through the API reaches
     * Google Cloud. Nothing else creates the subscription the cron job later reads.
     */
    public function testEnablingEventsCreatesTheTopicAndSubscription(): void {
        $fakes = FakeIntegrations::install();
        $fakes->realRegistryClients = true;

        $this->saveWithEvents();

        $this->assertSame(
            [['project' => 'the-project', 'topic' => GcrSubscription::TOPIC]],
            $fakes->pubSub()->topicsEnsured
        );
        $this->assertSame(
            [[
                'project' => 'the-project',
                'topic' => GcrSubscription::TOPIC,
                'subscription' => GcrSubscription::name(),
            ]],
            $fakes->pubSub()->subscriptionsEnsured
        );
    }

    /**
     * The name it subscribes under has to be the one the cron job pulls from. They are
     * built in two different files; this is the pair that has to agree.
     */
    public function testItSubscribesUnderTheNameTheCronJobReads(): void {
        $fakes = FakeIntegrations::install();
        $fakes->realRegistryClients = true;

        $this->saveWithEvents();

        $this->assertSame(
            GcrSubscription::name(),
            $fakes->pubSub()->subscriptionsEnsured[0]['subscription']
        );
    }

    /**
     * Only Artifact Registry publishes to Pub/Sub. Harbor and Azure call a webhook
     * instead, so subscribing would be meaningless - and would reach Google with an
     * Azure project name.
     */
    public function testOnlyArtifactRegistrySubscribes(): void {
        $fakes = FakeIntegrations::install();
        $fakes->realRegistryClients = true;

        $this->saveWithEvents(['provider' => \ContainerRegistries::Harbor]);

        $this->assertSame([], $fakes->pubSub()->topicsEnsured);
    }

    public function testAConnectionWithoutEventsTouchesNothing(): void {
        $fakes = FakeIntegrations::install();
        $fakes->realRegistryClients = true;

        $this->saveWithEvents(['events_enabled' => false]);

        $this->assertSame([], $fakes->pubSub()->topicsEnsured);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function saveWithEvents(array $overrides = []): void {
        $registry = Fixtures::containerRegistry(array_merge([
            'provider' => \ContainerRegistries::ArtifactContainerRegistry,
            'gcloud_project' => 'the-project',
            'gcloud_credentials' => '{}',
        ], $overrides));

        // postSave() runs on the REST path, not on a plain save, and it only looks when the
        // request actually carried the flag.
        \App\Entities\ContainerRegistry::patch($registry->id, [
            'events_enabled' => $overrides['events_enabled'] ?? true,
        ]);
    }

    /**
     * @param array<string, mixed> $registry
     */
    private function imageIn(array $registry = []): \App\Entities\ContainerImage {
        return Fixtures::containerImage([
            'container_registry_id' => Fixtures::containerRegistry($registry)->id,
        ]);
    }

    // </editor-fold>

}
