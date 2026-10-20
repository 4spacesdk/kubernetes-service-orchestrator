<?php namespace App\Libraries\Kubernetes;

use App\Libraries\RequestField;

/**
 * The parts of a volume that end up in the claim and the disk, in a form two sets can be
 * compared by.
 *
 * A PersistentVolumeClaim's spec cannot be changed once it exists, and a disk's source
 * cannot either - the api server answers `422` and the deploy stops at that step, with the
 * workspace still on the old disk. So an edit to any of these is refused when it is saved
 * rather than on the next deploy.
 *
 * The two are told apart. The claim's part is fixed as soon as the claim is there. The disk's
 * source is only fixed while the claim is bound to kso's own disk: one that is not - unused,
 * or a claim bound to a disk its storage class made - is made again by the next deploy.
 *
 * Mount path, sub path and reclaim policy are not here: the first two belong to the pod,
 * and a reclaim policy can be changed on a live volume.
 */
class VolumeFingerprint {

    private const ClaimFields = [
        'capacity', 'volume_mode', 'storage_class',
    ];

    private const SourceFields = [
        'type', 'nfs_server', 'nfs_path', 'csi_driver', 'csi_volume_handle', 'csi_volume_attributes', 'mount_options',
    ];

    /**
     * @param iterable<object> $rows Volume rows, or the objects a request carries - both
     *     name their fields the way the columns are named.
     * @return string[] Sorted, so the same volumes in another order are the same set.
     */
    public static function of(iterable $rows): array {
        return self::fingerprints($rows, [...self::ClaimFields, ...self::SourceFields]);
    }

    /**
     * What ends up in the claim.
     *
     * @param iterable<object> $rows
     * @return string[]
     */
    public static function claim(iterable $rows): array {
        return self::fingerprints($rows, self::ClaimFields);
    }

    /**
     * @param iterable<object> $rows
     * @param string[] $fields
     * @return string[]
     */
    private static function fingerprints(iterable $rows, array $fields): array {
        $fingerprints = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($fields as $field) {
                // Rows name their fields as the columns are named; a request may use either
                // spelling, so it is read the same way the endpoints read it.
                $values[] = (string) RequestField::read($row, $field, '');
            }
            $fingerprints[] = implode('|', $values);
        }
        sort($fingerprints);

        return $fingerprints;
    }

}
