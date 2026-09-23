<?php namespace App\Libraries\Health\Diagnosis;

use App\Libraries\Kubernetes\Quantity;

/**
 * Why a deployment is doing badly, from what kso can see and a general tool cannot: which
 * version it just rolled out, whether its migration failed, which pull secrets its image needs,
 * what the cluster said when it refused a deploy.
 *
 * Health says *that* something is wrong; this says *what*, and shows what it read it off. **A rule
 * that cannot settle it says so** - `CannotTell`, with what it did see - rather than guess. A
 * guess that sounds sure is worse than none.
 *
 * Pure, like `HealthEvaluator`: everything is in the `Evidence`, so every rule is tested in the
 * unit suite.
 */
class Diagnoser {

    private const array ImagePullReasons = ['ErrImagePull', 'ImagePullBackOff', 'InvalidImageName'];

    /** Seconds after a version change a crash is still put down to it, when the old pods are gone. */
    public const int VersionChangeWindow = 86400;

    /** Seconds a restart is remembered - the same hour as health's. */
    public const int RestartWindow = 3600;

    /** Restarts that make a crash, as in health. */
    public const int RestartsThatCount = 3;

    /** Lines of a log shown as evidence. */
    private const int LogLines = 10;

    /**
     * @return list<Finding> The most certain first. Empty when no rule found anything.
     */
    public static function Diagnose(Evidence $evidence, int $now): array {
        $findings = [
            ...self::RejectedByApiServer($evidence),
            ...self::MigrationFailed($evidence),
            ...self::ImagePull($evidence),
            ...self::OomKilled($evidence, $now),
            ...self::CrashAfterVersionChange($evidence, $now),
            ...self::NotReady($evidence),
            ...self::HealthCheckPath($evidence),
        ];

        $findings = self::Merged($findings);

        $rank = [\DiagnosisVerdicts::Certain => 0, \DiagnosisVerdicts::Possible => 1, \DiagnosisVerdicts::CannotTell => 2];
        usort($findings, fn(Finding $a, Finding $b) => $rank[$a->verdict] <=> $rank[$b->verdict]);

        return $findings;
    }

    // <editor-fold desc="Rules">

