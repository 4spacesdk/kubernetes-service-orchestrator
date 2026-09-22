<?php namespace App\Tests\Database\Health;

use App\Commands\CheckHealth;
use App\DatabaseTestCase;
use App\Entities\CronJob;
use App\Entities\Deployment;
use App\Entities\Workspace;
use App\Fixtures;
use App\Libraries\Health\ClusterSnapshot;
use App\Libraries\Kubernetes\ClusterIndex;
use App\Libraries\Kubernetes\IndexedCluster;
use App\Libraries\Health\HealthCheck;
use App\Libraries\Push\ChangeEvent;
use App\Libraries\Push\EventHandlers;
use App\Libraries\Push\Events;
use App\Libraries\Push\Publisher;
use App\SilentPush;

/**
 * What `HealthCheck` writes down, and what it tells whom.
 *
 * The rules themselves are in the unit suite (`HealthEvaluatorTest`). These are about the
 * promises around them: a cluster that does not answer changes nothing, the status is left
 * alone, the audit trail is not filled with a row a minute, a workspace takes the worst of its
 * deployments, and a webhook waits for a new health to hold.
 *
 * The cluster is a snapshot built from arrays, handed in through `HealthCheck::$snapshots`.
 * The publisher enqueues without pushing, so what was sent is read off the queue's table.
 */
class HealthCheckTest extends DatabaseTestCase {

    private const int T0 = 1_800_000_000;

    private ?ClusterSnapshot $cluster = null;
    private int $clusterReads = 0;
    private int $indexReads = 0;
    private ClusterIndex $index;

    public function setUp(): void {
        parent::setUp();

        HealthCheck::$snapshots = function (bool $withKnative): ClusterSnapshot {
            $this->clusterReads++;
            if ($this->cluster === null) {
                throw new \RuntimeException('The cluster did not answer');
            }
            return $this->cluster;
        };
        // A cluster with the namespace in it and nothing else: every kind is looked at, so no
        // step falls through to an api server that is not there, and the namespace is there
        // because a deployment whose namespace is gone is put back in Draft - which is a
        // different test from the ones about health. The url is one nothing listens on, so a
        // step that did fall through fails the test rather than reaching anything.
        HealthCheck::$indexedClusters = function (): IndexedCluster {
            $this->indexReads++;
            return (new IndexedCluster('http://127.0.0.1:9'))->useIndex($this->index);
        };
        // Both: the deployment's own namespace, and the workspace's, which is the one the
        // namespace step looks for.
        $this->index = self::AnIndexHolding(['ns', 'test']);
        $instance = new \ReflectionProperty(Publisher::class, 'instance');
        $instance->setValue(null, new Publisher(null, true));
    }

    public function tearDown(): void {
        HealthCheck::$snapshots = null;
        HealthCheck::$indexedClusters = null;
        SilentPush::install();
        parent::tearDown();
    }

    // <editor-fold desc="What is written">

    public function testAHealthIsWrittenWithWhenItChangedAndWhenItWasChecked(): void {
        $deployment = $this->activeDeployment();
        $this->clusterSays(pods: [$this->pod()]);

        HealthCheck::Run(null, self::T0);

        $row = $this->reread($deployment);
        $this->assertSame(\HealthStatusTypes::Healthy, $row->health);
        $this->assertSame(1, (int) $row->health_severity);
        $this->assertSame($this->at(self::T0), $row->health_changed_at);
        $this->assertSame($this->at(self::T0), $row->health_checked_at);
    }

    /**
     * "Since when" is the health's age, not the reason's. A crash loop that moves from one pod
     * to the next is the same problem, and has been since it started.
     */
    public function testANewReasonForTheSameHealthKeepsItsTime(): void {
        $deployment = $this->activeDeployment();
        $this->clusterSays(pods: [$this->pod('api-1', 'CrashLoopBackOff')]);
        HealthCheck::Run(null, self::T0);

        $this->clusterSays(pods: [$this->pod('api-2', 'CrashLoopBackOff')]);
        HealthCheck::Run(null, self::T0 + 60);

        $row = $this->reread($deployment);
        $this->assertSame(\HealthStatusTypes::Degraded, $row->health);
        $this->assertSame('CrashLoopBackOff (api-2)', $row->health_reason);
        $this->assertSame($this->at(self::T0), $row->health_changed_at);
        $this->assertSame($this->at(self::T0 + 60), $row->health_checked_at);
    }

