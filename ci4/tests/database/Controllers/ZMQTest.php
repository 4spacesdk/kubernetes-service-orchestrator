<?php namespace App\Tests\Database\Controllers;

use App\DatabaseTestCase;
use App\Entities\AutoUpdate;
use App\Entities\Deployment;
use App\Entities\Webhook;
use App\Entities\ZMQEvent;
use App\Fixtures;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CLIRequest;
use DebugTool\Data;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The controller the zmq client calls into when something happened elsewhere in kso.
 *
 * It has two halves, and each one is reached a different way.
 *
 * `initController()` is the half that takes the event in: it reads the event off the
 * command line, stores it in `zmq_events`, and decides whether **this** container is the
 * one that handles it - every container is handed every event. That decision is a `die` in
 * one direction, so only the first-mover path can be run from a test. The duplicate path
 * is marked out in the controller.
 *
 * The nine handler methods are the other half, and they do not go through
 * `initController()` at all: reaching them that way would mean feeding an event in over
 * argv, and the `die` would take phpunit with it. The private `$event` is therefore set by
 * reflection and the method called directly - which is exactly what the zmq client ends up
 * doing.
 *
 * What is worth pinning down in a handler is **which decision it makes**: seven of them
 * pick a webhook type, one waits for a migration job, and the last rolls an update out. The
 * name of the method and the type it delivers is the only thing telling the seven apart,
 * and a swap between two of them is not something anyone notices.
 */
class ZMQTest extends DatabaseTestCase {

    /** @var array<int, string> */
    private array $realArgv = [];

    public function setUp(): void {
        parent::setUp();

        // The debug store is a static that lives for the whole process, so without this an
        // assertion about what *this* handler wrote reads the previous test's lines.
        $this->forgetTheDebugStore();

        $this->realArgv = service('superglobals')->server('argv');
    }

    public function tearDown(): void {
        // `superglobals` is shared and outlives the test. Without this the rest of the
        // process runs with the phpunit arguments this test made up.
        service('superglobals')->setServer('argv', $this->realArgv);

        parent::tearDown();
    }

    // <editor-fold desc="The event coming in">

    /**
     * The first-mover path: no other container has stored this event, so it is ours.
     *
     * The row in `zmq_events` is not bookkeeping - it **is** the lock. The lowest numbered
     * row for an identifier wins, and every other container deletes its own again and goes
     * home. Without the row being stored, every container handles the same event.
     */
    public function testTheEventOnTheCommandLineIsStoredAndKept(): void {
        $controller = $this->initControllerWith('id-4711', 'workspace-created', '{"next":{"id":9}}');

        $rows = $this->db->table('zmq_events')->where('identifier', 'id-4711')->get()->getResultArray();

        $this->assertCount(1, $rows);
        $this->assertSame('workspace-created', $rows[0]['event']);
        $this->assertSame((int) $rows[0]['id'], $this->eventOf($controller)->id, '$this->event points somewhere other than the row');
    }

    /**
     * The data is stored pretty-printed rather than as it arrived. It is read by a person
     * looking for what went wrong, and a single line of json is not readable.
     *
     * The trip through `json_decode`/`json_encode` is at the same time the only thing that
     * objects to something that is not json: it ends up as `null` rather than as raw text
     * in the column.
     */
    public function testTheDataIsStoredPrettyPrinted(): void {
        $this->initControllerWith('id-pretty', 'workspace-created', '{"next":{"id":9,"name":"a"}}');

        $stored = $this->db->table('zmq_events')->where('identifier', 'id-pretty')->get()->getRowArray();

        $this->assertSame(
            json_encode(json_decode('{"next":{"id":9,"name":"a"}}'), JSON_PRETTY_PRINT),
            $stored['data']
        );
        $this->assertStringContainsString("\n", $stored['data'], 'the data was stored on one line');
    }

