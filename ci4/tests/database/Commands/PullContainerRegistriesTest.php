<?php namespace App\Tests\Database\Commands;

use App\Commands\PullContainerRegistries;
use App\DatabaseTestCase;
use App\Entities\AutoUpdate;
use App\Fixtures;
use App\Libraries\GoogleCloud\GcrSubscription;
use App\Models\AutoUpdateModel;
use App\Tests\Fakes\FakeIntegrations;

/**
 * The cron job that turns a registry push into a pending update.
 *
 * A Google Artifact Registry publishes to a Pub/Sub topic when a tag is pushed; this reads
 * the queue and hands each new tag to the auto-update machinery. It is the path by which a
 * customer's workspace learns that a new version exists, and until Pub/Sub had an interface
 * none of it could be looked at.
 *
 * `run()` is tested only as far as the point where it decides there is something to pull:
 * from there it sleeps two seconds five times over, which a test suite cannot wait for.
 * `runAcrProjects()` - the part that decides anything - is tested directly instead.
 */
class PullContainerRegistriesTest extends DatabaseTestCase {

    public function tearDown(): void {
        FakeIntegrations::uninstall();

        parent::tearDown();
    }

    public function testAPushedTagBecomesAPendingUpdate(): void {
        $fakes = FakeIntegrations::install();
        $deployment = $this->autoUpdatingDeployment();
        $fakes->pubSub()->messages['the-project'] = [
            $this->push('registry.example.org/team/api:v2.0.0'),
        ];

        $created = $this->pull(['the-project' => 'the-key']);

        $this->assertTrue($created);
        $update = $this->updatesFor($deployment->id)[0];
        $this->assertSame('registry.example.org/team/api', $update->image);
        $this->assertSame('v2.0.0', $update->next_tag);
    }

