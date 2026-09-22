<?php namespace App\Entities;

use App\Core\Entity;

/**
 * What Trivy found in one tag of a container image - one a deployment runs, or one asked for by
 * hand. One row per image and tag, overwritten by the next scan.
 *
 * @property int $container_image_id
 * @property ContainerImage $container_image
 * @property string $tag
 * @property string $image_reference the image as it was scanned: registry/repository:tag
 * @property string $status queued, scanning, scanned or failed - see ContainerImageScanStatuses
 * @property string $error why the last scan failed, in Trivy's words
 * @property string $digest
 * @property string $operating_system what Trivy recognised the image as, e.g. "alpine 3.20.3"; empty
 *                                    when it recognised none
 * @property int $targets how many parts of the image Trivy could read packages from - an OS, a
 *                        composer.lock, a node_modules. Nothing found in zero is not the same as
 *                        nothing found.
 * @property int $critical
 * @property int $high
 * @property int $medium
 * @property int $low
 * @property int $unknown
 * @property string $findings JSON: a list of {id, link, package, installed, fixed, severity, title}
 * @property string $scanned_at
 * @property bool $is_manual asked for by hand, for a tag that need not run - kept for
 *                           ImageScanner::ManualScansKeptFor rather than removed at the next
 *                           nightly scan
 */
class ContainerImageScan extends Entity {

}