    /**
     * The data arrives base64 encoded, because it would otherwise have to survive a shell
     * with quotes and braces in it. The decoding is not decoration.
     */
    public function testTheDataArrivesBase64Encoded(): void {
        $this->initControllerWith('id-b64', 'workspace-created', '{"next":{"id":1}}');

        $stored = $this->db->table('zmq_events')->where('identifier', 'id-b64')->get()->getRowArray();

        $this->assertStringContainsString('"id": 1', $stored['data']);
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
    public function testAHandlerDeliversItsOwnWebhookTypeAndNobodyElses(string $method, string $type): void {
        $this->aWebhookForEveryType();

        $this->handle($method, ['id' => 7, 'name' => 'the-workspace']);

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries, 'something other than one webhook was delivered to');

        $payload = json_decode($deliveries[0]['payload'], true);
        $this->assertSame($type, $payload['event']);
        $this->assertSame($type, $deliveries[0]['webhook_type'], 'the delivery went to a webhook of another type');
    }

    /**
     * What the subscriber is told is `next` - the state after the change - and not the
     * whole event envelope. A webhook carrying `previous` would tell a foreign recipient
     * what the fields used to be, and that is not what was agreed.
     */
    public function testTheWebhookCarriesTheNewStateAndNotThePreviousOne(): void {
        $this->aWebhookForEveryType();

        $this->handle(
            'workspaceUpdated',
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
        $this->handle('workspaceCreated', ['id' => 7]);

        $this->assertSame([], $this->deliveries());
        $this->assertStringContainsString('delivered workspace-created to 0 webhooks', $this->debugLog());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function theHandlersThatDeliverAWebhook(): array {
        return [
            'workspaceCreated' => ['workspaceCreated', \WebHookTypes::Workspace_Created],
            'workspaceUpdated' => ['workspaceUpdated', \WebHookTypes::Workspace_Updated],
            'workspaceDeleted' => ['workspaceDeleted', \WebHookTypes::Workspace_Deleted],
            'workspaceDeployed' => ['workspaceDeployed', \WebHookTypes::Workspace_Deployed],
            'workspaceTerminated' => ['workspaceTerminated', \WebHookTypes::Workspace_Terminated],
            'deploymentDeployed' => ['deploymentDeployed', \WebHookTypes::Deployment_Deployed],
            'deploymentTerminated' => ['deploymentTerminated', \WebHookTypes::Deployment_Terminated],
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

        $this->handle('migrationJobChangedStatus', [
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

        $this->handle('migrationJobChangedStatus', [
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

        $this->handle('autoUpdateApproved', ['id' => $named->id]);

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

        $this->handle('autoUpdateApproved', ['id' => $autoUpdate->id]);

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

        $this->handle('autoUpdateApproved', ['id' => $autoUpdate->id]);

        $this->assertSame('old', $this->versionOf($autoUpdate->deployment_id));
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * Run `initController()` with an event on the command line.
     *
     * `Services::clirequest()` is what the controller reads its options from, and a
     * CLIRequest parses argv in its constructor - so argv is written first and the finished
     * request is installed as the shared instance afterwards.
     *
     * Argv has to be written through the `superglobals` service and not into `$_SERVER`:
     * since 4.7 a request reads its globals from that service, which took its copy long
     * before the test got here. A write straight into `$_SERVER` is silently ignored, and
     * the controller sees phpunit's own arguments instead - whereupon it `die`s on the
     * duplicate path and takes phpunit with it, without a line in the output.
     */
    private function initControllerWith(string $identifier, string $event, string $data): \App\Controllers\ZMQ {
        service('superglobals')->setServer('argv', [
            'index.php',
            'zmq',
            '--identifier', $identifier,
            '--event', $event,
            '--data', base64_encode($data),
        ]);
        Services::injectMock('clirequest', new CLIRequest(config('App')));

        $controller = new \App\Controllers\ZMQ();
        $controller->initController(Services::request(), Services::response(), service('logger'));

        return $controller;
    }

    /**
     * Call a handler with an event already in place, without going through
     * `initController()`.
     *
     * @param array<string, mixed> $next
     * @param array<string, mixed>|null $previous
     */
    private function handle(string $method, array $next, ?array $previous = null): void {
        $event = new ZMQEvent();
        $event->data = json_encode(['previous' => $previous, 'next' => $next]);

        $controller = new \App\Controllers\ZMQ();
        $property = (new \ReflectionClass(\App\Controllers\ZMQ::class))->getProperty('event');
        $property->setValue($controller, $event);

        $controller->{$method}();
    }

    private function eventOf(\App\Controllers\ZMQ $controller): ZMQEvent {
        $property = (new \ReflectionClass(\App\Controllers\ZMQ::class))->getProperty('event');

        return $property->getValue($controller);
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
