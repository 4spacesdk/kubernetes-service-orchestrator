<?php namespace App\Tests\Database\Push;

use App\DatabaseTestCase;
use App\Entities\AutoUpdate;
use App\Entities\Deployment;
use App\Entities\Webhook;
use App\Fixtures;
use App\Jobs\HandleEvent;
use App\Libraries\Push\ChangeEvent;
use App\Libraries\Push\EventHandlers;
use App\Libraries\Push\Events;
use App\Libraries\Push\Publisher;
use DebugTool\Data;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The events kso acts on itself, from the moment one is raised to the moment it is handled.
 *
 * The first part goes the whole way: `Publisher::send()` puts the event on the queue, the
 * worker's side takes it off again, and `HandleEvent` runs it.
 *
 * The rest pins **which decision each handler makes**: seven of them pick a webhook type, one
 * looks at a deployment after its migration job, and the last rolls an update out. The event
 * name and the type it delivers is the only thing telling the seven apart, and a swap between
 * two of them is not something anyone notices.
 */
class EventHandlersTest extends DatabaseTestCase {

    public function setUp(): void {
        parent::setUp();

        // The debug store is a static that lives for the whole process, so without this an
        // assertion about what *this* handler wrote reads the previous test's lines.
        $this->forgetTheDebugStore();
    }

    // <editor-fold desc="Through the queue">

