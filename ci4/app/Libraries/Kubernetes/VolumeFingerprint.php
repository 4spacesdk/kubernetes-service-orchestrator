<?php namespace App\Libraries\Kubernetes;

/**
 * The parts of a volume that end up in the claim and the disk, in a form two sets can be
 * compared by.
 *
 * A PersistentVolumeClaim's spec cannot be changed once it exists, and a disk's source
 * cannot either - the api server answers `422` and the deploy stops at that step, with the
 * workspace still on the old disk. So an edit to any of these is refused when it is saved
 * rather than on the next deploy.
 *
 * Mount path, sub path and reclaim policy are not here: the first two belong to the pod,
 * and a reclaim policy can be changed on a live volume.
 */
class VolumeFingerprint {

    private const Fields = [
        'type', 'capacity', 'volume_mode', 'storage_class',
        'nfs_server', 'nfs_path', 'csi_driver', 'csi_volume_handle',
    ];

    /**
     * @param iterable<object> $rows Volume rows, or the objects a request carries - both
     *     name their fields the way the columns are named.
     * @return string[] Sorted, so the same volumes in another order are the same set.
     */
    public static function of(iterable $rows): array {
        $fingerprints = [];
        foreach ($rows as $row) {
            $values = [];
            foreach (self::Fields as $field) {
                $values[] = (string) ($row->{$field} ?? '');
            }
            $fingerprints[] = implode('|', $values);
        }
        sort($fingerprints);

        return $fingerprints;
    }

}
