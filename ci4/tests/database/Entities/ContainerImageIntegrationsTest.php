<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Fixtures;
use App\Libraries\ContainerRegistries\AzureContainerRegistry;
use App\Libraries\ContainerRegistries\GoogleCloudArtifactRegistry;
use App\Libraries\ContainerRegistries\HarborRegistry;
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
            $image = Fixtures::containerImage(['registry_provider' => $provider]);

            $this->assertInstanceOf($class, $image->getContainerRegistry(), $provider);
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
     * An image with nothing configured resolves to null on all three. Every caller has to
     * handle that, and several do not - see `getTags()` below.
     */
    public function testAnImageWithoutIntegrationsResolvesToNothing(): void {
        $image = Fixtures::containerImage([
            'registry_provider' => '',
            'version_control_provider' => '',
            'commit_identification_method' => '',
        ]);

        $this->assertNull($image->getContainerRegistry());
        $this->assertNull($image->getVersionControlSystem());
        $this->assertNull($image->getCommitIdentification());
    }

    /**
     * A provider that is not one of the three is treated as none at all, rather than
     * throwing. That matters because the column is free text.
     */
    public function testAnUnknownProviderIsTreatedAsNone(): void {
        $image = Fixtures::containerImage(['registry_provider' => 'some-registry-we-do-not-support']);

        $this->assertNull($image->getContainerRegistry());
    }

    // </editor-fold>

    // <editor-fold desc="With the outside systems faked">

    public function testTagsComeFromTheRegistry(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = ['1.0.0', '1.1.0', 'latest'];
        $image = Fixtures::containerImage(['registry_provider' => \ContainerRegistries::Harbor]);

        $this->assertSame(['1.0.0', '1.1.0', 'latest'], $image->getTags());
        $this->assertSame('team/app', $image->getRegistryRepoName());
    }

    /**
     * Today's behaviour, and the reason the null case above is worth stating: `getTags()`
     * calls straight through without checking, so an image with no registry configured
     * fails with a null call rather than an empty list or a message. FEAT-1 puts this
     * behind a button, where the error is what the user will see.
     */
    public function testTagsOnAnImageWithoutARegistryFailOnNull(): void {
        $image = Fixtures::containerImage(['registry_provider' => '']);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('on null');

        $image->getTags();
    }

    /**
     * The fake is asked, not the real registry - which is the whole point, since the real
     * one would open a connection from inside a test.
     */
    public function testTheFakeIsWhatGetsAsked(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = [];
        $image = Fixtures::containerImage(['name' => 'the-image', 'registry_provider' => \ContainerRegistries::Harbor]);

        $image->getTags();

        $this->assertSame(['the-image'], $fakes->registryLookups);
    }

    // </editor-fold>

    // <editor-fold desc="Subscribing to registry pushes">

    /**
     * Turning on `registry_subscribe` is what makes a registry tell kso about new tags, and
     * it happens while the image is being saved - so saving a container image through the
     * API reaches Google Cloud. Worth knowing, and worth having under test: nothing else
     * creates the subscription the cron job later reads.
     */
    public function testSubscribingCreatesTheTopicAndSubscription(): void {
        $fakes = FakeIntegrations::install();

        $this->saveSubscribingImage();

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

        $this->saveSubscribingImage();

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

        $this->saveSubscribingImage(['registry_provider' => \ContainerRegistries::Harbor]);

        $this->assertSame([], $fakes->pubSub()->topicsEnsured);
    }

    public function testAnImageThatIsNotSubscribingTouchesNothing(): void {
        $fakes = FakeIntegrations::install();

        $this->saveSubscribingImage(['registry_subscribe' => false]);

        $this->assertSame([], $fakes->pubSub()->topicsEnsured);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function saveSubscribingImage(array $overrides = []): void {
        $image = Fixtures::containerImage(array_merge([
            'registry_provider' => \ContainerRegistries::ArtifactContainerRegistry,
            'registry_provider_gcloud_project' => 'the-project',
            'registry_provider_gcloud_credentials' => '{}',
            'registry_subscribe' => true,
        ], $overrides));

        // postSave() runs on the REST path, not on a plain save, and it only looks when the
        // request actually carried the flag.
        \App\Entities\ContainerImage::patch($image->id, [
            'registry_subscribe' => $overrides['registry_subscribe'] ?? true,
        ]);
    }

    // </editor-fold>

}
