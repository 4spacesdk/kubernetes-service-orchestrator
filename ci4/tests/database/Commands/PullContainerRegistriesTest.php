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
 * `run()` pulls five times two seconds apart. The suite makes `sleep()` instant inside
 * `App\Commands` - see `tests/_fakes/NoSleepInCommands.php` - so the whole run is tested
 * here; `runAcrProjects()`, the part that decides anything, is also reached directly, so a
 * single pull can be examined without the five around it.
 */
class PullContainerRegistriesTest extends DatabaseTestCase {

    /** The command the last `runTheJob()` used, counting its own announcements. */
    private PullContainerRegistries $lastRun;

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
     * A registry reachable on a port puts a colon in the host too. This used to split on the
     * first one, so the image became `registry.example.org` and the tag `5000/team/api`:
     * nothing matched and no update was created, silently. Both halves split the same way
     * now - on the last colon after the last slash.
     */
    public function testARegistryHostWithAPortStillFindsTheTag(): void {
        $fakes = FakeIntegrations::install();
        $deployment = $this->autoUpdatingDeployment(['image' => 'registry.example.org:5000/team/api']);
        $fakes->pubSub()->messages['the-project'] = [
            $this->push('registry.example.org:5000/team/api:v2.0.0'),
        ];

        $this->pull(['the-project' => 'the-key']);

        $this->assertSame('v2.0.0', $this->updatesFor($deployment->id)[0]->next_tag);
    }

