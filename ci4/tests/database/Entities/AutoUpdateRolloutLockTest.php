<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\AutoUpdate;
use App\Entities\Deployment;
use App\Entities\DeploymentSpecificationPostUpdateAction;
use App\Fixtures;
use App\Tests\Fakes\FakeIntegrations;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Several queue workers take jobs at once, so two updates of one deployment can be taken at
 * the same moment. `AutoUpdate::rollout()` holds a lock per deployment while it runs: the
 * second waits for the first, and updates of other deployments do not wait at all.
 *
 * The rollout ahead is played by another connection holding the lock, as it would from the
 * other worker's process.
 */
class AutoUpdateRolloutLockTest extends DatabaseTestCase {

    private ?BaseConnection $otherWorker = null;

    public function setUp(): void {
        parent::setUp();
        AutoUpdate::$lockWait = 1;
    }

    public function tearDown(): void {
        AutoUpdate::$lockWait = 600;
        if ($this->otherWorker) {
            $this->otherWorker->query('SELECT RELEASE_ALL_LOCKS()');
            $this->otherWorker->close();
            $this->otherWorker = null;
        }
        parent::tearDown();
    }

    // <editor-fold desc="The lock">

    /**
     * Not rolled out alongside the one ahead - and when that one takes too long, the worker
     * sees it fail, and the update says why.
     */
    public function testAnUpdateWaitsForTheRolloutAheadOfItOnTheSameDeployment(): void {
        $update = $this->anApprovedUpdate(Fixtures::autoUpdatableDeployment(), '2.0.0');
        $this->anotherWorkerIsRollingOut($update->deployment_id);

        try {
            $update->rollout();
            $this->fail('rolled out alongside the rollout ahead of it');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('did not finish within 1 seconds', $e->getMessage());
        }

        $this->assertSame('old', $this->versionOf($update->deployment_id));
        $this->assertStringContainsString('did not finish within', $this->logOf($update));
    }

    public function testAnUpdateOfAnotherDeploymentDoesNotWait(): void {
        $busy = Fixtures::autoUpdatableDeployment();
        $update = $this->anApprovedUpdate(Fixtures::autoUpdatableDeployment(), '2.0.0');
        $this->anotherWorkerIsRollingOut($busy->id);

        $update->rollout();

        $this->assertSame('2.0.0', $this->versionOf($update->deployment_id));
    }

    public function testTheLockIsLetGoWhenTheRolloutEnds(): void {
        $update = $this->anApprovedUpdate(Fixtures::autoUpdatableDeployment(), '2.0.0');

        $update->rollout();

        $this->assertTrue($this->anotherWorkerCanTake($update->deployment_id));
    }

    public function testTheLockIsLetGoWhenTheRolloutThrows(): void {
        $fakes = FakeIntegrations::install();
        $fakes->shortSha = 'abc1234';
        $fakes->commitMessage = 'Fixes https://podio.com/acme/app/1/items/4217';
        $fakes->podio()->failCommentsWith = new \RuntimeException('Podio said no');
        $deployment = Fixtures::deployableDeployment(['auto_update_enabled' => true, 'version' => 'old']);
        $action = Fixtures::postUpdateAction([
            'type' => \PostUpdateActionTypes::Podio_AddComment,
            'podio_add_comment_integration_id' => Fixtures::podioIntegration()->id,
            'podio_add_comment_value' => 'Deployed',
        ]);
        $attached = new DeploymentSpecificationPostUpdateAction();
        $attached->deployment_specification_id = $deployment->deployment_specification_id;
        $attached->post_update_action_id = $action->id;
        $attached->position = 1;
        $attached->save();
        $update = $this->anApprovedUpdate($deployment, '2.0.0');

        try {
            $update->rollout();
            $this->fail('the rollout did not fail');
        } catch (\RuntimeException $e) {
            $this->assertSame('Podio said no', $e->getMessage());
        } finally {
            FakeIntegrations::uninstall();
        }

        $this->assertTrue($this->anotherWorkerCanTake($update->deployment_id));
    }

    /**
     * A lock is the MySQL server's, and two installations - or the test suite beside a
     * development database - can share one server.
     */
    public function testTheLockIsNamedAfterTheDatabaseToo(): void {
        $this->assertSame('kso-rollout-' . $this->db->getDatabase() . '-42', AutoUpdate::LockName(42));
    }

    // </editor-fold>

    // <editor-fold desc="Overtaken">

