<?php namespace App\Libraries\Kubernetes;

use App\Entities\ContainerImage;
use App\Entities\DeploymentSpecification;
use RenokiCo\PhpK8s\Instances\Instance;

/**
 * The security context of every container kso runs - a Deployment's, a Knative service's, a cron
 * job's, the migration job's and their init containers. Each container takes its own image's,
 * and the specification may override:
 *
 * * **The container image** holds it, and is made secure when it is made: kso reads the `USER`
 *   its registry says it runs as (`ContainerImage::readUser()`), and stamps `run_as_non_root` on
 *   when that is a number other than 0, and `seccomp_runtime_default` on regardless. An image
 *   made before then has neither, and runs as it always did. `runAsUser`, `runAsGroup`,
 *   `allowPrivilegeEscalation`, `readOnlyRootFilesystem` and `fsGroup` are the image's as before.
 * * **The specification** overrides the three - `On`, `Off`, or empty to inherit
 *   (`SecurityContextOverrides`) - and may give one `fsGroup` to all its pods, kept when an
 *   image's uid changes, so a new version can still write what an old one left on a volume.
 *
 * `runAsNonRoot` rather than a uid is what makes an image safe across versions: a security update
 * that gives it a new uid still runs as non-root, where a uid written down is wrong for one of them.
 * Per container, so a migration image that runs as root beside an app that does not is no reason
 * to have all or nothing.
 *
 * All at container level but `fsGroup`: Knative takes a pod-level security context only behind a
 * feature flag, and a container's own applies to that container, init containers included.
 */
class SecurityContext {

    /**
     * @param Instance $container A `Container` - or the plain `Instance` the Knative step builds its one from
     * @param bool $runsTheWorkload the app, the migration job, a cron job or a job `RunJobHelper` starts -
     *                              not an init container or a sidecar: the specification's writable paths
     *                              are added to these, see `WritablePaths`
     */
    public static function ApplyToContainer(Instance $container, ContainerImage $image, DeploymentSpecification $spec, bool $runsTheWorkload = true): void {
        if (strlen((string) $image->security_context_run_as_user) > 0) {
            $container->setAttribute('securityContext.runAsUser', (int) $image->security_context_run_as_user);
        }
        if (strlen((string) $image->security_context_run_as_group) > 0) {
            $container->setAttribute('securityContext.runAsGroup', (int) $image->security_context_run_as_group);
        }
        $container->setAttribute('securityContext.allowPrivilegeEscalation', (bool) $image->security_context_allow_privilege_escalation);
        $container->setAttribute('securityContext.readOnlyRootFilesystem', (bool) $image->security_context_read_only_root_filesystem);

        if (self::Effective($spec->security_context_run_as_non_root, $image->security_context_run_as_non_root)) {
            $container->setAttribute('securityContext.runAsNonRoot', true);
        }
        if (self::Effective($spec->security_context_drop_all_capabilities, $image->security_context_drop_all_capabilities)) {
            $container->setAttribute('securityContext.capabilities', ['drop' => ['ALL']]);
        }
        if (self::Effective($spec->security_context_seccomp_runtime_default, $image->security_context_seccomp_runtime_default)) {
            $container->setAttribute('securityContext.seccompProfile', ['type' => 'RuntimeDefault']);
        }

        // Where it may still write when its root filesystem is read-only.
        WritablePaths::Mount($container, $image, $runsTheWorkload ? $spec : null);
    }

    /**
     * The pod's `fsGroup`: the specification's when it has one, the image's otherwise, null for none.
     */
    public static function FsGroup(ContainerImage $image, DeploymentSpecification $spec): ?int {
        foreach ([$spec->security_context_fs_group, $image->security_context_fs_group] as $group) {
            if (strlen((string) $group) > 0) {
                return (int) $group;
            }
        }
        return null;
    }

    /**
     * The specification's `On`/`Off`, or the image's when it inherits.
     */
    public static function Effective(?string $override, mixed $imageValue): bool {
        return match ((string) $override) {
            \SecurityContextOverrides::On => true,
            \SecurityContextOverrides::Off => false,
            default => (bool) $imageValue,
        };
    }

    /**
     * Whether a `USER` is one `runAsNonRoot` accepts: a number other than 0, alone or before a
     * group. A name cannot be checked by kubelet, and empty is root.
     */
    public static function IsNonRootUser(?string $user): bool {
        $uid = explode(':', trim((string) $user))[0];
        return ctype_digit($uid) && (int) $uid !== 0;
    }

}
