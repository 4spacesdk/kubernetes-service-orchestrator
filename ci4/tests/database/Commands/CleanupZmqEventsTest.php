<?php namespace App\Tests\Database\Commands;

use App\Commands\CleanupZmqEvents;
use App\DatabaseTestCase;

/**
 * The nightly job that keeps `zmq_events` to a window.
 *
 * Every push event writes a row there, and until this job existed nothing ever removed one:
 * the only code that deleted a row was a deduplication branch that could not be reached -
 * see the note on `Controllers\ZMQ`. An installation therefore held every event it had ever
 * raised, and nothing reads them back.
 *
 * So what is worth holding here is the boundary. A job that deletes too much throws away
 * the log somebody is in the middle of reading; one that deletes too little is the job not
 * running at all.
 */
class CleanupZmqEventsTest extends DatabaseTestCase {

    public function testAnEventOlderThanTheWindowIsRemoved(): void {
        $this->event('old', self::daysAgo(CleanupZmqEvents::RetentionDays + 1));

        $this->runTheJob();

        $this->assertSame([], $this->identifiersLeft());
    }

    /**
     * The other side of the same line. A window that is read as "older than nothing" would
     * empty the table on its first run, and nobody would notice until they went looking for
     * an event from this morning.
     */
    public function testAnEventInsideTheWindowIsKept(): void {
        $this->event('recent', self::daysAgo(CleanupZmqEvents::RetentionDays - 1));
        $this->event('from-today', date('Y-m-d H:i:s'));

        $this->runTheJob();

        $this->assertSame(['recent', 'from-today'], $this->identifiersLeft());
    }

    /**
     * What the run decided, in the job's own log. The cron page is the only place an
     * operator sees this job at all, and a job that says nothing looks like a job that is
     * not running.
     */
    public function testTheJobRecordsHowManyItRemoved(): void {
        $this->event('old-one', self::daysAgo(CleanupZmqEvents::RetentionDays + 1));
        $this->event('old-two', self::daysAgo(CleanupZmqEvents::RetentionDays + 2));
        $this->event('recent', date('Y-m-d H:i:s'));

        $this->assertStringContainsString('removed 2 events older than 30 days', $this->runTheJob());
    }

    /**
     * Nothing to do is the ordinary night, and it still has to record that it ran - the
     * cron page shows when each job last did.
     */
    public function testAQuietNightStillRecordsThatTheJobRan(): void {
        $before = $this->cronJob()['last_run'];

        $this->runTheJob();

        $this->assertNotSame($before, $this->cronJob()['last_run']);
    }

    // <editor-fold desc="Fixtures">

    private function runTheJob(): string {
        $store = (new \ReflectionClass(\DebugTool\Data::class))->getProperty('store');
        $store->setValue(null, ['status' => null]);

        (new CleanupZmqEvents(service('logger'), service('commands')))->run([]);

        return (string) $this->cronJob()['last_log'];
    }

    /**
     * Written through the builder, because `created` is set by the ORM on insert and an
     * entity cannot be given a date in the past.
     */
    private function event(string $identifier, string $created): void {
        $this->db->table('zmq_events')->insert([
            'identifier' => $identifier,
            'event' => 'workspace-created',
            'data' => '{}',
            'created' => $created,
        ]);
    }

    private static function daysAgo(int $days): string {
        return date('Y-m-d H:i:s', strtotime("-{$days} days"));
    }

    /**
     * @return string[]
     */
    private function identifiersLeft(): array {
        return array_column(
            $this->db->table('zmq_events')->orderBy('id', 'asc')->get()->getResultArray(),
            'identifier'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cronJob(): array {
        return $this->db->table('cron_jobs')
            ->where('id', \CronJobIds::CleanupZmqEvents)
            ->get()->getRowArray();
    }

    // </editor-fold>

}