    /**
     * A timeout is not an answer. The last health stays, and the check time that stops moving
     * is what shows the web app it is stale.
     */
    public function testAClusterThatDoesNotAnswerChangesNothing(): void {
        $deployment = $this->activeDeployment();
        $this->clusterSays(pods: [$this->pod()]);
        HealthCheck::Run(null, self::T0);

        $this->cluster = null;
        try {
            HealthCheck::Run(null, self::T0 + 60);
            $this->fail('A cluster that did not answer was taken as an answer');
        } catch (\RuntimeException) {
        }

        $row = $this->reread($deployment);
        $this->assertSame(\HealthStatusTypes::Healthy, $row->health);
        $this->assertSame($this->at(self::T0), $row->health_checked_at);
    }

    /**
     * A deployment that cannot be deployed - no workspace here - is a Draft, and a Draft has no
     * health. The pods are not even asked for.
     */
    public function testADraftHasNoHealthAndTheClusterIsNotAsked(): void {
        $deployment = Fixtures::deployment(['name' => 'api', 'namespace' => 'ns', 'status' => \DeploymentStatusTypes::Draft]);

        HealthCheck::Run(null, self::T0);

        $row = $this->reread($deployment);
        $this->assertSame(\DeploymentStatusTypes::Draft, $row->status);
        $this->assertNull($row->health);
        $this->assertSame(0, $this->clusterReads);
    }

    /**
     * A paused workspace is not looked for in the cluster at all - its workloads may well be
     * gone, and that is what paused means.
     */
    public function testADeploymentInAPausedWorkspaceIsSuspendedWithoutAskingTheCluster(): void {
        $workspace = Fixtures::workspace(['is_paused' => true, 'status' => \WorkspaceStatusTypes::Paused]);
        $deployment = $this->activeDeployment(['workspace_id' => $workspace->id]);

        HealthCheck::Run(null, self::T0);

        $this->assertSame(\HealthStatusTypes::Suspended, $this->reread($deployment)->health);
        $this->assertSame(0, $this->clusterReads);
    }

    /**
     * Health never writes the status - auto update picks deployments by that, and a crash loop
     * must not change what it does. Followed through a rollout, where the status is not
     * recomputed at all, the workload is Missing and the status is untouched.
     */
    public function testHealthNeverWritesTheStatus(): void {
        $deployment = $this->activeDeployment();
        $this->cluster = ClusterSnapshot::FromArrays([], []);

        HealthCheck::Run($deployment->id, self::T0);

        $row = $this->reread($deployment);
        $this->assertSame(\HealthStatusTypes::Missing, $row->health);
        $this->assertSame(\DeploymentStatusTypes::Synced, $row->status);
    }

    /**
     * Health is not recorded in the audit trail: once a minute for every deployment would bury
     * everything a person did. A status that changes is recorded, as it is whoever changes it -
     * that is a resource appearing or going away, which is worth being able to look up.
     */
    public function testHealthIsNotRecordedInTheAuditTrailButAStatusChangeIs(): void {
        $deployment = $this->activeDeployment();
        $this->clusterSays(pods: [$this->pod('api-1', 'CrashLoopBackOff')]);
        HealthCheck::Run(null, self::T0);
        $after = $this->db->table('audit_events')->countAllResults();

        // Only the health moves this time; the status has already settled.
        $this->clusterSays(pods: [$this->pod()]);
        HealthCheck::Run(null, self::T0 + HealthCheck::SettleFor);

        $this->assertSame($after, $this->db->table('audit_events')->countAllResults());
        $recorded = $this->db->table('audit_events')
            ->where('resource_type', 'Deployment')
            ->where('action', \App\Libraries\Audit\Audit::Updated)
            ->get()->getResultArray();
        $this->assertCount(1, $recorded, 'the status that changed on the first run was not recorded');
        $this->assertStringContainsString(\DeploymentStatusTypes::OutOfSync, $recorded[0]['details']);
    }

    /**
     * The status is recomputed by the minute run too, so a resource removed behind
     * kso's back is noticed within the minute, instead of standing as Synced until somebody
     * deploys or presses refresh.
     *
     * The namespace is in the index and the workload is not, which is what it looks like when
     * somebody deletes a Deployment with kubectl.
     */
    public function testTheMinuteRunRecomputesTheStatusFromTheIndex(): void {
        $deployment = $this->activeDeployment();
        $this->clusterSays(pods: []);

        HealthCheck::Run(null, self::T0);

        $this->assertSame(\DeploymentStatusTypes::OutOfSync, $this->reread($deployment)->status);
        $this->assertSame(1, $this->indexReads, 'the kinds were listed more than once');
    }