    /**
     * A deleted tag is not a new version. Ignoring it is the difference between a tidy-up
     * in the registry and a workspace being offered a rollback.
     */
    public function testADeletedTagIsIgnored(): void {
        $fakes = FakeIntegrations::install();
        $deployment = $this->autoUpdatingDeployment();
        $fakes->pubSub()->messages['the-project'] = [
            $this->message('DELETE', 'registry.example.org/team/api:v2.0.0'),
        ];

        $created = $this->pull(['the-project' => 'the-key']);

        $this->assertFalse($created);
        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    /**
     * The image and the tag arrive as one string and are split on the colon. A registry
     * host carrying a port would split in the wrong place - see the note below.
     */
    public function testTheImageAndTagAreSplitOnTheColon(): void {
        $fakes = FakeIntegrations::install();
        $deployment = $this->autoUpdatingDeployment(['image' => 'registry.example.org/team/api']);
        $fakes->pubSub()->messages['the-project'] = [
            $this->push('registry.example.org/team/api:v2.0.0'),
        ];

        $this->pull(['the-project' => 'the-key']);

        $this->assertSame('v2.0.0', $this->updatesFor($deployment->id)[0]->next_tag);
    }

    /**
     * Today's behaviour, and a trap the webhooks avoid. The webhook splits on the **last**
     * colon; this splits on the first, so a registry that publishes a host with a port
     * gives an image of `registry.example.org` and a tag of `5000/team/api`. Nothing
     * matches, and no update is created - silently. See FEAT-12.
     */
    public function testARegistryHostWithAPortIsSplitInTheWrongPlace(): void {
        $fakes = FakeIntegrations::install();
        $deployment = $this->autoUpdatingDeployment(['image' => 'registry.example.org:5000/team/api']);
        $fakes->pubSub()->messages['the-project'] = [
            $this->push('registry.example.org:5000/team/api:v2.0.0'),
        ];

        $this->pull(['the-project' => 'the-key']);

        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    public function testEveryProjectIsRead(): void {
        $fakes = FakeIntegrations::install();
        $first = $this->autoUpdatingDeployment(['name' => 'first', 'image' => 'registry.example.org/team/first']);
        $second = $this->autoUpdatingDeployment(['name' => 'second', 'image' => 'registry.example.org/team/second']);
        $fakes->pubSub()->messages['project-a'] = [$this->push('registry.example.org/team/first:v2.0.0')];
        $fakes->pubSub()->messages['project-b'] = [$this->push('registry.example.org/team/second:v3.0.0')];

        $this->pull(['project-a' => 'key-a', 'project-b' => 'key-b']);

        $this->assertSame('v2.0.0', $this->updatesFor($first->id)[0]->next_tag);
        $this->assertSame('v3.0.0', $this->updatesFor($second->id)[0]->next_tag);
    }

    /**
     * Nothing waiting is the ordinary case - this runs every minute - and it must not
     * report that it created anything, or the run ends by announcing an update over the
     * push socket for no reason.
     */
    public function testAnEmptyQueueCreatesNothing(): void {
        FakeIntegrations::install();

        $this->assertFalse($this->pull(['the-project' => 'the-key']));
    }

    /**
     * The subscription is read under the same name it is created under. They are built in
     * two different files, and if they ever disagree kso subscribes to one queue and reads
     * an empty one - with no error anywhere.
     */
    public function testTheQueueIsReadUnderTheAgreedName(): void {
        $fakes = FakeIntegrations::install();

        $this->pull(['the-project' => 'the-key']);

        $this->assertSame(
            [['project' => 'the-project', 'topic' => GcrSubscription::TOPIC, 'subscription' => GcrSubscription::name()]],
            $fakes->pubSub()->pulls
        );
    }

    /**
     * The cron page shows when each job last ran, and a job that never records it looks
     * stuck to whoever is looking - while it is in fact running every minute.
     */
    public function testTheJobRecordsWhenItLastRan(): void {
        $before = $this->cronJob()['last_run'];

        $this->runTheJob();

        $this->assertNotSame($before, $this->cronJob()['last_run']);
    }

    /**
     * What the run decided is only visible afterwards through the job's own log, which is
     * the only account of a job that otherwise leaves no trace when there is nothing to do.
     */
    public function testTheJobRecordsWhatItSaw(): void {
        $this->assertStringContainsString(
            'Check for container registry events',
            $this->runTheJob()
        );
    }

    /**
     * Subscribing is opt-in per image. An image that was never subscribed has no Pub/Sub
     * topic behind it, so reading it would fail - and the customer never asked for it.
     */
    public function testOnlyImagesWithRegistrySubscribeAreLookedAt(): void {
        Fixtures::containerImage([
            'name' => 'subscribed',
            'registry_subscribe' => true,
            'registry_provider' => \ContainerRegistries::Harbor,
        ]);
        // Two of them, so that reading the flag backwards gives a different count rather
        // than the same one.
        Fixtures::containerImage([
            'name' => 'unsubscribed',
            'registry_subscribe' => false,
            'registry_provider' => \ContainerRegistries::Harbor,
        ]);
        Fixtures::containerImage([
            'name' => 'also-unsubscribed',
            'registry_subscribe' => false,
            'registry_provider' => \ContainerRegistries::Harbor,
        ]);

        $log = $this->runTheJob();

        $this->assertStringContainsString(
            'found 1 container images with registry subscribe enabled',
            $log
        );
    }

    /**
     * Only Google's Artifact Registry publishes to a Pub/Sub topic. Harbor and Azure are
     * subscribed to by other means entirely, and pulling a Google queue for them would ask
     * Google for a project that has nothing to do with the image.
     */
    public function testOnlyArtifactRegistryImagesContributeAProject(): void {
        Fixtures::containerImage([
            'name' => 'on-harbor',
            'registry_subscribe' => true,
            'registry_provider' => \ContainerRegistries::Harbor,
            'registry_provider_gcloud_project' => 'not-a-google-project',
        ]);
        Fixtures::containerImage([
            'name' => 'on-azure',
            'registry_subscribe' => true,
            'registry_provider' => \ContainerRegistries::AzureContainerRegistry,
            'registry_provider_gcloud_project' => 'also-not-a-google-project',
        ]);

        $log = $this->runTheJob();

        $this->assertStringContainsString(
            'found 2 container images with registry subscribe enabled',
            $log
        );
        $this->assertStringNotContainsString('ACR projects', $log);
    }

    /**
     * Nothing subscribed at all is a fresh installation, and it must still complete and
     * write its bookkeeping rather than fall over on an empty result.
     */
    public function testNothingSubscribedIsNotAnError(): void {
        $log = $this->runTheJob();

        $this->assertStringContainsString(
            'found 0 container images with registry subscribe enabled',
            $log
        );
        $this->assertStringNotContainsString('ACR projects', $log);
    }

    /**
     * The whole of `run()` on the path that matters: a subscribed Artifact Registry image
     * is found, its project is pulled, and the job writes what it saw.
     *
     * `run()` pulls five times two seconds apart. The suite makes `sleep()` instant inside
     * `App\Commands` - see `tests/_fakes/NoSleepInCommands.php` - so the five pulls are
     * visible here rather than being ten seconds nobody can afford to wait for.
     */
    public function testAnArtifactRegistryProjectIsPulledFiveTimes(): void {
        $fakes = FakeIntegrations::install();
        Fixtures::containerImage([
            'registry_provider' => \ContainerRegistries::ArtifactContainerRegistry,
            'registry_provider_gcloud_project' => 'the-project',
            'registry_provider_gcloud_credentials' => '{}',
            'registry_subscribe' => true,
        ]);

        $log = $this->runTheJob();

        $this->assertStringContainsString('found 1 ACR projects', $log);
        $this->assertCount(5, $fakes->pubSub()->pulls, 'five pulls, two seconds apart in production');
    }

    /**
     * A pull that fails - an expired service account key is the ordinary way - is swallowed
     * into the job's log rather than allowed to end the run, so the cron job still records
     * that it ran and what went wrong.
     */
    public function testAFailingPullIsWrittenToTheLogRatherThanEndingTheRun(): void {
        $fakes = FakeIntegrations::install();
        $fakes->pubSub()->failPullWith = new \Exception('the key has expired');
        Fixtures::containerImage([
            'registry_provider' => \ContainerRegistries::ArtifactContainerRegistry,
            'registry_provider_gcloud_project' => 'the-project',
            'registry_provider_gcloud_credentials' => '{}',
            'registry_subscribe' => true,
        ]);

        $log = $this->runTheJob();

        $this->assertStringContainsString('the key has expired', $log);
        $this->assertNotSame('', (string) $this->cronJob()['last_log'], 'the run still finished');
    }

    // <editor-fold desc="Fixtures">

    /**
     * Runs the command and hands back what it wrote into its own log. The debugger's store
     * is a process-wide static, so it is cleared first - otherwise each run carries every
     * earlier run's lines and an assertion reads the wrong answer.
     */
    private function runTheJob(): string {
        $store = (new \ReflectionClass(\DebugTool\Data::class))->getProperty('store');
        $store->setValue(null, ['status' => null]);

        (new PullContainerRegistries(service('logger'), service('commands')))->run([]);

        return (string) $this->cronJob()['last_log'];
    }

    /**
     * @return array<string, mixed>
     */
    private function cronJob(): array {
        return $this->db->table('cron_jobs')
            ->where('id', \CronJobIds::PullContainerRegistries)
            ->get()->getRowArray();
    }

    /**
     * @param array<string, string> $projects
     */
    private function pull(array $projects): bool {
        $command = new class (service('logger'), service('commands')) extends PullContainerRegistries {
            /** @param array<string, string> $acrProjects */
            public function pullFor(array $acrProjects): bool {
                return $this->runAcrProjects($acrProjects);
            }
        };

        return $command->pullFor($projects);
    }

    private function push(string $imageAndTag): string {
        return $this->message('INSERT', $imageAndTag);
    }

    private function message(string $action, string $imageAndTag): string {
        return json_encode(['action' => $action, 'tag' => $imageAndTag]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function autoUpdatingDeployment(array $overrides = []): \App\Entities\Deployment {
        return Fixtures::autoUpdatableDeployment(array_merge([
            'image' => 'registry.example.org/team/api',
            'version' => 'v1.0.0',
            'auto_update_tag_regex' => 'v[0-9]+\.[0-9]+\.[0-9]+',
            'auto_update_require_approval' => true,
        ], $overrides));
    }

    /**
     * @return AutoUpdate[]
     */
    private function updatesFor(int $deploymentId): array {
        return (new AutoUpdateModel())
            ->where('deployment_id', $deploymentId)
            ->orderBy('id', 'asc')
            ->find()
            ->all ?? [];
    }

    // </editor-fold>

}
