<?php namespace App\Tests\Unit\ImageScanning;

use App\Libraries\ImageScanning\TrivyReport;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Trivy's report cut to what kso keeps.
 */
class TrivyReportTest extends CIUnitTestCase {

    private function report(): TrivyReport {
        return new TrivyReport(json_decode((string) file_get_contents(TESTPATH . '_fakes/trivy-report.json'), true));
    }

    public function testEachSeverityIsCounted(): void {
        $this->assertSame(
            ['critical' => 1, 'high' => 1, 'medium' => 1, 'low' => 1, 'unknown' => 1],
            $this->report()->counts
        );
    }

    /**
     * Trivy lists a package once per path it reached the image by; a finding is counted once.
     */
    public function testAFindingReachedByTwoPathsIsOneFinding(): void {
        $ids = array_column($this->report()->findings, 'id');

        $this->assertSame(1, count(array_keys($ids, 'CVE-2026-0003')));
    }

    public function testTheMostSevereComeFirst(): void {
        $this->assertSame(
            ['critical', 'high', 'medium', 'low', 'unknown'],
            array_column($this->report()->findings, 'severity')
        );
    }

    public function testAFindingKeepsWhatTheListShows(): void {
        $this->assertSame([
            'id' => 'CVE-2026-0003',
            'link' => 'https://avd.aquasec.com/nvd/cve-2026-0003',
            'package' => 'codeigniter4/framework',
            'installed' => 'v4.4.5',
            'fixed' => '4.7.4',
            'severity' => 'critical',
            'title' => 'CodeIgniter: SQL injection',
        ], $this->report()->findings[0]);
    }

    public function testTheDigestIsWhatWasScanned(): void {
        $this->assertStringContainsString('@sha256:1111', (string) $this->report()->digest);
    }

    public function testAnImageWithNothingFoundIsAllZeros(): void {
        $report = new TrivyReport(['Results' => [['Target' => 'x']]]);

        $this->assertSame([], $report->findings);
        $this->assertSame(0, array_sum($report->counts));
        $this->assertNull($report->digest);
        $this->assertSame(1, $report->targets, 'something was read, and nothing was found in it');
    }

    public function testWhatTrivyRecognisedIsKept(): void {
        $report = $this->report();

        $this->assertSame('alpine 3.20.3', $report->operatingSystem);
        $this->assertSame(3, $report->targets);
    }

    /**
     * An image Trivy could read nothing in - no OS it knows, no lock files - is not a clean
     * one, and has to be told apart from it.
     */
    public function testAnImageTrivyCouldReadNothingInHasNoTargets(): void {
        $report = new TrivyReport(['Metadata' => []]);

        $this->assertSame(0, $report->targets);
        $this->assertNull($report->operatingSystem);
    }

}
