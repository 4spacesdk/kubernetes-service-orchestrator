<?php namespace App\Tests\Database\ImageScanning;

use App\DatabaseTestCase;
use App\Entities\ContainerImage;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\ImageScanning\ImageScanner;
use App\Libraries\ImageScanning\Trivy;

/**
 * Which images are scanned, what is kept, and what a failure costs - against the stand-in for
 * Trivy in tests/_fakes/trivy.
 */
class ImageScannerTest extends DatabaseTestCase {

    private string $log;

    public function setUp(): void {
        parent::setUp();
        $this->log = tempnam(sys_get_temp_dir(), 'fake-trivy-log-');
        putenv('FAKE_TRIVY_REPORT=' . TESTPATH . '_fakes/trivy-report.json');
        putenv('FAKE_TRIVY_LOG=' . $this->log);
        putenv('FAKE_TRIVY_FAIL');
    }

    public function tearDown(): void {
        @unlink($this->log);
        putenv('FAKE_TRIVY_REPORT');
        putenv('FAKE_TRIVY_LOG');
        putenv('FAKE_TRIVY_FAIL');
        parent::tearDown();
    }

    public function testARunningTagIsScannedAndWhatWasFoundIsKept(): void {
        $image = $this->anImage('registry.example.org/tenant/api');
        $this->aDeploymentRunning($image, '1.2.3');

        $result = $this->scanner()->scanAllRunning();

        $this->assertSame(['scanned' => 1, 'failed' => 0, 'removed' => 0], $result);
        $row = $this->onlyScan();
        $this->assertSame('scanned', $row['status']);
        $this->assertSame('registry.example.org/tenant/api:1.2.3', $row['image_reference']);
        $this->assertSame(['1', '1', '1', '1', '1'], [$row['critical'], $row['high'], $row['medium'], $row['low'], $row['unknown']]);
        $this->assertCount(5, json_decode($row['findings'], true));
        $this->assertNotNull($row['scanned_at']);
    }

    /**
     * The registry's pull account is what Trivy pulls with.
     */
    public function testTheImageIsPulledWithItsRegistrysCredentials(): void {
        $registry = Fixtures::containerRegistry(['pull_username' => 'puller', 'pull_password' => 'pull-secret']);
        $image = $this->anImage('registry.example.org/tenant/api', ['container_registry_id' => $registry->id]);
        $this->aDeploymentRunning($image, '1.2.3');

        $this->scanner()->scanAllRunning();

        $this->assertStringContainsString(base64_encode('puller:pull-secret'), (string) file_get_contents($this->log));
    }

