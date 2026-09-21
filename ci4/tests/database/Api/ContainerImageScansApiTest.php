<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;

/**
 * Asking for a scan, and reading what was found.
 */
class ContainerImageScansApiTest extends ControllerTestCase {

    public function testScanNowQueuesTheImagesRunningTags(): void {
        $image = Fixtures::containerImage(['url' => 'registry.example.org/tenant/api']);
        $spec = Fixtures::deploymentSpecification(['container_image_id' => $image->id]);
        $workspace = Fixtures::workspace(['status' => \WorkspaceStatusTypes::Active]);
        Fixtures::deployment(['deployment_specification_id' => $spec->id, 'workspace_id' => $workspace->id,
            'status' => \DeploymentStatusTypes::Active, 'version' => '1.2.3']);

        $body = $this->decode($this->signedIn()->put("container-images/{$image->id}/scan"));

        $this->assertSame(1, $body['resource']['queued']);
        $row = $this->db->table('container_image_scans')->get()->getRowArray();
        $this->assertSame(['queued', '1.2.3'], [$row['status'], $row['tag']]);
    }

    public function testAnImageNothingRunsQueuesNothing(): void {
        $image = Fixtures::containerImage();

        $this->assertSame(0, $this->decode($this->signedIn()->put("container-images/{$image->id}/scan"))['resource']['queued']);
    }

    public function testAnUnknownImageIsRefused(): void {
        $response = $this->signedIn()->put('container-images/999999/scan');

        $this->assertSame(400, $response->response()->getStatusCode());
    }

    public function testTheScansCanBeReadByImage(): void {
        $image = Fixtures::containerImage();
        $this->db->table('container_image_scans')->insert([
            'container_image_id' => $image->id, 'tag' => '1.2.3', 'status' => 'scanned', 'critical' => 2,
            'findings' => json_encode([['id' => 'CVE-1', 'severity' => 'critical']]),
        ]);

        $body = $this->decode($this->signedIn()->get("container_image_scans?filter=container_image_id:{$image->id}"));

        $this->assertSame(1, count($body['resources']));
        $this->assertSame(2, (int) $body['resources'][0]['critical']);
    }

    /**
     * A list carries the counts; the findings - hundreds, for some images - only come with a
     * read of the one scan.
     */
    public function testTheFindingsComeWithOneScanNotWithTheList(): void {
        $scan = Fixtures::containerImageScan();

        $list = $this->decode($this->signedIn()->get('container_image_scans'))['resources'][0];
        $one = $this->decode($this->signedIn()->get("container_image_scans/{$scan->id}"))['resource'];

        $this->assertArrayNotHasKey('findings', array_filter($list, fn ($v) => $v !== null));
        $this->assertSame(1, (int) $list['high']);
        $this->assertSame('CVE-0000-0001', json_decode($one['findings'], true)[0]['id']);
    }

    /**
     * The scanner writes the rows; the API does not.
     */
    public function testScansCannotBeWrittenThroughTheApi(): void {
        try {
            $response = $this->withBodyFormat('json')->signedIn()->post('container_image_scans', ['container_image_id' => 1, 'tag' => 'x']);
            $this->assertNotSame(200, $response->response()->getStatusCode());
        } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
            // No such route at all, which is the point.
        }

        $this->assertSame(0, $this->db->table('container_image_scans')->countAllResults());
    }

    /**
     * The graph over time reads the records of one tag, oldest first.
     */
    public function testTheRecordsOfATagCanBeReadInOrder(): void {
        $image = Fixtures::containerImage();
        Fixtures::containerImageScanRecord(['container_image_id' => $image->id, 'critical' => 3, 'scanned_at' => '2026-09-02 02:30:00']);
        Fixtures::containerImageScanRecord(['container_image_id' => $image->id, 'critical' => 5, 'scanned_at' => '2026-09-01 02:30:00']);
        Fixtures::containerImageScanRecord(['container_image_id' => $image->id, 'tag' => 'other']);

        $body = $this->decode($this->signedIn()->get("container_image_scan_records?filter=container_image_id:{$image->id},tag:1.0.0&ordering=scanned_at"));

        $this->assertSame([5, 3], array_map(fn ($r) => (int) $r['critical'], $body['resources']));
    }

    public function testRecordsCannotBeWrittenThroughTheApi(): void {
        try {
            $this->withBodyFormat('json')->signedIn()->post('container_image_scan_records', ['container_image_id' => 1, 'tag' => 'x']);
        } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
            // No such route at all, which is the point.
        }

        $this->assertSame(0, $this->db->table('container_image_scan_records')->countAllResults());
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

}
