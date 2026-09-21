<?php namespace App\Entities;

use App\Core\Entity;

/**
 * The counts of one scan of a tag that succeeded. Kept for a year, for a graph over time;
 * the findings are only kept for the latest scan, in ContainerImageScan.
 *
 * @property int $container_image_id
 * @property ContainerImage $container_image
 * @property string $tag
 * @property string $digest what the tag pointed at when it was scanned - a tag like `develop`
 *                          changes, and a change of count may be a change of image
 * @property int $critical
 * @property int $high
 * @property int $medium
 * @property int $low
 * @property int $unknown
 * @property string $scanned_at
 */
class ContainerImageScanRecord extends Entity {

}