    /**
     * A message with no tag in it at all is ignored rather than read as one.
     */
    public function testAMessageWithoutATagIsIgnored(): void {
        $fakes = FakeIntegrations::install();
        $deployment = $this->autoUpdatingDeployment(['image' => 'registry.example.org/team/api']);
        $fakes->pubSub()->messages['the-project'] = [$this->push('registry.example.org/team/api')];

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
     * Events are opt-in per registry connection. One that never enabled them has no Pub/Sub
     * topic behind it, so reading it would fail - and the customer never asked for it.
     */
    public function testOnlyConnectionsWithEventsEnabledAreLookedAt(): void {
        $this->artifactRegistry(['name' => 'with-events', 'events_enabled' => true]);
        // Two of them, so that reading the flag backwards gives a different count rather
        // than the same one.
        $this->artifactRegistry(['name' => 'without-events', 'events_enabled' => false]);
        $this->artifactRegistry(['name' => 'also-without-events', 'events_enabled' => false]);

        $log = $this->runTheJob();

        $this->assertStringContainsString('found 1 artifact registries with events enabled', $log);
    }

    /**
     * Only Google's Artifact Registry publishes to a Pub/Sub topic. Harbor and Azure are
     * told about by webhooks, and pulling a Google queue for them would ask Google for a
     * project that has nothing to do with them.
     */
    public function testOnlyArtifactRegistriesContributeAProject(): void {
        Fixtures::containerRegistry([
            'provider' => \ContainerRegistries::Harbor,
            'gcloud_project' => 'not-a-google-project',
            'events_enabled' => true,
        ]);
        Fixtures::containerRegistry([
            'provider' => \ContainerRegistries::AzureContainerRegistry,
            'gcloud_project' => 'also-not-a-google-project',
            'events_enabled' => true,
        ]);

        $log = $this->runTheJob();

        $this->assertStringContainsString('found 0 artifact registries with events enabled', $log);
        $this->assertStringNotContainsString('ACR projects', $log);
    }

    /**
     * Nothing subscribed at all is a fresh installation, and it must still complete and
     * write its bookkeeping rather than fall over on an empty result.
     */
    public function testNothingSubscribedIsNotAnError(): void {
        $log = $this->runTheJob();

        $this->assertStringContainsString('found 0 artifact registries with events enabled', $log);
        $this->assertStringNotContainsString('ACR projects', $log);
    }

    /**
     * Two connections into the same project share its topic, so the project is pulled once
     * - pulling it twice would split its messages between the two reads for no gain.
     */
    public function testTwoConnectionsInOneProjectArePulledAsOne(): void {
        $fakes = FakeIntegrations::install();
        $this->artifactRegistry(['gcloud_registry_name' => 'one']);
        $this->artifactRegistry(['gcloud_registry_name' => 'two']);

        $log = $this->runTheJob();

        $this->assertStringContainsString('found 1 ACR projects', $log);
        $this->assertCount(5, $fakes->pubSub()->pulls);
    }

    /**
     * The whole of `run()` on the path that matters: an Artifact Registry with events on
     * is found, its project is pulled, and the job writes what it saw.
     *
     * `run()` pulls five times two seconds apart. The suite makes `sleep()` instant inside
     * `App\Commands` - see `tests/_fakes/NoSleepInCommands.php` - so the five pulls are
     * visible here rather than being ten seconds nobody can afford to wait for.
     */
    public function testAnArtifactRegistryProjectIsPulledFiveTimes(): void {
        $fakes = FakeIntegrations::install();
        $this->artifactRegistry();

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
        $this->artifactRegistry();

        $log = $this->runTheJob();

        $this->assertStringContainsString('the key has expired', $log);
        $this->assertNotSame('', (string) $this->cronJob()['last_log'], 'the run still finished');
    }

    /**
     * A `TypeError` is not an `Exception`, and the run used to let it past - skipping
     * `last_log` and the closing `save()`, so the job page kept showing the *previous*
     * run's log with no sign that anything had gone wrong.
     */
    public function testAnErrorInThePullIsAlsoWrittenToTheLog(): void {
        $fakes = FakeIntegrations::install();
        $fakes->pubSub()->failPullWith = new \TypeError('pull() got null');
        $this->artifactRegistry();

        $log = $this->runTheJob();

        $this->assertStringContainsString('pull() got null', $log);
    }

    /**
     * The run pulls five times, and a push usually arrives on one of them. The result used
     * to be assigned rather than collected, so a tag found by the first pull was erased by
     * the four empty ones after it: no event went over the push socket, and the update only
     * appeared once somebody reloaded the page.
     */
    public function testATagFoundByAnEarlyPullIsStillAnnouncedAfterTheEmptyOnes(): void {
        $fakes = FakeIntegrations::install();
        $deployment = $this->autoUpdatingDeployment();
        $this->artifactRegistry();
        $fakes->pubSub()->messages['the-project'] = [
            $this->push('registry.example.org/team/api:v2.0.0'),
        ];

        $this->runTheJob();

        $this->assertCount(1, $this->updatesFor($deployment->id), 'the first pull found it');
        $this->assertSame(1, $this->announcements(), 'and the four empty pulls did not erase it');
    }

    /**
     * Five pulls that find nothing are the ordinary minute, and announcing an update then
     * would have every open UI ask for a list that has not changed.
     */
    public function testAQuietRunAnnouncesNothing(): void {
        FakeIntegrations::install();
        $this->artifactRegistry();

        $this->runTheJob();

        $this->assertSame(0, $this->announcements());
    }

    // <editor-fold desc="Fixtures">

    /**
     * Runs the command and hands back what it wrote into its own log. The debugger's store
     * is a process-wide static, so it is cleared first - otherwise each run carries every
     * earlier run's lines and an assertion reads the wrong answer.
     */
    /**
     * @param array<string, mixed> $overrides
     */
    private function artifactRegistry(array $overrides = []): \App\Entities\ContainerRegistry {
        return Fixtures::containerRegistry(array_merge([
            'provider' => \ContainerRegistries::ArtifactContainerRegistry,
            'gcloud_project' => 'the-project',
            'gcloud_credentials' => '{}',
            'events_enabled' => true,
        ], $overrides));
    }

    private function runTheJob(): string {
        $store = (new \ReflectionClass(\DebugTool\Data::class))->getProperty('store');
        $store->setValue(null, ['status' => null]);

        $this->lastRun = new class (service('logger'), service('commands')) extends PullContainerRegistries {
            public int $announced = 0;

            protected function announceAutoUpdates(): void {
                $this->announced++;
                // Still the real one, over the suite's silenced socket: whether it sends is
                // half of what is being asked here.
                parent::announceAutoUpdates();
            }
        };
        $this->lastRun->run([]);

        return (string) $this->cronJob()['last_log'];
    }

    /** How many times the last run told the open UIs that an update is waiting. */
    private function announcements(): int {
        return $this->lastRun->announced;
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