    public function testAnEventKsoActsOnGoesThroughTheQueueToItsHandler(): void {
        $this->aWebhookForEveryType();

        $this->publisher()->send(Events::Workspace_Created(), (new ChangeEvent(null, ['id' => 7]))->toArray());
        $this->assertSame([], $this->deliveries(), 'handled when raised instead of by the worker');

        $this->runTheQueue();

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries);
        $this->assertSame(\WebHookTypes::Workspace_Created, $deliveries[0]['webhook_type']);
        $this->assertSame(0, $this->db->table('queue_jobs')->countAllResults(), 'the job is still there after it ran');
    }

    /**
     * Every event in the map has a handler that can be called. A method name that does not
     * exist would be an Error in the worker - for exactly the events nobody tests by hand.
     */
    #[DataProvider('everyEventKsoActsOn')]
    public function testEveryEventInTheMapReachesAHandler(string $event): void {
        EventHandlers::Handle($event, new ChangeEvent(null, ['id' => 0, 'status' => '']));

        $this->assertStringNotContainsString('No handler for', $this->debugLog());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function everyEventKsoActsOn(): array {
        $events = [
            Events::MigrationJob_Changed_Status(0),
            Events::Workspace_Created(),
            Events::Workspace_Updated(),
            Events::Workspace_Deleted(),
            Events::Workspace_Deployed(),
            Events::Workspace_Terminated(),
            Events::Deployment_Deployed(),
            Events::Deployment_Terminated(),
            Events::AutoUpdate_Approved(),
        ];
        return array_combine($events, array_map(fn ($event) => [$event], $events));
    }

    /**
     * Only pushed to the browsers. A status change happens on every step of a deploy, and
     * nothing on the server acts on it.
     */
    public function testAnEventNothingActsOnIsNotQueued(): void {
        $this->publisher()->send(Events::Workspace_Changed_Status(7), (new ChangeEvent(null, ['id' => 7]))->toArray());

        $this->assertSame(0, $this->db->table('queue_jobs')->countAllResults());
    }

    /**
     * The job's pod is still shutting down when it reports that it is done, so the deployment
     * is looked at a few seconds later rather than straight away.
     */
    public function testAFinishedMigrationJobIsHandledAfterADelay(): void {
        $this->publisher()->send(Events::MigrationJob_Changed_Status(0), (new ChangeEvent(null, ['status' => 'x']))->toArray());

        $job = $this->db->table('queue_jobs')->get()->getRowArray();
        $this->assertGreaterThan(time(), (int) $job['available_at']);
    }

    /**
     * The id-scoped channel is for the browser watching that one job; only the shared one is
     * acted on, or every job would be handled twice.
     */
    public function testTheMigrationJobsOwnChannelIsNotQueued(): void {
        $this->publisher()->send(Events::MigrationJob_Changed_Status(12), (new ChangeEvent(null, ['status' => 'x']))->toArray());

        $this->assertSame(0, $this->db->table('queue_jobs')->countAllResults());
    }

    // </editor-fold>

    // <editor-fold desc="The seven handlers that deliver a webhook">

    /**
     * Every handler delivers its own webhook type and no other.
     *
     * There is a webhook for **every** type when this runs, so a handler that reached for
     * the wrong constant would deliver to a different webhook - not to none at all. That is
     * the only way a swap becomes visible: the methods are otherwise identical down to the
     * character.
     *
     * The delivery itself goes over curl to an empty url, which fails without touching a
     * network. The row in `webhook_deliveries` is written before that call and stays, and
     * it is the imprint this reads.
     */
    #[DataProvider('theHandlersThatDeliverAWebhook')]
    public function testAHandlerDeliversItsOwnWebhookTypeAndNobodyElses(string $event, string $type): void {
        $this->aWebhookForEveryType();

        $this->handle($event, ['id' => 7, 'name' => 'the-workspace']);

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries, 'something other than one webhook was delivered to');

        $payload = json_decode($deliveries[0]['payload'], true);
        $this->assertSame($type, $payload['event']);
        $this->assertSame($type, $deliveries[0]['webhook_type'], 'the delivery went to a webhook of another type');
    }

    /**
     * What the webhook is told is `next` - the state after the change - and not the
     * whole event envelope. A webhook carrying `previous` would tell a foreign recipient
     * what the fields used to be, and that is not what was agreed.
     */
    public function testTheWebhookCarriesTheNewStateAndNotThePreviousOne(): void {
        $this->aWebhookForEveryType();

        $this->handle(
            Events::Workspace_Updated(),
            ['id' => 7, 'name' => 'after'],
            ['id' => 7, 'name' => 'before']
        );

        $delivery = $this->deliveries()[0];
        $payload = json_decode($delivery['payload'], true);

        $this->assertSame(['id' => 7, 'name' => 'after'], json_decode($payload['payload'], true));
        $this->assertStringNotContainsString('before', $delivery['payload']);
    }

    /**
     * No subscribers is not an error. It is the ordinary state of an installation that does
     * not use webhooks, and every single change in kso goes through this line.
     */
    public function testAHandlerWithNobodyListeningIsQuietAndSaysSo(): void {
        $this->handle(Events::Workspace_Created(), ['id' => 7]);

        $this->assertSame([], $this->deliveries());
        $this->assertStringContainsString('delivered workspace-created to 0 webhooks', $this->debugLog());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function theHandlersThatDeliverAWebhook(): array {
        return [
            'workspaceCreated' => [Events::Workspace_Created(), \WebHookTypes::Workspace_Created],
            'workspaceUpdated' => [Events::Workspace_Updated(), \WebHookTypes::Workspace_Updated],
            'workspaceDeleted' => [Events::Workspace_Deleted(), \WebHookTypes::Workspace_Deleted],
            'workspaceDeployed' => [Events::Workspace_Deployed(), \WebHookTypes::Workspace_Deployed],
            'workspaceTerminated' => [Events::Workspace_Terminated(), \WebHookTypes::Workspace_Terminated],
            'deploymentDeployed' => [Events::Deployment_Deployed(), \WebHookTypes::Deployment_Deployed],
            'deploymentTerminated' => [Events::Deployment_Terminated(), \WebHookTypes::Deployment_Terminated],
        ];
    }

    // </editor-fold>

    // <editor-fold desc="The migration job that changed status">

    /**
     * A migration job that has come to an end - one way or the other - is what makes the
     * deployment look at itself again. There are no steps here that can be validated (the
     * deployment has no workspace), so `checkStatus()` puts it in Draft, and the move from
     * Active to Draft is what shows the call happened.
     */
    #[DataProvider('theStatusesThatEndAMigrationJob')]
    public function testAFinishedMigrationJobMakesTheDeploymentLookAtItself(string $status): void {
        $deployment = Fixtures::deployment(['status' => \DeploymentStatusTypes::Active]);

        $this->handle(Events::MigrationJob_Changed_Status(0), [
            'status' => $status,
            'deployment_id' => $deployment->id,
        ]);

        $this->assertSame(\DeploymentStatusTypes::Draft, $this->statusOf($deployment));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function theStatusesThatEndAMigrationJob(): array {
        return [
            'completed' => [\MigrationJobStatusTypes::Completed],
            'failed log verification' => [\MigrationJobStatusTypes::Failed_LogVerification],
            'failed post commands' => [\MigrationJobStatusTypes::Failed_PostCommands],
        ];
    }

    /**
     * A job still under way does not touch the deployment. That is the other half of the
     * switch, and without it every status line from a running job would cost a status check
     * - which asks the cluster - for every step the job took.
     */
    #[DataProvider('theStatusesThatMeanAJobIsStillRunning')]
    public function testAMigrationJobThatIsStillRunningIsLeftAlone(string $status): void {
        $deployment = Fixtures::deployment(['status' => \DeploymentStatusTypes::Active]);

        $this->handle(Events::MigrationJob_Changed_Status(0), [
            'status' => $status,
            'deployment_id' => $deployment->id,
        ]);

        $this->assertSame(\DeploymentStatusTypes::Active, $this->statusOf($deployment));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function theStatusesThatMeanAJobIsStillRunning(): array {
        return [
            'deploying' => [\MigrationJobStatusTypes::Deploying],
            'started' => [\MigrationJobStatusTypes::Started],
        ];
    }

    // </editor-fold>

    // <editor-fold desc="The approved automatic update">

    /**
     * An approved auto update is rolled out, and it is rolled out for the `id` the event
     * names - not the latest one and not the first one.
     *
     * The imprint is the new version on the deployment: `rollout()` calls
     * `updateVersion($this->next_tag)`, so a handler that fetched the wrong row would put
     * the wrong tag on the wrong deployment. That is the whole decision in the method.
     */
    public function testTheApprovedAutoUpdateThatTheEventNamesIsTheOneRolledOut(): void {
        $other = $this->anApprovedAutoUpdate('other/image', '9.9.9');
        $named = $this->anApprovedAutoUpdate('named/image', '2.0.0');

        $this->handle(Events::AutoUpdate_Approved(), ['id' => $named->id]);

        $this->assertSame('2.0.0', $this->versionOf($named->deployment_id));
        $this->assertSame('old', $this->versionOf($other->deployment_id), 'the other auto update was rolled out');
        $this->assertStringContainsString('rollout named/image 2.0.0', $this->debugLog());
    }

    /**
     * A switched-off workspace has its deployments left alone. The guard was there all
     * along and did nothing: `$deployment->find($id)` loads the deployment's own row, and
     * `->workspace` was then an empty entity whose status is null, which is never Inactive.
     * The workspace is looked up by id now.
     */
    public function testAnInactiveWorkspaceStopsTheRollout(): void {
        $autoUpdate = $this->anApprovedAutoUpdate('image', '2.0.0', [
            'workspace_status' => \WorkspaceStatusTypes::Inactive,
        ]);

        $this->handle(Events::AutoUpdate_Approved(), ['id' => $autoUpdate->id]);

        $this->assertStringContainsString('Skip rollout because the workspace is paused or inactive', $this->debugLog());
        $this->assertSame('old', $this->versionOf($autoUpdate->deployment_id));
    }

    /**
     * And a paused one, which is the point of the flag: the status is recomputed from the
     * deployments and can be anything, so `Inactive` alone is not enough to go by.
     */
    public function testAPausedWorkspaceStopsTheRollout(): void {
        $autoUpdate = $this->anApprovedAutoUpdate('image', '2.0.0', [
            'workspace_status' => \WorkspaceStatusTypes::Active,
            'workspace_paused' => true,
        ]);

        $this->handle(Events::AutoUpdate_Approved(), ['id' => $autoUpdate->id]);

        $this->assertSame('old', $this->versionOf($autoUpdate->deployment_id));
    }

    /**
     * An update whose deployment has been removed rolls out nothing - and, above all, does
     * not create one.
     *
     * `updateVersion()` sets the version and saves, and saving an entity that was never
     * loaded **inserts a row**: a rollout on a deleted deployment used to write a nameless
     * deployment in no workspace and then ask Kubernetes to deploy it. The same shape as a
     * write against an id that was not found elsewhere in kso.
     */
    public function testAnUpdateWhoseDeploymentIsGoneRollsOutNothingAndCreatesNothing(): void {
        $autoUpdate = $this->anApprovedAutoUpdate('image', '2.0.0');
        $before = $this->db->table('deployments')->countAllResults();
        $this->db->table('deployments')->where('id', $autoUpdate->deployment_id)->delete();

        $this->handle(Events::AutoUpdate_Approved(), ['id' => $autoUpdate->id]);

        $this->assertSame($before - 1, $this->db->table('deployments')->countAllResults(), 'a deployment was created');
        $this->assertStringContainsString('Skip rollout because the deployment is gone', $this->debugLog());
    }

    /**
     * An update carrying no deployment at all rolls out onto nobody.
     *
     * `find(null)` does not answer "not found" - it loads the whole table and reads as
     * existing, from the first row. So the `exists()` check alone would have rolled this
     * update's tag out onto whichever deployment happened to be first.
     */
    public function testAnUpdateWithNoDeploymentOnItDoesNotRollOutOntoSomebodyElses(): void {
        $bystander = Fixtures::autoUpdatableDeployment();
        $autoUpdate = new AutoUpdate();
        $autoUpdate->image = 'image';
        $autoUpdate->next_tag = '2.0.0';
        $autoUpdate->previous_tag = 'old';
        $autoUpdate->is_approved = true;
        $autoUpdate->save();

        $this->handle(Events::AutoUpdate_Approved(), ['id' => $autoUpdate->id]);

        $this->assertSame('old', $this->versionOf($bystander->id));
    }

    /**
     * The event is handled out of band, so the update named by it may have been rolled out
     * by hand or deleted with its deployment before this arrives. That is a line in the log,
     * not the end of the run: it used to reach `rollout()` on an empty entity, where
     * `updateVersion(null)` is a `TypeError`.
     */
    public function testAnEventNamingAnUpdateThatIsGoneIsALineInTheLog(): void {
        $this->handle(Events::AutoUpdate_Approved(), ['id' => 424242]);

        $this->assertStringContainsString('No auto update with id', $this->debugLog());
    }

    /**
     * And an event with no id on it at all, which is the same question one step earlier.
     */
    public function testAnEventWithNoIdOnItRollsOutNothing(): void {
        $this->handle(Events::AutoUpdate_Approved(), []);

        $this->assertStringContainsString('No auto update with id', $this->debugLog());
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * Hand a handler an event, the way `HandleEvent` does.
     *
     * @param array<string, mixed> $next
     * @param array<string, mixed>|null $previous
     */
    private function handle(string $event, array $next, ?array $previous = null): void {
        EventHandlers::Handle($event, new ChangeEvent($previous, $next));
    }

    private function publisher(): Publisher {
        return new Publisher(null, true);
    }

    /**
     * What `spark queue:work events` does, one job at a time, until there are none it may
     * take yet.
     */
    private function runTheQueue(): void {
        $queue = service('queue');
        while ($work = $queue->pop(EventHandlers::Queue, ['default'])) {
            (new HandleEvent($work->payload['data']))->process();
            $queue->done($work);
        }
    }

    /**
     * A subscriber on every single webhook type, all of them with an empty url.
     *
     * The empty url is on purpose: `curl_exec()` refuses it before anything is opened, so
     * the delivery fails cleanly and without leaving the process. The row in
     * `webhook_deliveries` is written before that and stays.
     */
    private function aWebhookForEveryType(): void {
        foreach (\WebHookTypes::All() as $type) {
            $webhook = new Webhook();
            $webhook->type = $type;
            $webhook->name = $type;
            $webhook->url = '';
            $webhook->http_method = 'post';
            $webhook->content_type = 'application/json';
            $webhook->auth_bearer_token = '';
            $webhook->save();
        }
    }

    /**
     * The deliveries, with the type of the webhook each one went to.
     *
     * @return array<array<string, mixed>>
     */
    private function deliveries(): array {
        return $this->db->table('webhook_deliveries d')
            ->select('d.payload, w.type as webhook_type')
            ->join('webhooks w', 'w.id = d.webhook_id')
            ->orderBy('d.id', 'asc')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string, mixed> $workspace What the deployment's workspace looks like.
     */
    private function anApprovedAutoUpdate(string $image, string $tag, array $workspace = []): AutoUpdate {
        $deployment = Fixtures::autoUpdatableDeployment($workspace);

        $autoUpdate = new AutoUpdate();
        $autoUpdate->deployment_id = $deployment->id;
        $autoUpdate->image = $image;
        $autoUpdate->next_tag = $tag;
        $autoUpdate->previous_tag = 'old';
        $autoUpdate->is_approved = true;
        $autoUpdate->save();

        return $autoUpdate;
    }

    private function statusOf(Deployment $deployment): string {
        return (string) $this->db->table('deployments')
            ->where('id', $deployment->id)
            ->get()
            ->getRowArray()['status'];
    }

    private function versionOf(int $deploymentId): string {
        return (string) $this->db->table('deployments')
            ->where('id', $deploymentId)
            ->get()
            ->getRowArray()['version'];
    }

    private function debugLog(): string {
        return implode("\n", array_map(
            fn ($line) => is_string($line) ? $line : json_encode($line),
            Data::getDebugger()
        ));
    }

    private function forgetTheDebugStore(): void {
        $store = (new \ReflectionClass(Data::class))->getProperty('store');
        $store->setValue(null, ['status' => null]);
    }

    // </editor-fold>

}
