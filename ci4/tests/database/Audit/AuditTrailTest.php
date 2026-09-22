<?php namespace App\Tests\Database\Audit;

use App\DatabaseTestCase;
use App\Entities\CronJob;
use App\Entities\DatabaseService;
use App\Entities\Deletion;
use App\Entities\RbacPermission;
use App\Entities\RbacRole;
use App\Fixtures;
use App\Libraries\Audit\Audit;
use App\Libraries\Audit\AuditContext;
use CodeIgniter\HTTP\CLIRequest;
use Config\Services;

/**
 * What the entities write in the audit trail when they are saved, deleted and related - see
 * `Audited`. One row per change, with what changed, and never a secret.
 */
class AuditTrailTest extends DatabaseTestCase {

    public function tearDown(): void {
        AuditContext::Forget();
        parent::tearDown();
    }

    public function testACreatedRowIsRecordedWithItsFields(): void {
        $service = Fixtures::databaseService(['name' => 'shared-mysql', 'host' => 'mysql.internal']);

        $event = $this->onlyEventFor($service);
        $this->assertSame(Audit::Created, $event['action']);
        $this->assertSame('DatabaseService', $event['resource_type']);
        $this->assertSame('shared-mysql', $event['resource_name']);
        $this->assertSame([null, 'mysql.internal'], $event['details']['changes']['host']);
    }

    public function testAnUpdateRecordsOnlyWhatChanged(): void {
        $service = Fixtures::databaseService(['host' => 'old.internal', 'port' => 3306]);

        $service->host = 'new.internal';
        $service->save();

        $event = $this->lastEventFor($service);
        $this->assertSame(Audit::Updated, $event['action']);
        $this->assertSame(['host' => ['old.internal', 'new.internal']], $event['details']['changes']);
    }

    public function testASaveThatChangesNothingIsNotRecorded(): void {
        $service = Fixtures::databaseService();

        $service->save();

        $this->assertCount(1, $this->eventsFor($service), 'only its creation');
    }

    /**
     * A secret says that it changed, never what from or to - not even encrypted.
     */
    public function testASecretIsRecordedAsChangedButNotWhatTo(): void {
        $service = Fixtures::databaseService(['pass' => 'the-old-one']);

        $service->pass = 'the-new-one';
        $service->save();

        $this->assertSame([Audit::Redacted, Audit::Redacted], $this->lastEventFor($service)['details']['changes']['pass']);
        $this->assertStringNotContainsString('the-', $this->everythingRecorded());
    }

    /**
     * What kso writes on every run is not a change anybody made.
     */
    public function testFieldsKsoKeepsUpToDateAreNotRecorded(): void {
        $job = new CronJob();
        $job->find(\CronJobIds::CleanupAuditEvents);
        $before = count($this->eventsFor($job));

        $job->last_run = date('Y-m-d H:i:s');
        $job->last_log = 'ran';
        $job->save();

        $this->assertCount($before, $this->eventsFor($job));
    }

    public function testADeletionRecordsWhatWasThere(): void {
        $service = Fixtures::databaseService(['host' => 'gone.internal']);

        $service->delete();

        $event = $this->lastEventFor($service);
        $this->assertSame(Audit::Deleted, $event['action']);
        $this->assertSame(['gone.internal', null], $event['details']['changes']['host']);
    }

    /**
     * Marking a row deleted is a save underneath, and the deletion gets a row of its own in
     * `deletions`. Neither is a change of its own.
     */
    public function testASoftDeletionIsOneEvent(): void {
        $service = Fixtures::databaseService();

        $service->delete();

        $this->assertSame([Audit::Created, Audit::Deleted], array_column($this->eventsFor($service), 'action'));
        $this->assertSame([], $this->eventsOfType('Deletion'));
    }

    public function testARelationAddedAndRemovedIsOneEventEach(): void {
        $role = $this->aRole();
        $permission = $this->aPermission();

        $role->save($permission);
        $role->deleteRelation($permission);

        $events = array_slice($this->eventsFor($role), 1);
        $this->assertSame([Audit::RelationAdded, Audit::RelationRemoved], array_column($events, 'action'));
        $this->assertSame(['type' => 'RbacPermission', 'id' => (int) $permission->id], $events[0]['details']['relation']);
        $this->assertCount(1, $this->eventsFor($permission), 'only its creation');
    }

    public function testRowsKsoRecordsElsewhereAreNotAudited(): void {
        $deletion = new Deletion();
        $deletion->save();

        $this->assertSame([], $this->eventsOfType('Deletion'));
    }

    /**
     * The change and its row are written together: when one fails, neither is there.
     */
    public function testAFailedWriteLeavesNoChangeBehind(): void {
        $service = Fixtures::databaseService(['host' => 'kept.internal']);

        try {
            Audit::Atomically(function () use ($service) {
                $service->host = 'lost.internal';
                $service->save();
                throw new \RuntimeException('the write after it failed');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('kept.internal', (new DatabaseService())->find($service->id)->host);
        $this->assertCount(1, $this->eventsFor($service));
    }

    /**
     * A queued job acts as whoever set it off.
     */
    public function testWorkCarriedOnElsewhereIsStillTheirs(): void {
        AuditContext::Restore(['user_id' => 4242, 'client_id' => 'webclient', 'ip_address' => '10.0.0.7'], AuditContext::Queue);

        $service = Fixtures::databaseService();

        $event = $this->onlyEventFor($service);
        $this->assertSame(4242, (int) $event['user_id']);
        $this->assertSame(AuditContext::Queue, $event['source']);
        $this->assertSame('10.0.0.7', $event['ip_address']);
    }

    /**
     * A command, run by spark - which answers with a request of its own kind. Under PHPUnit
     * the request is an ordinary one, so the test sets spark's.
     */
    public function testACommandIsTheCommandLine(): void {
        Services::injectMock('request', new CLIRequest(config('App')));
        try {
            $this->assertSame(AuditContext::Cli, $this->onlyEventFor(Fixtures::databaseService())['source']);
        } finally {
            Services::resetSingle('request');
        }
    }

    // <editor-fold desc="Helpers">

    private function aRole(): RbacRole {
        $role = new RbacRole();
        $role->name = 'auditor';
        $role->identifier = 'auditor';
        $role->save();
        return $role;
    }

    private function aPermission(): RbacPermission {
        $permission = new RbacPermission();
        $permission->name = 'audit.read';
        $permission->save();
        return $permission;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function eventsFor($entity): array {
        return $this->decoded($this->db->table('audit_events')
            ->where('resource_type', Audit::TypeOf($entity))
            ->where('resource_id', $entity->id)
            ->orderBy('id')
            ->get()->getResultArray());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function eventsOfType(string $type): array {
        return $this->decoded($this->db->table('audit_events')->where('resource_type', $type)->get()->getResultArray());
    }

    private function onlyEventFor($entity): array {
        $events = $this->eventsFor($entity);
        $this->assertCount(1, $events);
        return $events[0];
    }

    private function lastEventFor($entity): array {
        $events = $this->eventsFor($entity);
        return end($events);
    }

    private function everythingRecorded(): string {
        return json_encode($this->db->table('audit_events')->get()->getResultArray());
    }

    private function decoded(array $rows): array {
        return array_map(function (array $row) {
            $row['details'] = json_decode((string) $row['details'], true) ?? [];
            return $row;
        }, $rows);
    }

    // </editor-fold>

}