    /**
     * A public image is scanned without credentials.
     */
    public function testAnImageWithoutARegistryIsScannedAnonymously(): void {
        $this->aDeploymentRunning($this->anImage('docker.io/library/nginx'), '1.27');

        $this->scanner()->scanAllRunning();

        $this->assertSame("docker.io/library/nginx:1.27\tnone\n", file_get_contents($this->log));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deploymentsThatAreNotRunning')]
    public function testOnlyWhatRunsIsScanned(string $deploymentStatus, string $workspaceStatus, bool $paused): void {
        $workspace = Fixtures::workspace(['status' => $workspaceStatus, 'is_paused' => $paused]);
        $this->aDeploymentRunning($this->anImage('registry.example.org/tenant/api'), '1.2.3', [
            'status' => $deploymentStatus,
            'workspace_id' => $workspace->id,
        ]);

        $this->assertSame(['scanned' => 0, 'failed' => 0, 'removed' => 0], $this->scanner()->scanAllRunning());
        $this->assertSame('', (string) file_get_contents($this->log));
    }

    public static function deploymentsThatAreNotRunning(): array {
        return [
            'a draft' => [\DeploymentStatusTypes::Draft, \WorkspaceStatusTypes::Synced, false],
            'terminated' => [\DeploymentStatusTypes::Inactive, \WorkspaceStatusTypes::Synced, false],
            'in a paused workspace' => [\DeploymentStatusTypes::Synced, \WorkspaceStatusTypes::Paused, true],
            'in a terminated workspace' => [\DeploymentStatusTypes::Synced, \WorkspaceStatusTypes::Inactive, false],
        ];
    }

    /**
     * Two deployments on the same tag are one scan.
     */
    public function testATagRunTwiceIsScannedOnce(): void {
        $image = $this->anImage('registry.example.org/tenant/api');
        $this->aDeploymentRunning($image, '1.2.3');
        $this->aDeploymentRunning($image, '1.2.3', ['name' => 'another']);

        $this->assertSame(1, $this->scanner()->scanAllRunning()['scanned']);
    }

    /**
     * An image that cannot be scanned is recorded with Trivy's reason, and the rest are
     * scanned all the same.
     */
    public function testAFailureIsRecordedAndDoesNotStopTheRest(): void {
        putenv('FAKE_TRIVY_FAIL=private');
        $this->aDeploymentRunning($this->anImage('registry.example.org/private/api'), '1');
        $this->aDeploymentRunning($this->anImage('registry.example.org/tenant/api'), '2');

        $this->assertSame(['scanned' => 1, 'failed' => 1, 'removed' => 0], $this->scanner()->scanAllRunning());

        $failed = $this->db->table('container_image_scans')->where('status', 'failed')->get()->getRowArray();
        $this->assertStringContainsString('UNAUTHORIZED', $failed['error']);
    }

    /**
     * A tag no deployment runs any more is dropped at the next nightly scan.
     */
    public function testATagThatStoppedRunningIsRemoved(): void {
        $image = $this->anImage('registry.example.org/tenant/api');
        $deployment = $this->aDeploymentRunning($image, '1.2.3');
        $this->scanner()->scanAllRunning();

        $this->db->table('deployments')->where('id', $deployment->id)->update(['version' => '1.2.4']);
        $result = $this->scanner()->scanAllRunning();

        $this->assertSame(1, $result['removed']);
        $this->assertSame('1.2.4', $this->onlyScan()['tag']);
    }

    /**
     * "Scan now" queues the image's running tags; the job every minute scans only what is
     * queued, not everything.
     */
    public function testQueuingOneImageScansOnlyThatImage(): void {
        $wanted = $this->anImage('registry.example.org/tenant/api');
        $this->aDeploymentRunning($wanted, '1.2.3');
        $this->aDeploymentRunning($this->anImage('registry.example.org/tenant/web'), '4.5.6');

        $this->assertSame(1, $this->scanner()->queue($wanted));
        $this->assertSame(['scanned' => 1, 'failed' => 0], $this->scanner()->scanQueued());

        $this->assertSame("registry.example.org/tenant/api:1.2.3\tnone\n", file_get_contents($this->log));
    }

    public function testNothingQueuedIsNothingDone(): void {
        $this->aDeploymentRunning($this->anImage('registry.example.org/tenant/api'), '1.2.3');

        $this->assertSame(['scanned' => 0, 'failed' => 0], $this->scanner()->scanQueued());
    }

    /**
     * Each scan that succeeds adds to the history; the latest scan is overwritten.
     */
    public function testEveryScanThatSucceedsIsRecorded(): void {
        $image = $this->anImage('registry.example.org/tenant/api');
        $this->aDeploymentRunning($image, '1.2.3');

        $this->scanner()->scanAllRunning();
        $this->scanner()->queue($image);
        $this->scanner()->scanQueued();

        $records = $this->db->table('container_image_scan_records')->get()->getResultArray();
        $this->assertCount(2, $records);
        $this->assertSame([(string) $image->id, '1.2.3', '1', '1'], [$records[0]['container_image_id'], $records[0]['tag'], $records[0]['critical'], $records[0]['high']]);
        $this->assertNotNull($records[0]['scanned_at']);
    }

    /**
     * A failed scan found nothing, which is not the same as nothing being there.
     */
    public function testAFailedScanIsNotRecorded(): void {
        putenv('FAKE_TRIVY_FAIL=private');
        $this->aDeploymentRunning($this->anImage('registry.example.org/private/api'), '1');

        $this->scanner()->scanAllRunning();

        $this->assertSame(0, $this->db->table('container_image_scan_records')->countAllResults());
    }

    /**
     * The history of a tag outlives the tag, until it is a year old.
     */
    public function testRecordsAreKeptForAYearAndOutliveTheirTag(): void {
        $image = $this->anImage('registry.example.org/tenant/api');
        $old = Fixtures::containerImageScanRecord(['container_image_id' => $image->id, 'tag' => '0.9', 'scanned_at' => date('Y-m-d H:i:s', strtotime('-13 months'))]);
        $recent = Fixtures::containerImageScanRecord(['container_image_id' => $image->id, 'tag' => '1.0', 'scanned_at' => date('Y-m-d H:i:s', strtotime('-11 months'))]);

        $this->scanner()->scanAllRunning();

        $ids = array_column($this->db->table('container_image_scan_records')->get()->getResultArray(), 'id');
        $this->assertSame([(string) $recent->id], $ids);
        $this->assertNotContains((string) $old->id, $ids);
    }

    public function testTheRecordsOfADeletedImageGo(): void {
        $image = $this->anImage('registry.example.org/tenant/api');
        Fixtures::containerImageScanRecord(['container_image_id' => $image->id]);
        $this->db->table('container_images')->where('id', $image->id)->delete();

        $this->scanner()->scanAllRunning();

        $this->assertSame(0, $this->db->table('container_image_scan_records')->countAllResults());
    }

    /**
     * An open dialog follows the scan through its statuses, and gets the counts at the end.
     */
    public function testEachStepOfAScanIsAnnounced(): void {
        $image = $this->anImage('registry.example.org/tenant/api');
        $this->aDeploymentRunning($image, '1.2.3');
        $scanner = $this->scanner();

        $scanner->queue($image);
        $scanner->scanQueued();

        $this->assertSame([
            [(int) $image->id, 'queued', 0],
            [(int) $image->id, 'scanning', 0],
            [(int) $image->id, 'scanned', 1],
        ], array_map(fn ($scan) => [(int) $scan->container_image_id, $scan->status, (int) $scan->critical], $scanner->announced));
    }

    // <editor-fold desc="Fixtures">

    private function scanner(): ImageScanner {
        return new class(new Trivy(TESTPATH . '_fakes/trivy', sys_get_temp_dir() . '/fake-trivy-cache')) extends ImageScanner {
            /** @var list<\App\Entities\ContainerImageScan> copies, as each was announced */
            public array $announced = [];

            protected function announce(\App\Entities\ContainerImageScan $scan): void {
                $this->announced[] = clone $scan;
            }
        };
    }

    private function anImage(string $url, array $overrides = []): ContainerImage {
        return Fixtures::containerImage(array_merge(['url' => $url], $overrides));
    }

    private function aDeploymentRunning(ContainerImage $image, string $tag, array $overrides = []): Deployment {
        $spec = Fixtures::deploymentSpecification(['container_image_id' => $image->id]);
        $workspace = Fixtures::workspace(['status' => \WorkspaceStatusTypes::Synced]);

        return Fixtures::deployment(array_merge([
            'deployment_specification_id' => $spec->id,
            'workspace_id' => $workspace->id,
            'status' => \DeploymentStatusTypes::Synced,
            'version' => $tag,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function onlyScan(): array {
        $rows = $this->db->table('container_image_scans')->get()->getResultArray();
        $this->assertCount(1, $rows);

        return $rows[0];
    }

    // </editor-fold>

}
