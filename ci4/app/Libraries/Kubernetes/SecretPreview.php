<?php namespace App\Libraries\Kubernetes;

use App\Entities\Deployment;

/**
 * Secret values kept out of a step's preview, which the UI shows as it is.
 *
 * Two places they could reach it. **The workload as it is in the cluster:** one deployed
 * before its variables went into a Secret still has the values in its pod spec, so every
 * variable the pod now reads from a Secret has its value hidden there. **The Secret itself:**
 * shown with every value hidden, the ones about to change marked, so the diff still says that
 * something will.
 */
class SecretPreview {

    public const string Hidden = '(hidden)';

    public const string Changed = '(hidden, changed)';

    /**
     * The step's preview, with the workload's Secret beside the workload when it has one, or
     * had one. Without, it is what it was: the workload alone.
     *
     * @param string $local the workload as kso would write it
     * @param array<string, mixed>|null $remote the workload as it is, cleaned up; null when not there
     * @param array<string, string>|null $remoteSecretData the Secret's values in the cluster; null when not there
     * @return array{local: string|string[], remote: string|string[]|null}
     */
    public static function of(string $local, ?array $remote, WorkloadSecret $secret, Deployment $deployment, ?array $remoteSecretData): array {
        $remote = $remote === null ? null : json_encode(self::hideValues($remote, json_decode($local, true)));

        if ($secret->isEmpty() && $remoteSecretData === null) {
            return ['local' => $local, 'remote' => $remote];
        }

        $locals = [$local];
        $remotes = $remote === null ? [] : [$remote];
        if (!$secret->isEmpty()) {
            $shown = [];
            foreach ($secret->data() as $key => $value) {
                $shown[$key] = ($remoteSecretData[$key] ?? null) === $value ? self::Hidden : self::Changed;
            }
            $locals[] = self::secretJson($secret, $deployment, $shown);
        }
        if ($remoteSecretData !== null) {
            $remotes[] = self::secretJson($secret, $deployment, array_map(fn () => self::Hidden, $remoteSecretData));
        }

        return ['local' => $locals, 'remote' => $remotes];
    }

    /**
     * The workload from the cluster, with the value of every variable the local one reads from
     * a Secret hidden - in every container and init container, however deep the pod template.
     *
     * @param array<string, mixed> $remote
     * @param array<string, mixed> $local
     * @return array<string, mixed>
     */
    public static function hideValues(array $remote, array $local): array {
        $names = self::namesReadFromASecret($local);

        return $names === [] ? $remote : self::hide($remote, $names);
    }

    /**
     * @param array<string, string> $data what to show for each key
     */
    private static function secretJson(WorkloadSecret $secret, Deployment $deployment, array $data): string {
        $resource = json_decode($secret->toResource($deployment)->toJson(), true);
        $resource['data'] = $data;

        return json_encode($resource);
    }

    /**
     * @return list<string>
     */
    private static function namesReadFromASecret(array $node): array {
        $names = [];
        foreach ($node as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            if ($key === 'env') {
                foreach ($value as $entry) {
                    if (isset($entry['name'], $entry['valueFrom']['secretKeyRef'])) {
                        $names[] = $entry['name'];
                    }
                }
                continue;
            }
            $names = [...$names, ...self::namesReadFromASecret($value)];
        }

        return array_values(array_unique($names));
    }

    /**
     * @param list<string> $names
     */
    private static function hide(array $node, array $names): array {
        foreach ($node as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            if ($key === 'env') {
                foreach ($value as $index => $entry) {
                    if (isset($entry['name'], $entry['value']) && in_array($entry['name'], $names, true)) {
                        $node[$key][$index]['value'] = self::Hidden;
                    }
                }
                continue;
            }
            $node[$key] = self::hide($value, $names);
        }

        return $node;
    }

}
