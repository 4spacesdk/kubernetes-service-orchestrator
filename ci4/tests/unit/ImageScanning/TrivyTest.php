<?php namespace App\Tests\Unit\ImageScanning;

use App\Libraries\ImageScanning\Trivy;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Running Trivy, against the stand-in in tests/_fakes/trivy.
 */
class TrivyTest extends CIUnitTestCase {

    private string $log;

    protected function setUp(): void {
        parent::setUp();
        $this->log = tempnam(sys_get_temp_dir(), 'fake-trivy-log-');
        putenv('FAKE_TRIVY_REPORT=' . TESTPATH . '_fakes/trivy-report.json');
        putenv('FAKE_TRIVY_LOG=' . $this->log);
        putenv('FAKE_TRIVY_FAIL');
    }

    protected function tearDown(): void {
        @unlink($this->log);
        putenv('FAKE_TRIVY_REPORT');
        putenv('FAKE_TRIVY_LOG');
        putenv('FAKE_TRIVY_FAIL');
        parent::tearDown();
    }

    private function trivy(): Trivy {
        return new Trivy(TESTPATH . '_fakes/trivy', sys_get_temp_dir() . '/fake-trivy-cache');
    }

    public function testTheReportIsHandedBack(): void {
        $report = $this->trivy()->scan('registry.example.org/tenant/api:1.2.3', null);

        $this->assertSame('registry.example.org/tenant/api:1.2.3', $report['ArtifactName']);
        $this->assertSame("registry.example.org/tenant/api:1.2.3\tnone\n", file_get_contents($this->log));
    }

    /**
     * The registry credentials reach Trivy as a Docker config - and are not left on disk.
     */
    public function testCredentialsAreHandedOverAndRemovedAfterwards(): void {
        $before = glob(sys_get_temp_dir() . '/kso-trivy-*') ?: [];

        $this->trivy()->scan('registry.example.org/tenant/api:1.2.3', '{"auths":{"registry.example.org":{"auth":"x"}}}');

        $this->assertStringContainsString('"registry.example.org"', (string) file_get_contents($this->log));
        $this->assertSame($before, glob(sys_get_temp_dir() . '/kso-trivy-*') ?: [], 'the working directory with the password is gone');
    }

    public function testARefusalIsThrownWithTrivysOwnReason(): void {
        putenv('FAKE_TRIVY_FAIL=private');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/UNAUTHORIZED: authentication required/');

        $this->trivy()->scan('registry.example.org/private/api:1', null);
    }

    /**
     * The reference is an argument, not part of a command line: what a customer named their
     * tag cannot reach a shell.
     */
    public function testNothingInTheReferenceReachesAShell(): void {
        $marker = sys_get_temp_dir() . '/kso-trivy-shell-' . bin2hex(random_bytes(4));

        $this->trivy()->scan("registry.example.org/tenant/api:1;touch {$marker}", null);

        $this->assertFileDoesNotExist($marker);
    }

}
