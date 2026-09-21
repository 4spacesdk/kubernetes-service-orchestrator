<?php namespace App\Tests\Database\Commands;

use App\Commands\CleanupSignInAttempts;
use App\DatabaseTestCase;

/**
 * The nightly job that keeps `sign_in_attempts` to a window.
 *
 * Failed attempts write rows too, so the table grows as fast as someone can try made-up
 * usernames. What is worth holding is the boundary: too much, and the log somebody is
 * reading is gone; too little, and the job is not running at all.
 */
class CleanupSignInAttemptsTest extends DatabaseTestCase {

    public function testAnAttemptOlderThanTheWindowIsRemoved(): void {
        $this->attempt('old', self::daysAgo(CleanupSignInAttempts::RetentionDays + 1));

        $this->runTheJob();

        $this->assertSame([], $this->usernamesLeft());
    }

    public function testAnAttemptInsideTheWindowIsKept(): void {
        $this->attempt('recent', self::daysAgo(CleanupSignInAttempts::RetentionDays - 1));
        $this->attempt('from-today', date('Y-m-d H:i:s'));

        $this->runTheJob();

        $this->assertSame(['recent', 'from-today'], $this->usernamesLeft());
    }

    /**
     * The cron page is the only place an operator sees this job, so it says what it did.
     */
    public function testTheJobRecordsHowManyItRemovedAndThatItRan(): void {
        $before = $this->cronJob()['last_run'];
        $this->attempt('old-one', self::daysAgo(CleanupSignInAttempts::RetentionDays + 1));
        $this->attempt('old-two', self::daysAgo(CleanupSignInAttempts::RetentionDays + 2));

        $log = $this->runTheJob();

        $this->assertStringContainsString('removed 2 sign-in attempts older than 90 days', $log);
        $this->assertNotSame($before, $this->cronJob()['last_run']);
    }

    // <editor-fold desc="Fixtures">

    private function runTheJob(): string {
        $store = (new \ReflectionClass(\DebugTool\Data::class))->getProperty('store');
        $store->setValue(null, ['status' => null]);

        (new CleanupSignInAttempts(service('logger'), service('commands')))->run([]);

        return (string) $this->cronJob()['last_log'];
    }

    private function attempt(string $username, string $created): void {
        $this->db->table('sign_in_attempts')->insert([
            'username' => $username,
            'step' => 'password',
            'succeeded' => 0,
            'refused' => 0,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'test',
            'created' => $created,
        ]);
    }

    private static function daysAgo(int $days): string {
        return date('Y-m-d H:i:s', strtotime("-{$days} days"));
    }

    /**
     * @return string[]
     */
    private function usernamesLeft(): array {
        return array_column(
            $this->db->table('sign_in_attempts')->orderBy('id', 'asc')->get()->getResultArray(),
            'username'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cronJob(): array {
        return $this->db->table('cron_jobs')
            ->where('id', \CronJobIds::CleanupSignInAttempts)
            ->get()->getRowArray();
    }

    // </editor-fold>

}