    /**
     * The cluster refused a manifest - kso's own settings, or the last deploy's answer.
     *
     * @return list<Finding>
     */
    private static function RejectedByApiServer(Evidence $evidence): array {
        $findings = [];
        $resources = $evidence->resources;

        // Kubernetes refuses these outright, so the deploy that carries them cannot succeed.
        foreach ([['cpu', 'm'], ['memory', ' MiB']] as [$kind, $unit]) {
            $request = $resources["{$kind}_request"] ?? null;
            $limit = $resources["{$kind}_limit"] ?? null;
            if ($request && $limit && $request > $limit) {
                $findings[] = new Finding(
                    'rejected_by_api_server',
                    \DiagnosisVerdicts::Certain,
                    "The {$kind} request is above its limit, which the cluster refuses",
                    ["Requested {$request}{$unit}, limit {$limit}{$unit}"],
                    ['type' => 'section', 'label' => 'Change the resources', 'section' => 'resource-management'],
                );
            }
        }

        $refused = $evidence->lastDeployError;
        if ($refused === null) {
            return $findings;
        }

        $status = json_decode($refused['error'], true);
        $when = date('Y-m-d H:i', $refused['at']);
        if (!is_array($status) || ($status['kind'] ?? null) !== 'Status') {
            $findings[] = new Finding(
                'rejected_by_api_server',
                \DiagnosisVerdicts::Possible,
                "The last deploy of {$refused['step']} failed",
                ["{$when}: " . mb_strimwidth($refused['error'], 0, 500, '…')],
                ['type' => 'deploy', 'label' => 'Deploy again'],
            );
            return $findings;
        }

        $code = (int) ($status['code'] ?? 0);
        $message = (string) ($status['message'] ?? $status['reason'] ?? '');
        $shown = ["{$when}: {$code} {$message}"];
        foreach ($status['details']['causes'] ?? [] as $cause) {
            $shown[] = trim(($cause['field'] ?? '') . ': ' . ($cause['message'] ?? ''), ': ');
        }

        $findings[] = match (true) {
            $code === 403 => new Finding(
                'rejected_by_api_server',
                \DiagnosisVerdicts::Certain,
                "kso is not allowed to deploy {$refused['step']} - its service account lacks the right",
                $shown,
            ),
            $code === 400 || $code === 422 => new Finding(
                'rejected_by_api_server',
                \DiagnosisVerdicts::Certain,
                "The cluster refused the manifest for {$refused['step']}",
                $shown,
                ['type' => 'deploy', 'label' => 'Deploy again'],
            ),
            default => new Finding(
                'rejected_by_api_server',
                \DiagnosisVerdicts::Possible,
                "The last deploy of {$refused['step']} failed",
                $shown,
                ['type' => 'deploy', 'label' => 'Deploy again'],
            ),
        };

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private static function MigrationFailed(Evidence $evidence): array {
        $migration = $evidence->lastMigration;
        // A migration that failed for an earlier version was fixed by the deploy that came after it.
        if ($migration === null || $evidence->version === '' || !str_ends_with($migration['image'], ":{$evidence->version}")) {
            return [];
        }

        $failedAt = match ($migration['status']) {
            \MigrationJobStatusTypes::Failed_LogVerification => 'its log did not show it finished',
            \MigrationJobStatusTypes::Failed_PostCommands => 'a post command failed',
            default => null,
        };
        if ($failedAt === null) {
            return [];
        }

        return [new Finding(
            'migration_failed',
            \DiagnosisVerdicts::Certain,
            "The migration for {$evidence->version} failed: {$failedAt}",
            self::LastLines(explode("\n", $migration['log'])),
            ['type' => 'migration_job', 'label' => 'Open the migration job', 'migration_job_id' => $migration['id']],
        )];
    }

    /**
     * @return list<Finding>
     */
    private static function ImagePull(Evidence $evidence): array {
        $findings = [];
        foreach (self::Containers($evidence->pods) as [$pod, $container, $spec]) {
            $waiting = $container['state']['waiting'] ?? [];
            $reason = $waiting['reason'] ?? null;
            if (!in_array($reason, self::ImagePullReasons, true)) {
                continue;
            }

            $podName = $pod['metadata']['name'] ?? '';
            $image = (string) ($spec['image'] ?? $container['image'] ?? '');
            $message = (string) ($waiting['message'] ?? '');
            $shown = [
                "{$podName}: {$reason}" . ($message !== '' ? " - {$message}" : ''),
                ...self::EventMessages($evidence, $podName, ['Failed']),
            ];

            $findings[] = self::WhyTheImageCannotBePulled($evidence, $pod, $image, $reason, $message, $shown);
        }
        return $findings;
    }

    private static function WhyTheImageCannotBePulled(Evidence $evidence, array $pod, string $image, string $reason, string $message, array $shown): Finding {
        if ($reason === 'InvalidImageName') {
            return new Finding('image_pull', \DiagnosisVerdicts::Certain, "The image name is not valid: {$image}", $shown);
        }

        $tag = self::TagOf($image);
        $pickAnother = ['type' => 'section', 'label' => 'Pick another version', 'section' => 'version'];

        if ($evidence->imageTags !== null && $tag !== null && !in_array($tag, $evidence->imageTags, true)) {
            return new Finding(
                'image_pull',
                \DiagnosisVerdicts::Certain,
                "The tag {$tag} is not in the registry",
                ['The registry has ' . count($evidence->imageTags) . ' tags for the image, and not this one', ...$shown],
                $pickAnother,
            );
        }
        if ($evidence->imageTagsError !== null) {
            $shown[] = "The registry could not be asked for its tags: {$evidence->imageTagsError}";
        }
        $tagIsThere = $evidence->imageTags !== null && $tag !== null;

        $named = array_map(fn(array $secret) => $secret['name'] ?? '', $pod['spec']['imagePullSecrets'] ?? []);
        foreach ($evidence->pullSecrets as $secret => $exists) {
            // kso's own registry secret is written by the deploy; one named on the image is not.
            $ours = str_starts_with($secret, 'kso-registry-');
            if ($exists === false) {
                return new Finding(
                    'image_pull',
                    \DiagnosisVerdicts::Certain,
                    "The pull secret {$secret} is not in the namespace" . ($ours ? '' : ' - it has to be created there'),
                    $shown,
                    $ours ? ['type' => 'deploy', 'label' => 'Deploy again'] : null,
                );
            }
            if (!in_array($secret, $named, true)) {
                return new Finding(
                    'image_pull',
                    \DiagnosisVerdicts::Certain,
                    "The pod does not name the pull secret {$secret}",
                    $shown,
                    ['type' => 'deploy', 'label' => 'Deploy again'],
                );
            }
        }

        if (preg_match('/\b(401|403)\b|unauthori[sz]ed|forbidden|denied/i', $message)) {
            return new Finding(
                'image_pull',
                $tagIsThere ? \DiagnosisVerdicts::Certain : \DiagnosisVerdicts::Possible,
                $evidence->pullSecrets
                    ? 'The registry refused the login - the pull secret is wrong or has expired'
                    : 'The registry refused the pull, and the image has no pull secret',
                $shown,
            );
        }

        if (preg_match('/not found|manifest unknown/i', $message)) {
            return new Finding(
                'image_pull',
                \DiagnosisVerdicts::Possible,
                $tag !== null ? "The tag {$tag} does not seem to exist" : 'The image does not seem to exist',
                $shown,
                $pickAnother,
            );
        }

        return new Finding(
            'image_pull',
            \DiagnosisVerdicts::CannotTell,
            $tagIsThere
                ? "The image cannot be pulled, though the tag is in the registry and the pull secrets are there"
                : 'The image cannot be pulled, and the cluster has not said why',
            $shown,
        );
    }

    /**
     * @return list<Finding>
     */
    private static function OomKilled(Evidence $evidence, int $now): array {
        $findings = [];
        foreach (self::Containers($evidence->pods) as [$pod, $container, $spec]) {
            $terminated = $container['lastState']['terminated'] ?? $container['state']['terminated'] ?? null;
            if (($terminated['reason'] ?? null) !== 'OOMKilled') {
                continue;
            }
            if ($now - self::Time($terminated['finishedAt'] ?? null, $now) > self::RestartWindow) {
                continue;
            }

            $podName = $pod['metadata']['name'] ?? '';
            $name = $container['name'] ?? '';
            $limit = Quantity::Bytes($spec['resources']['limits']['memory'] ?? null);
            $shown = ["{$podName}/{$name}: OOMKilled at " . ($terminated['finishedAt'] ?? '?')];

            $usage = self::MemoryUsage($evidence, $podName, $name);
            if ($usage !== null) {
                $shown[] = 'Using ' . self::MiB($usage) . ' now' . ($limit ? ' of ' . self::MiB($limit) : '');
            }

            $findings[] = $limit
                ? new Finding(
                    'oom_killed',
                    \DiagnosisVerdicts::Certain,
                    'The memory limit is too low: ' . self::MiB($limit),
                    [...$shown, 'The peak before it was killed is not kept - only what it uses now'],
                    ['type' => 'section', 'label' => 'Raise the memory limit', 'section' => 'resource-management'],
                )
                : new Finding(
                    'oom_killed',
                    \DiagnosisVerdicts::Possible,
                    'The node ran out of memory - the container has no limit of its own',
                    $shown,
                    ['type' => 'section', 'label' => 'Set a memory limit', 'section' => 'resource-management'],
                );
        }
        return $findings;
    }

    /**
     * A container that keeps crashing - and whether the version kso rolled out is why.
     *
     * Certain only when the old version is still running beside it: then the one thing that
     * differs is the version. Without it the crash may merely have followed the change.
     *
     * @return list<Finding>
     */
    private static function CrashAfterVersionChange(Evidence $evidence, int $now): array {
        $crashing = [];
        foreach (self::Containers($evidence->pods) as [$pod, $container, $spec]) {
            $terminated = $container['lastState']['terminated'] ?? null;
            if (($terminated['reason'] ?? null) === 'OOMKilled') {
                continue;
            }
            $loop = ($container['state']['waiting']['reason'] ?? null) === 'CrashLoopBackOff';
            $recent = $terminated !== null
                && $now - self::Time($terminated['finishedAt'] ?? null, $now) <= self::RestartWindow
                && (int) ($container['restartCount'] ?? 0) >= self::RestartsThatCount;
            if ($loop || $recent) {
                $crashing[] = [$pod, $container, $spec, $terminated];
            }
        }
        if (!$crashing) {
            return [];
        }

        $shown = [];
        foreach ($crashing as [$pod, $container, $spec, $terminated]) {
            $podName = $pod['metadata']['name'] ?? '';
            $exit = $terminated ? " - exit code " . ($terminated["exitCode"] ?? "?") . (isset($terminated['reason']) ? " ({$terminated['reason']})" : '') : '';
            $shown[] = "{$podName}: restarted {$container['restartCount']} times{$exit}";
            foreach (self::LastLines($evidence->previousLogs[$podName] ?? []) as $line) {
                $shown[] = "{$podName}: {$line}";
            }
        }

        $change = $evidence->versionChange;
        if ($change === null || $change['to'] !== $evidence->version) {
            return [new Finding(
                'crash_after_version_change',
                \DiagnosisVerdicts::CannotTell,
                'It keeps crashing, and there is no version change on record to put it down to',
                $shown,
            )];
        }

        $rollback = ['type' => 'rollback', 'label' => "Roll back to {$change['from']}", 'version' => $change['from']];
        $changed = "The version changed from {$change['from']} to {$change['to']} at " . date('Y-m-d H:i', $change['at']);

        $oldStillRuns = false;
        foreach ($evidence->pods as $pod) {
            $images = array_map(fn(array $c) => (string) ($c['image'] ?? ''), $pod['spec']['containers'] ?? []);
            $runsTheOld = (bool) array_filter($images, fn(string $image) => self::TagOf($image) === $change['from']);
            if ($runsTheOld && self::IsReady($pod)) {
                $oldStillRuns = true;
            }
        }

        if ($oldStillRuns) {
            return [new Finding(
                'crash_after_version_change',
                \DiagnosisVerdicts::Certain,
                "{$change['to']} crashes, and {$change['from']} still runs beside it",
                [$changed, ...$shown],
                $rollback,
            )];
        }

        if ($now - $change['at'] <= self::VersionChangeWindow) {
            return [new Finding(
                'crash_after_version_change',
                \DiagnosisVerdicts::Possible,
                "It has crashed since the version changed to {$change['to']}",
                [$changed, ...$shown],
                $rollback,
            )];
        }

        return [new Finding(
            'crash_after_version_change',
            \DiagnosisVerdicts::CannotTell,
            'It keeps crashing, and the version has not changed for more than a day',
            [$changed, ...$shown],
        )];
    }

    /**
     * Running, but not ready - what the probes said.
     *
     * kso sets no probes itself, so one comes from the image's spec or the chart; without one a
     * running container is ready, and one that is not has something else going on.
     *
     * @return list<Finding>
     */
    private static function NotReady(Evidence $evidence): array {
        $findings = [];
        foreach (self::Containers($evidence->pods) as [$pod, $container, $spec]) {
            if (($pod['status']['phase'] ?? null) !== 'Running' || !isset($container['state']['running']) || ($container['ready'] ?? true)) {
                continue;
            }
            // An init container is never "ready"; it is done or not.
            if (!in_array($container['name'] ?? '', array_column($pod['spec']['containers'] ?? [], 'name'), true)) {
                continue;
            }

            $podName = $pod['metadata']['name'] ?? '';
            $failures = self::EventMessages($evidence, $podName, ['Unhealthy']);
            $readiness = array_values(array_filter($failures, fn(string $m) => stripos($m, 'Readiness probe failed') !== false));

            if ($readiness) {
                $cause = 'The readiness probe fails';
                if (preg_match('/statuscode:\s*(401|403)/i', implode(' ', $readiness), $match)) {
                    $path = $spec['readinessProbe']['httpGet']['path'] ?? null;
                    $cause = "The readiness probe is answered with {$match[1]}"
                        . ($path ? " on {$path}" : '')
                        . ' - is the path behind a login, such as basic auth?';
                }
                $findings[] = new Finding('not_ready', \DiagnosisVerdicts::Certain, $cause, $readiness);
                continue;
            }

            $findings[] = new Finding(
                'not_ready',
                \DiagnosisVerdicts::CannotTell,
                isset($spec['readinessProbe'])
                    ? 'Not ready, and the readiness probe has left no event saying why'
                    : 'Not ready, though it has no readiness probe',
                ["{$podName}/{$container['name']}: running, not ready", ...$failures],
            );
        }
        return $findings;
    }

    /**
     * The path GKE's load balancer checks, answered with something other than 200 - the pods are
     * up and ready, and the site still answers 502, because the load balancer thinks otherwise.
     *
     * @return list<Finding>
     */
    private static function HealthCheckPath(Evidence $evidence): array {
        $check = $evidence->healthCheck;
        if ($check === null) {
            return [];
        }

        $failing = array_values(array_filter($check['answers'], fn(array $answer) => $answer['status'] !== 200));
        if (!$failing) {
            return [];
        }

        $statuses = array_values(array_unique(array_map(fn(array $answer) => $answer['status'], $failing)));
        $status = $statuses[0];
        $cause = "The load balancer's health check on {$check['path']} is answered with {$status}, and it wants 200";
        if ($status === 401 || $status === 403) {
            $cause = "The load balancer's health check on {$check['path']} is answered with {$status} - is the path behind a login, such as basic auth?";
        } else if ($status >= 300 && $status < 400) {
            $cause = "The load balancer's health check on {$check['path']} is redirected ({$status}) - it does not follow, and wants 200";
        }

        return [new Finding(
            'health_check_path',
            \DiagnosisVerdicts::Certain,
            $cause,
            array_map(fn(array $answer) => "{$answer['pod']}: GET :{$check['port']}{$check['path']} answered {$answer['status']}", $failing),
            ['type' => 'specification', 'label' => 'Change the health check'],
        )];
    }

    // </editor-fold>

    // <editor-fold desc="Helpers">

    /**
     * Every container status with its pod and its spec, init containers first.
     *
     * @param list<array> $pods
     * @return list<array{0: array, 1: array, 2: array}>
     */
    private static function Containers(array $pods): array {
        $all = [];
        foreach ($pods as $pod) {
            $specs = [];
            foreach ([...($pod['spec']['initContainers'] ?? []), ...($pod['spec']['containers'] ?? [])] as $spec) {
                $specs[$spec['name'] ?? ''] = $spec;
            }
            $status = $pod['status'] ?? [];
            foreach ([...($status['initContainerStatuses'] ?? []), ...($status['containerStatuses'] ?? [])] as $container) {
                $all[] = [$pod, $container, $specs[$container['name'] ?? ''] ?? []];
            }
        }
        return $all;
    }

    /**
     * The messages of the events about one pod with one of these reasons, newest first.
     *
     * @param list<string> $reasons
     * @return list<string>
     */
    private static function EventMessages(Evidence $evidence, string $podName, array $reasons): array {
        $events = array_filter(
            $evidence->events,
            fn(array $event) => ($event['involvedObject']['name'] ?? null) === $podName
                && in_array($event['reason'] ?? '', $reasons, true),
        );
        usort($events, fn(array $a, array $b) => self::EventTime($b) <=> self::EventTime($a));

        return array_values(array_map(
            fn(array $event) => ($event['message'] ?? '') . (($event['count'] ?? 1) > 1 ? " (×{$event['count']})" : ''),
            $events,
        ));
    }

    private static function EventTime(array $event): string {
        return (string) ($event['lastTimestamp'] ?? $event['eventTime'] ?? $event['metadata']['creationTimestamp'] ?? '');
    }

    private static function IsReady(array $pod): bool {
        foreach ($pod['status']['conditions'] ?? [] as $condition) {
            if (($condition['type'] ?? null) === 'Ready') {
                return ($condition['status'] ?? null) === 'True';
            }
        }
        return false;
    }

    /** The tag of an image, null for one pinned by digest or with no tag. */
    private static function TagOf(string $image): ?string {
        if (str_contains($image, '@')) {
            return null;
        }
        $name = substr($image, (int) strrpos($image, '/'));
        $colon = strrpos($name, ':');
        return $colon === false ? null : substr($name, $colon + 1);
    }

    private static function MemoryUsage(Evidence $evidence, string $podName, string $container): ?int {
        foreach ($evidence->metrics['pods'] ?? [] as $metrics) {
            if (($metrics['pod'] ?? null) === $podName && ($metrics['container'] ?? null) === $container) {
                return $metrics['memory_bytes'] ?? null;
            }
        }
        return null;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private static function LastLines(array $lines): array {
        $lines = array_values(array_filter(array_map('rtrim', $lines), fn(string $line) => $line !== ''));
        return array_slice($lines, -self::LogLines);
    }

    /**
     * One finding per cause: two pods that cannot pull the same image are one problem.
     *
     * @param list<Finding> $findings
     * @return list<Finding>
     */
    private static function Merged(array $findings): array {
        $merged = [];
        foreach ($findings as $finding) {
            $key = "{$finding->rule}|{$finding->verdict}|{$finding->cause}";
            $merged[$key] = isset($merged[$key])
                ? new Finding($finding->rule, $finding->verdict, $finding->cause, array_values(array_unique([...$merged[$key]->evidence, ...$finding->evidence])), $merged[$key]->action)
                : $finding;
        }
        return array_values($merged);
    }

    private static function MiB(int $bytes): string {
        return round($bytes / 1024 ** 2) . ' MiB';
    }

    private static function Time(?string $timestamp, int $now): int {
        $time = $timestamp ? strtotime($timestamp) : false;
        return $time === false ? $now : $time;
    }

    // </editor-fold>

}
