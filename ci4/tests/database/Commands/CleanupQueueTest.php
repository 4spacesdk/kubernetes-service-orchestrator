<?php namespace App\Tests\Database\Commands;

use App\Commands\CleanupQueue;
use App\DatabaseTestCase;
use App\Libraries\Push\EventHandlers;
use CodeIgniter\Queue\Enums\Status;

/**
 * The hourly job that takes care of what the job queue leaves behind: a job whose worker died,
 * and failed jobs nobody removes.
 */
class CleanupQueueTest extends DatabaseTestCase {

    public function testAJobReservedLongerThanAnyJobTakesIsMovedToTheFailedJobs(): void {
        $id = $this->aJob(Status::RESERVED, minutesSinceItChanged: CleanupQueue::StaleAfterMinutes + 5);

        $this->runTheJob();

        $this->assertSame(0, $this->db->table('queue_jobs')->where('id', $id)->countAllResults());
        $failed = $this->db->table('queue_jobs_failed')->get()->getResultArray();
        $this->assertCount(1, $failed);
        $this->assertStringContainsString('The worker stopped while running this job', $failed[0]['exception']);
    }

    /**
     * Reserved a moment ago is a job being run right now.
     */
    public function testAJobReservedJustNowIsLeftToItsWorker(): void {
        $id = $this->aJob(Status::RESERVED, minutesSinceItChanged: 1);

        $this->runTheJob();

        $this->assertSame(1, $this->db->table('queue_jobs')->where('id', $id)->countAllResults());
    }

    /**
     * A job waiting for a worker is not stuck, however long it has waited - a worker that was
     * down for a while comes back to a backlog, and that is what the queue is for.
     */
    public function testAJobThatHasWaitedLongButWasNeverTakenIsLeftInTheQueue(): void {
        $id = $this->aJob(Status::PENDING, minutesSinceItChanged: 600);

        $this->runTheJob();

        $this->assertSame(1, $this->db->table('queue_jobs')->where('id', $id)->countAllResults());
    }

    /**
     * What the rest rests on: when the queue reserves a job, MySQL writes the time - so a job
     * that waited a long time and was taken just now is not taken for a stuck one.
     */
    public function testReservingAJobStampsItWithTheTimeItWasTaken(): void {
        $id = $this->aJob(Status::PENDING, minutesSinceItChanged: 600);

        $taken = service('queue')->pop(EventHandlers::Queue, ['default']);
        $this->assertSame($id, (int) $taken->id);

        $this->runTheJob();

        $this->assertSame(1, $this->db->table('queue_jobs')->where('id', $id)->countAllResults(), 'a job taken just now was failed');
    }

    public function testAFailedJobIsKeptForAMonthAndThenRemoved(): void {
        $this->aFailedJob(daysAgo: CleanupQueue::RetentionDays + 1);
        $recent = $this->aFailedJob(daysAgo: CleanupQueue::RetentionDays - 1);

        $this->runTheJob();

        $this->assertSame([$recent], array_map('intval', array_column(
            $this->db->table('queue_jobs_failed')->select('id')->get()->getResultArray(), 'id'
        )));
    }

    public function testTheJobRecordsWhatItDid(): void {
        $this->aJob(Status::RESERVED, minutesSinceItChanged: CleanupQueue::StaleAfterMinutes + 5);

        $log = $this->runTheJob();

        $this->assertStringContainsString('failed 1 jobs reserved for more than 60 minutes', $log);
    }

    // <editor-fold desc="Fixtures">

    private function runTheJob(): string {
        $store = (new \ReflectionClass(\DebugTool\Data::class))->getProperty('store');
        $store->setValue(null, ['status' => null]);

        (new CleanupQueue(service('logger'), service('commands')))->run([]);

        return (string) $this->db->table('cron_jobs')->where('id', \CronJobIds::CleanupQueue)->get()->getRowArray()['last_log'];
    }

    private function aJob(Status $status, int $minutesSinceItChanged): int {
        $this->db->table('queue_jobs')->insert([
            'queue' => EventHandlers::Queue,
            'payload' => json_encode(['job' => EventHandlers::Job, 'data' => ['event' => 'events.workspace.created', 'data' => []]]),
            'priority' => 'default',
            'status' => $status->value,
            'attempts' => 0,
            'available_at' => time(),
            'created_at' => time(),
        ]);
        $id = (int) $this->db->insertID();
        // Set after the insert, as MySQL would have: it writes the column on every change.
        $this->db->query('UPDATE queue_jobs SET changed_at = NOW() - INTERVAL ? MINUTE WHERE id = ?', [$minutesSinceItChanged, $id]);

        return $id;
    }

    private function aFailedJob(int $daysAgo): int {
        $this->db->table('queue_jobs_failed')->insert([
            'connection' => 'database',
            'queue' => EventHandlers::Queue,
            'payload' => '{}',
            'priority' => 'default',
            'exception' => 'Exception: 0 - something',
            'failed_at' => strtotime("-{$daysAgo} days"),
        ]);

        return (int) $this->db->insertID();
    }

    // </editor-fold>

}
