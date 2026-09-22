<?php namespace App\Libraries\ImageScanning;

use App\Entities\ContainerImage;
use App\Entities\ContainerImageScan;
use App\Entities\ContainerImageScanRecord;
use App\Libraries\Push\ChangeEvent;
use App\Libraries\Push\Events;
use App\Libraries\Push\Publisher;
use App\Models\ContainerImageScanModel;
use DebugTool\Data;

/**
 * Which tags of the customers' images are running, and what Trivy finds in them.
 *
 * Only what runs: a tag no deployment runs is not scanned, and its row goes at the next
 * nightly scan. The status describes what is in the cluster, the same principle as
 * SECURITY-STATUS.md describing main.
 *
 * The exception is a tag asked for by hand - an image to look at before anything deploys it.
 * It is scanned once, when asked, and its row stays for ManualScansKeptFor.
 */
class ImageScanner {

    /** How long the counts of earlier scans are kept, for the graph over time. */
    public const RecordsKeptFor = '1 year';

    /** How long a scan asked for by hand stays when no deployment runs its tag. */
    public const ManualScansKeptFor = '30 days';

    public function __construct(private Trivy $trivy = new Trivy()) {
    }

    /**
     * Every deployment that is running: not a draft, not terminated, not in a paused or
     * terminated workspace, and with a version. One query, so the container image list can
     * count them per image as well.
     *
     * @return list<array{deployment_id: int, version: string, container_image_id: int}>
     */
    public static function runningDeployments(?int $onlyImageId = null): array {
        $builder = db_connect()->table('deployments d')
            ->select('d.id AS deployment_id, d.version, s.container_image_id')
            ->join('deployment_specifications s', 's.id = d.deployment_specification_id')
            ->join('container_images i', 'i.id = s.container_image_id')
            ->join('workspaces w', 'w.id = d.workspace_id AND w.deletion_id IS NULL', 'left')
            ->where('d.deletion_id', null)
            ->whereNotIn('d.status', [\DeploymentStatusTypes::Draft, \DeploymentStatusTypes::Inactive])
            ->where("COALESCE(d.version, '') !=", '')
            ->groupStart()
                ->where('w.id', null)
                ->orGroupStart()
                    ->where('w.is_paused', 0)
                    ->where('w.status !=', \WorkspaceStatusTypes::Inactive)
                ->groupEnd()
            ->groupEnd();
        if ($onlyImageId !== null) {
            $builder->where('s.container_image_id', $onlyImageId);
        }

        return array_map(fn(array $row) => [
            'deployment_id' => (int) $row['deployment_id'],
            'version' => (string) $row['version'],
            'container_image_id' => (int) $row['container_image_id'],
        ], $builder->get()->getResultArray());
    }

    /**
     * The image and tag of every running deployment, see runningDeployments().
     *
     * @return array<string, array{0: ContainerImage, 1: string}> keyed by "imageId:tag"
     */
    public function runningTags(?ContainerImage $only = null): array {
        $images = [];
        $running = [];
        foreach (self::runningDeployments($only?->id !== null ? (int) $only->id : null) as $row) {
            $imageId = $row['container_image_id'];
            if (!isset($images[$imageId])) {
                $images[$imageId] = new ContainerImage();
                $images[$imageId]->find($imageId);
            }
            $running["{$imageId}:{$row['version']}"] = [$images[$imageId], $row['version']];
        }

        return $running;
    }

    /**
     * Put the running tags - of one image, or all of them - in the queue.
     *
     * @return int how many were queued
     */
    public function queue(?ContainerImage $only = null): int {
        $queued = 0;
        foreach ($this->runningTags($only) as [$image, $tag]) {
            $scan = $this->rowFor((int) $image->id, $tag);
            $scan->status = \ContainerImageScanStatuses::Queued;
            $scan->image_reference = self::referenceOf($image, $tag);
            $scan->save();
            $this->announce($scan);
            $queued++;
        }

        return $queued;
    }

    /**
     * Put one tag of an image in the queue, whether a deployment runs it or not.
     */
    public function queueTag(ContainerImage $image, string $tag): ContainerImageScan {
        $scan = $this->rowFor((int) $image->id, $tag);
        $scan->status = \ContainerImageScanStatuses::Queued;
        $scan->image_reference = self::referenceOf($image, $tag);
        $scan->is_manual = true;
        $scan->save();
        $this->announce($scan);

        return $scan;
    }