    /**
     * And the namespace itself being gone puts the deployment back in Draft, as it does when the
     * status is checked any other way: there is nothing to deploy into.
     */
    public function testANamespaceThatIsGoneMakesItADraftAgain(): void {
        $deployment = $this->activeDeployment();
        $this->index = self::AnIndexHolding([]);
        $this->clusterSays(pods: []);

        HealthCheck::Run(null, self::T0);

        $row = $this->reread($deployment);
        $this->assertSame(\DeploymentStatusTypes::Draft, $row->status);
        $this->assertNull($row->health, 'a draft has no health');
    }

    /**
     * A workspace adds its deployments up again afterwards - once, not once per deployment.
     */
    public function testTheWorkspaceFollowsTheStatusesItsDeploymentsGot(): void {
        $deployment = $this->activeDeployment();
        $workspace = new Workspace();
        $workspace->find($deployment->workspace_id);
        $workspace->updateStatus(\WorkspaceStatusTypes::Synced);
        $this->clusterSays(pods: []);

        HealthCheck::Run(null, self::T0);

        $workspace->find($deployment->workspace_id);
        $this->assertSame(\WorkspaceStatusTypes::OutOfSync, $workspace->status);
    }

    /**
     * Following one deployment through its rollout does not list every kind in the cluster: that
     * is twenty calls to answer a question worth eight, and the deploy just checked the status
     * itself anyway.
     */
    public function testFollowingOneDeploymentDoesNotListEveryKind(): void {
        $deployment = $this->activeDeployment();
        $this->clusterSays(pods: [$this->pod()]);

        HealthCheck::Run($deployment->id, self::T0);

        $this->assertSame(0, $this->indexReads);
    }

    // </editor-fold>

    // <editor-fold desc="The workspace">

    public function testAWorkspaceTakesTheWorstOfItsDeploymentsAndNamesIt(): void {
        $workspace = Fixtures::workspace(['status' => \WorkspaceStatusTypes::Synced]);
        $this->activeDeployment(['workspace_id' => $workspace->id, 'name' => 'api']);
        $this->activeDeployment(['workspace_id' => $workspace->id, 'name' => 'web']);
        $this->activeDeployment(['workspace_id' => $workspace->id, 'name' => 'notes', 'status' => \DeploymentStatusTypes::Draft]);
        $this->cluster = ClusterSnapshot::FromArrays(
            [$this->deploymentInTheCluster('api'), $this->deploymentInTheCluster('web')],
            [$this->pod('api-1'), $this->pod('web-1', 'CrashLoopBackOff', 'web')],
        );

        HealthCheck::Run(null, self::T0);

        $row = new Workspace();
        $row->find($workspace->id);
        $this->assertSame(\HealthStatusTypes::Degraded, $row->health);
        $this->assertSame('web: CrashLoopBackOff (web-1)', $row->health_reason);
        $this->assertSame($this->at(self::T0), $row->health_changed_at);
    }

    /**
     * Healthy needs no names - "api: healthy; web: healthy" is a row full of nothing.
     */
    public function testAHealthyWorkspaceHasNoReason(): void {
        $workspace = Fixtures::workspace(['status' => \WorkspaceStatusTypes::Synced]);
        $this->activeDeployment(['workspace_id' => $workspace->id]);
        $this->clusterSays(pods: [$this->pod()]);

        HealthCheck::Run(null, self::T0);

        $row = new Workspace();
        $row->find($workspace->id);
        $this->assertSame(\HealthStatusTypes::Healthy, $row->health);
        $this->assertSame('', (string) $row->health_reason);
    }

    // </editor-fold>

    // <editor-fold desc="The webhook">

    public function testANewHealthIsSentOnlyOnceItHasHeld(): void {
        $this->activeDeployment();
        $this->clusterSays(pods: [$this->pod('api-1', 'CrashLoopBackOff')]);

        HealthCheck::Run(null, self::T0);
        HealthCheck::Run(null, self::T0 + HealthCheck::SettleFor - 1);
        $this->assertSame([], $this->queued(Events::Deployment_Health_Settled()), 'sent before it had held');

        HealthCheck::Run(null, self::T0 + HealthCheck::SettleFor);
        HealthCheck::Run(null, self::T0 + HealthCheck::SettleFor + 60);

        $sent = $this->queued(Events::Deployment_Health_Settled());
        $this->assertCount(1, $sent, 'not sent exactly once');
        $this->assertSame(\HealthStatusTypes::Degraded, $sent[0]['next']['health']);
        $this->assertNull($sent[0]['next']['previous_health']);
    }

