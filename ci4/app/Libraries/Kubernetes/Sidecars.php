<?php namespace App\Libraries\Kubernetes;

/**
 * Native sidecars, as a pod read from the cluster has them: init containers with
 * `restartPolicy: Always` (Kubernetes 1.29+), which start in their place among the init
 * containers and then run beside the app. See `InitContainer::toKubernetesResource()`.
 *
 * What reads a pod has to tell them from ordinary init containers, which run to the end one at
 * a time: a sidecar is running and ready when all is well, and it counts towards the pod's
 * readiness and its requests for as long as the pod lives.
 */
class Sidecars {

    /**
     * @param array<string, mixed> $container a container of a pod spec
     */
    public static function Is(array $container): bool {
        return ($container['restartPolicy'] ?? null) === 'Always';
    }

    /**
     * @param array<string, mixed> $podSpec
     * @return list<string>
     */
    public static function Names(array $podSpec): array {
        return array_values(array_map(
            fn(array $container) => (string) ($container['name'] ?? ''),
            array_filter($podSpec['initContainers'] ?? [], fn(array $container) => self::Is($container)),
        ));
    }

    /**
     * Whether a sidecar of the pod has started but is not ready - which keeps the pod from
     * being ready, as an app container would.
     *
     * @param array<string, mixed> $pod
     */
    public static function AnyNotReady(array $pod): bool {
        $names = self::Names($pod['spec'] ?? []);
        foreach ($pod['status']['initContainerStatuses'] ?? [] as $status) {
            if (in_array($status['name'] ?? '', $names, true) && !($status['ready'] ?? false)) {
                return true;
            }
        }
        return false;
    }

}
