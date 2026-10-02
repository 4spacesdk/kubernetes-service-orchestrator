<?php namespace App\Libraries\Kubernetes;

use App\Entities\ContainerImage;
use App\Entities\DeploymentSpecification;
use RenokiCo\PhpK8s\Instances\Instance;

/**
 * Where a container with a read-only root filesystem may still write: an `emptyDir` on each path
 * its image says it writes to - a PID file, a lock, `/tmp`, a cache. Without, read-only cannot be
 * turned on for almost any image: it crashes as it starts.
 *
 * The paths are the image's, read from its OCI label `dk.4spaces.kso.writable-paths`
 * (comma-separated) when the image is read, and editable on its Security tab. A specification can
 * add paths for the containers that run its workload - the app, the migration job, the cron jobs,
 * the jobs `RunJobHelper` starts - but not take the image's away. An init container or a sidecar
 * has its own image, and that image's paths.
 *
 * Each path is its own `emptyDir`, on disk rather than in memory - tmpfs counts against the
 * container's memory limit - with a `sizeLimit` the specification can change. Its name is a short
 * hash of the container and the path, so it is the same at every deploy and a redeploy shows no
 * difference. An `emptyDir` is writable by everyone without an `fsGroup`, and by the group with one,
 * so it works with Run as non-root either way.
 */
class WritablePaths {

    public const string Label = 'dk.4spaces.kso.writable-paths';

    public const string DefaultSizeLimit = '1Gi';

    private const string VolumePrefix = 'kso-writable-';

    /**
     * The paths in a list as written - comma- or line-separated - without repeats.
     *
     * @return list<string>
     */
    public static function Parse(?string $written): array {
        return array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[,\n]+/', (string) $written)),
            fn(string $path) => $path !== ''
        )));
    }

    /**
     * Why a list cannot be saved, or null: every path is absolute and plain.
     */
    public static function ReasonInvalid(?string $written): ?string {
        foreach (self::Parse($written) as $path) {
            if (!str_starts_with($path, '/') || str_contains($path, '..') || preg_match('/[\s:]/', $path)) {
                return "'{$path}' is not an absolute path to write to, such as /tmp";
            }
        }
        return null;
    }

    /**
     * Why a size cannot be an `emptyDir`'s limit, or null. Empty is the default.
     */
    public static function ReasonSizeInvalid(?string $size): ?string {
        $size = trim((string) $size);
        if ($size !== '' && !preg_match('/^\d+(Ki|Mi|Gi|Ti|K|M|G|T)?$/', $size)) {
            return "'{$size}' is not a size Kubernetes reads, such as 512Mi or 1Gi";
        }
        return null;
    }

    /**
     * Mount the writable paths into a container whose root filesystem is read-only.
     *
     * @param Instance $container a `Container`, or the plain `Instance` the Knative step builds
     * @param DeploymentSpecification|null $spec whose own paths are added - null for an init container or a sidecar
     */
    public static function Mount(Instance $container, ContainerImage $image, ?DeploymentSpecification $spec): void {
        if (!$image->security_context_read_only_root_filesystem) {
            return;
        }
        $paths = self::Parse((string) $image->writable_paths);
        if ($spec !== null) {
            $paths = array_values(array_unique([...$paths, ...self::Parse((string) $spec->writable_paths)]));
        }
        $name = (string) $container->getAttribute('name');
        // A path that is not absolute and plain would have the pod refused; the forms say so.
        foreach (array_filter($paths, fn(string $path) => self::ReasonInvalid($path) === null) as $path) {
            $container->addToAttribute('volumeMounts', ['name' => self::VolumeName($name, $path), 'mountPath' => $path]);
        }
    }

    /**
     * The `emptyDir` volumes the containers' mounts name - for the pod's `volumes`.
     *
     * @param list<Instance|array> $containers every container of the pod, init containers included
     * @return list<array{name: string, emptyDir: array{sizeLimit: string}}>
     */
    public static function Volumes(array $containers, DeploymentSpecification $spec): array {
        $sizeLimit = trim((string) $spec->writable_paths_size_limit) ?: self::DefaultSizeLimit;
        $volumes = [];
        foreach ($containers as $container) {
            $mounts = $container instanceof Instance ? ($container->getAttribute('volumeMounts') ?? []) : ($container['volumeMounts'] ?? []);
            foreach ($mounts as $mount) {
                $mount = $mount instanceof Instance ? $mount->toArray() : $mount;
                $name = (string) ($mount['name'] ?? '');
                if (str_starts_with($name, self::VolumePrefix)) {
                    $volumes[$name] = ['name' => $name, 'emptyDir' => ['sizeLimit' => $sizeLimit]];
                }
            }
        }
        return array_values($volumes);
    }

    /**
     * The same for a container and a path at every deploy - and within the 63 characters of a name.
     */
    public static function VolumeName(string $container, string $path): string {
        return self::VolumePrefix . substr(sha1("{$container}\n{$path}"), 0, 12);
    }

}