    /**
     * The upgrade that brings this in gives every deployment its first health at once. Sending
     * "healthy" for each of them would be the first thing anybody hears from it.
     */
    public function testAFirstHealthThatIsGoodIsNotAnnouncedButLaterChangesAre(): void {
        $this->activeDeployment();
        $this->clusterSays(pods: [$this->pod()]);
        HealthCheck::Run(null, self::T0);
        HealthCheck::Run(null, self::T0 + HealthCheck::SettleFor);
        $this->assertSame([], $this->queued(Events::Deployment_Health_Settled()));

        $this->clusterSays(pods: [$this->pod('api-1', 'CrashLoopBackOff')]);
        HealthCheck::Run(null, self::T0 + 200);
        HealthCheck::Run(null, self::T0 + 200 + HealthCheck::SettleFor);

        $sent = $this->queued(Events::Deployment_Health_Settled());
        $this->assertCount(1, $sent);
        $this->assertSame(\HealthStatusTypes::Degraded, $sent[0]['next']['health']);
        $this->assertSame(\HealthStatusTypes::Healthy, $sent[0]['next']['previous_health']);
    }

    /**
     * Degraded for a minute and back again - a pod replaced during a node drain - is not news.
     */
    public function testAHealthThatDidNotHoldIsNeverSent(): void {
        $this->activeDeployment();
        $this->clusterSays(pods: [$this->pod()]);
        HealthCheck::Run(null, self::T0);
        HealthCheck::Run(null, self::T0 + HealthCheck::SettleFor);

        $this->clusterSays(pods: [$this->pod('api-1', 'CrashLoopBackOff')]);
        HealthCheck::Run(null, self::T0 + 200);
        $this->clusterSays(pods: [$this->pod()]);
        HealthCheck::Run(null, self::T0 + 260);
        HealthCheck::Run(null, self::T0 + 260 + HealthCheck::SettleFor);

        $this->assertSame([], $this->queued(Events::Deployment_Health_Settled()));
    }

    // </editor-fold>

    // <editor-fold desc="Following a rollout">

    public function testADeployStartsFollowingTheDeploymentAfterAShortDelay(): void {
        $deployment = $this->activeDeployment();

        EventHandlers::Handle(Events::Deployment_Deployed(), new ChangeEvent(null, ['id' => $deployment->id]));

        $job = $this->db->table('queue_jobs')->get()->getResultArray();
        $follows = array_values(array_filter($job, fn($row) => json_decode($row['payload'], true)['data']['event'] === Events::Deployment_Health_Follow()));
        $this->assertCount(1, $follows);
        $this->assertGreaterThan(time(), (int) $follows[0]['available_at']);
        $this->assertSame((int) $deployment->id, (int) json_decode($follows[0]['payload'], true)['data']['data']['next']['deployment_id']);
    }

    public function testAFollowedDeploymentIsLookedAtAgainWhileItRollsOut(): void {
        $deployment = $this->activeDeployment();
        $this->cluster = ClusterSnapshot::FromArrays([$this->deploymentInTheCluster('api', rollingOut: true)], [$this->pod()]);

        HealthCheck::Follow($deployment->id, time() + 60);

        $this->assertSame(\HealthStatusTypes::Progressing, $this->reread($deployment)->health);
        $this->assertCount(1, $this->queued(Events::Deployment_Health_Follow()));
    }

    public function testAFollowStopsWhenTheRolloutIsDone(): void {
        $deployment = $this->activeDeployment();
        $this->clusterSays(pods: [$this->pod()]);

        HealthCheck::Follow($deployment->id, time() + 60);

        $this->assertSame([], $this->queued(Events::Deployment_Health_Follow()));
    }

    /**
     * A rollout that goes on for longer than a quarter of an hour is left to the minute run.
     */
    public function testAFollowStopsWhenItsTimeIsUp(): void {
        $deployment = $this->activeDeployment();
        $this->cluster = ClusterSnapshot::FromArrays([$this->deploymentInTheCluster('api', rollingOut: true)], [$this->pod()]);

        HealthCheck::Follow($deployment->id, time() - 1);

        $this->assertSame([], $this->queued(Events::Deployment_Health_Follow()));
    }