    /**
     * Two workers took both updates, and the later one got the lock first. The earlier one
     * would put the older tag back.
     */
    public function testAnUpdateALaterOneHasRolledOutAheadOfIsSkipped(): void {
        $deployment = Fixtures::autoUpdatableDeployment();
        $earlier = $this->anApprovedUpdate($deployment, '2.0.0', '2026-10-29 08:25:00');
        $this->anApprovedUpdate($deployment, '2.0.1', '2026-10-29 08:26:00', log: "rollout image 2.0.1\n");

        $earlier->rollout();

        $this->assertSame('old', $this->versionOf($deployment->id));
        $this->assertStringContainsString('Skip rollout because a later update of the deployment has rolled out', $this->logOf($earlier));
    }

    /**
     * The later one is waiting for this one - this one goes first, as it would have with one
     * worker.
     */
    public function testALaterUpdateThatHasNotRolledOutYetHoldsNothingBack(): void {
        $deployment = Fixtures::autoUpdatableDeployment();
        $earlier = $this->anApprovedUpdate($deployment, '2.0.0', '2026-10-29 08:25:00');
        $this->anApprovedUpdate($deployment, '2.0.1', '2026-10-29 08:26:00');

        $earlier->rollout();

        $this->assertSame('2.0.0', $this->versionOf($deployment->id));
    }

    /**
     * Re-approve rolls an old update out again, on purpose, after the ones that came since.
     */
    public function testAReapprovedUpdateRollsOutOverTheOnesBeforeIt(): void {
        $deployment = Fixtures::autoUpdatableDeployment();
        $reapproved = $this->anApprovedUpdate($deployment, '2.0.0', '2026-10-29 09:00:00', log: "rollout image 2.0.0\n");
        $this->anApprovedUpdate($deployment, '2.0.1', '2026-10-29 08:26:00', log: "rollout image 2.0.1\n");

        $reapproved->rollout();

        $this->assertSame('2.0.0', $this->versionOf($deployment->id));
    }

    /**
     * Approved in the same second - Approve selected goes down the list a fifth of a second
     * apart. The later row is the later update.
     */
    public function testInTheSameSecondTheLaterRowIsTheLaterUpdate(): void {
        $deployment = Fixtures::autoUpdatableDeployment();
        $earlier = $this->anApprovedUpdate($deployment, '2.0.0', '2026-10-29 08:25:00');
        $this->anApprovedUpdate($deployment, '2.0.1', '2026-10-29 08:25:00', log: "rollout image 2.0.1\n");

        $earlier->rollout();

        $this->assertSame('old', $this->versionOf($deployment->id));
    }

    public function testAnUpdateOfAnotherDeploymentDoesNotOvertake(): void {
        $deployment = Fixtures::autoUpdatableDeployment();
        $update = $this->anApprovedUpdate($deployment, '2.0.0', '2026-10-29 08:25:00');
        $this->anApprovedUpdate(Fixtures::autoUpdatableDeployment(), '2.0.1', '2026-10-29 08:26:00', log: "rollout image 2.0.1\n");

        $update->rollout();

        $this->assertSame('2.0.0', $this->versionOf($deployment->id));
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    private function anApprovedUpdate(Deployment $deployment, string $tag, string $approvedDate = '2026-10-29 08:25:00', ?string $log = null): AutoUpdate {
        $update = new AutoUpdate();
        $update->deployment_id = $deployment->id;
        $update->image = 'image';
        $update->previous_tag = 'old';
        $update->next_tag = $tag;
        $update->is_approved = true;
        $update->approved_date = $approvedDate;
        $update->log = $log;
        $update->save();

        return $update;
    }

    private function otherWorker(): BaseConnection {
        return $this->otherWorker ??= Database::connect(null, false);
    }

    private function anotherWorkerIsRollingOut(int $deploymentId): void {
        $this->otherWorker()->query('SELECT GET_LOCK(?, 0)', [AutoUpdate::LockName($deploymentId)]);
    }

    private function anotherWorkerCanTake(int $deploymentId): bool {
        return (int) $this->otherWorker()
            ->query('SELECT GET_LOCK(?, 0) AS taken', [AutoUpdate::LockName($deploymentId)])
            ->getRow()->taken === 1;
    }

    private function versionOf(int $deploymentId): string {
        return (string) $this->db->table('deployments')->where('id', $deploymentId)->get()->getRowArray()['version'];
    }

    private function logOf(AutoUpdate $update): string {
        return (string) (new AutoUpdate())->find($update->id)->log;
    }

    // </editor-fold>

}