    /**
     * Scan what is queued, one image at a time. One that fails is recorded as failed, with
     * Trivy's reason, and the rest are scanned all the same.
     *
     * @return array{scanned: int, failed: int}
     */
    public function scanQueued(): array {
        /** @var ContainerImageScan $queued */
        $queued = (new ContainerImageScanModel())
            ->where('status', \ContainerImageScanStatuses::Queued)
            ->find();

        $result = ['scanned' => 0, 'failed' => 0];
        foreach ($queued as $scan) {
            $this->scan($scan) ? $result['scanned']++ : $result['failed']++;
        }

        return $result;
    }

    /**
     * The nightly run: every running tag, and the rows of tags that no longer run removed -
     * except one asked for by hand, until it is older than ManualScansKeptFor. Their records
     * stay, until they are older than RecordsKeptFor.
     *
     * @return array{scanned: int, failed: int, removed: int}
     */
    public function scanAllRunning(): array {
        $running = $this->runningTags();
        $this->queue();

        $removed = 0;
        /** @var ContainerImageScan $all */
        $all = (new ContainerImageScanModel())->find();
        $manualSince = date('Y-m-d H:i:s', strtotime('-' . self::ManualScansKeptFor));
        foreach ($all as $scan) {
            $isKeptManual = $scan->is_manual && ($scan->scanned_at === null || $scan->scanned_at >= $manualSince);
            if (!isset($running["{$scan->container_image_id}:{$scan->tag}"]) && !$isKeptManual) {
                $scan->delete();
                $removed++;
            }
        }

        $this->removeOldRecords();

        return $this->scanQueued() + ['removed' => $removed];
    }

    private function scan(ContainerImageScan $scan): bool {
        $image = new ContainerImage();
        $image->find($scan->container_image_id);
        if (!$image->exists()) {
            $scan->delete();
            return false;
        }

        $scan->status = \ContainerImageScanStatuses::Scanning;
        $scan->save();
        $this->announce($scan);

        try {
            $report = new TrivyReport($this->trivy->scan($scan->image_reference, $image->getPullCredentialsDockerConfig()));

            foreach ($report->counts as $severity => $count) {
                $scan->{$severity} = $count;
            }
            $scan->findings = json_encode($report->findings);
            $scan->digest = $report->digest;
            $scan->operating_system = $report->operatingSystem;
            $scan->targets = $report->targets;
            $scan->error = null;
            $scan->status = \ContainerImageScanStatuses::Scanned;
            $ok = true;
        } catch (\Throwable $e) {
            Data::debug('Scan of', $scan->image_reference, 'failed:', $e->getMessage());
            $scan->error = $e->getMessage();
            $scan->status = \ContainerImageScanStatuses::Failed;
            $ok = false;
        }

        $scan->scanned_at = date('Y-m-d H:i:s');
        $scan->save();
        if ($ok) {
            $this->record($scan);
        }
        $this->announce($scan);

        return $ok;
    }

    /**
     * Tells an open vulnerabilities dialog that a scan moved on, so it shows the status and
     * the result without a reload. The counts, not the findings: the dialog reads those itself.
     *
     * Protected so a test can see that it was reached; the socket records nothing.
     */
    protected function announce(ContainerImageScan $scan): void {
        $row = $scan->toArray();
        unset($row['findings']);
        Publisher::getInstance()->send(
            Events::ContainerImage_Scans_Changed((int) $scan->container_image_id),
            (new ChangeEvent(null, $row))->toArray()
        );
    }

    /**
     * Only a scan that succeeded: a failed one found nothing, which is not the same as nothing
     * being there.
     */
    private function record(ContainerImageScan $scan): void {
        $record = new ContainerImageScanRecord();
        foreach (['container_image_id', 'tag', 'digest', 'critical', 'high', 'medium', 'low', 'unknown', 'scanned_at'] as $field) {
            $record->{$field} = $scan->{$field};
        }
        $record->save();
    }

    /**
     * Records older than RecordsKeptFor, and those of images that are gone.
     */
    private function removeOldRecords(): void {
        $db = db_connect();
        $db->table('container_image_scan_records')
            ->where('scanned_at <', date('Y-m-d H:i:s', strtotime('-' . self::RecordsKeptFor)))
            ->delete();
        $db->table('container_image_scan_records')
            ->whereNotIn('container_image_id', $db->table('container_images')->select('id'))
            ->delete();
    }

    private function rowFor(int $imageId, string $tag): ContainerImageScan {
        /** @var ContainerImageScan $scan */
        $scan = (new ContainerImageScanModel())
            ->where('container_image_id', $imageId)
            ->where('tag', $tag)
            ->find();
        if (!$scan->exists()) {
            $scan = new ContainerImageScan();
            $scan->container_image_id = $imageId;
            $scan->tag = $tag;
        }

        return $scan;
    }

    public static function referenceOf(ContainerImage $image, string $tag): string {
        return "{$image->url}:{$tag}";
    }

}