    // </editor-fold>

    // <editor-fold desc="The cron job">

    public function testTheCronJobSaysWhatItDidInItsLog(): void {
        $this->activeDeployment();
        $this->clusterSays(pods: [$this->pod()]);

        $this->runTheCronJob();

        $this->assertStringContainsString('checked 1 changed 1 notified 0', $this->cronLog());
    }

    public function testTheCronJobLogsAClusterThatDidNotAnswerAndGoesOn(): void {
        $this->activeDeployment();

        $this->runTheCronJob();

        $this->assertStringContainsString('The cluster could not be read, nothing was changed', $this->cronLog());
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * An Active deployment called `api` in `ns`, of a Deployment workload.
     *
     * @param array<string, mixed> $overrides
     */
    private function activeDeployment(array $overrides = []): Deployment {
        // Deployable, because the run recomputes the status before it works out the health: a
        // deployment that cannot pass its steps' validation is Draft, and a Draft has no health.
        return Fixtures::deployableDeployment([
            'name' => 'api',
            'namespace' => 'ns',
            'image' => 'registry/api',
            'version' => '1.0',
            'status' => \DeploymentStatusTypes::Synced,
            ...$overrides,
        ], ['workload_type' => \WorkloadTypes::Deployment]);
    }

    /**
     * @param list<array> $pods
     */
    private function clusterSays(array $pods): void {
        $this->cluster = ClusterSnapshot::FromArrays([$this->deploymentInTheCluster('api')], $pods);
    }

    private function deploymentInTheCluster(string $name, bool $rollingOut = false): array {
        return [
            'metadata' => ['namespace' => 'ns', 'name' => $name, 'generation' => $rollingOut ? 3 : 2],
            'spec' => ['replicas' => 1],
            'status' => [
                'observedGeneration' => 2,
                'replicas' => 1,
                'updatedReplicas' => 1,
                'availableReplicas' => 1,
                'conditions' => [['type' => 'Progressing', 'status' => 'True', 'reason' => 'NewReplicaSetAvailable']],
            ],
        ];
    }

    private function pod(string $name = 'api-1', ?string $waiting = null, string $app = 'api'): array {
        return [
            'metadata' => ['namespace' => 'ns', 'name' => $name, 'labels' => ['app' => $app, 'role' => 'app']],
            'status' => ['containerStatuses' => [[
                'name' => $app,
                'state' => $waiting ? ['waiting' => ['reason' => $waiting]] : ['running' => []],
                'restartCount' => 0,
                'lastState' => [],
            ]]],
        ];
    }

    /**
     * Every kind kso deploys, holding the namespaces named and nothing else. A namespace is
     * cluster-scoped, so it is held under a key with no namespace of its own.
     *
     * @param list<string> $namespaces
     */
    private static function AnIndexHolding(array $namespaces): ClusterIndex {
        $held = [];
        foreach ($namespaces as $name) {
            $held["/{$name}"] = ['metadata' => ['name' => $name]];
        }
        return ClusterIndex::Of([\RenokiCo\PhpK8s\Kinds\K8sNamespace::class => $held], ClusterIndex::Kinds);
    }

    private function reread(Deployment $deployment): Deployment {
        $row = new Deployment();
        $row->find($deployment->id);
        return $row;
    }

    private function at(int $time): string {
        return date('Y-m-d H:i:s', $time);
    }

    /**
     * What was put on the queue for one event, as the handler would be given it.
     *
     * @return list<array{previous: ?array, next: array}>
     */
    private function queued(string $event): array {
        $found = [];
        foreach ($this->db->table('queue_jobs')->orderBy('id', 'asc')->get()->getResultArray() as $row) {
            $payload = json_decode($row['payload'], true)['data'];
            if ($payload['event'] === $event) {
                $found[] = $payload['data'];
            }
        }
        return $found;
    }

    /**
     * The debug store is a static for the whole process; without emptying it first, the log
     * would hold every earlier test's lines too.
     */
    private function runTheCronJob(): void {
        $store = (new \ReflectionClass(\DebugTool\Data::class))->getProperty('store');
        $store->setValue(null, ['status' => null]);

        (new CheckHealth(service('logger'), service('commands')))->run([]);
    }

    private function cronLog(): string {
        $job = new CronJob();
        $job->find(\CronJobIds::CheckHealth);
        return (string) $job->last_log;
    }

    // </editor-fold>

}
