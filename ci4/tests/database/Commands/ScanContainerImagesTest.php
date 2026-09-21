<?php namespace App\Tests\Database\Commands;

use App\Commands\ScanContainerImages;
use App\DatabaseTestCase;

/**
 * The two cron jobs: each records that it ran, on its own row. What is scanned is held in
 * ImageScannerTest.
 */
class ScanContainerImagesTest extends DatabaseTestCase {

    public function setUp(): void {
        parent::setUp();
        putenv('TRIVY_BINARY=' . TESTPATH . '_fakes/trivy');
    }

    public function tearDown(): void {
        putenv('TRIVY_BINARY');
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('theTwoJobs')]
    public function testEachJobRecordsThatItRanOnItsOwnRow(array $params, int $jobId, string $said): void {
        $store = (new \ReflectionClass(\DebugTool\Data::class))->getProperty('store');
        $store->setValue(null, ['status' => null]);
        $before = $this->job($jobId)['last_run'];

        (new ScanContainerImages(service('logger'), service('commands')))->run($params);

        $job = $this->job($jobId);
        $this->assertNotSame($before, $job['last_run']);
        $this->assertStringContainsString($said, (string) $job['last_log']);
    }

    public static function theTwoJobs(): array {
        return [
            'nightly, everything running' => [[], \CronJobIds::ScanContainerImages, 'all running'],
            'every minute, the queue' => [['queued'], \CronJobIds::ScanQueuedContainerImages, 'queued'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function job(int $id): array {
        return $this->db->table('cron_jobs')->where('id', $id)->get()->getRowArray();
    }

}
