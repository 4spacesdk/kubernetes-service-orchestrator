<?php namespace App\Libraries\Kubernetes;

use App\Entities\Deployment;
use DebugTool\Data;

/**
 * A deployment's logs, across its pods, in one process.
 *
 * Reading a pod's log means holding a socket open, and php-k8s reads one of them to the end in a
 * blocking loop. Following a deployment that way would be a request - and an Apache process - per
 * pod, and a rollout is exactly when there are several: the old ones still serving, the new ones
 * starting. So the streams are opened here and read without blocking, a slice at a time, round
 * after round.
 *
 * Not `stream_select()`: the api server is https, and PHP cannot select on a stream with a TLS
 * filter on it - "Cannot cast a filtered stream on this system". Each stream is therefore set to
 * non-blocking and read with `fread()`, which answers with whatever has arrived. A read can stop
 * in the middle of a line, so each stream keeps what it has until a newline turns up.
 *
 * **It stops on its own.** Nothing on the browser's side can close it: the lines go out over the
 * push server, so the request writes nothing to its own response and never notices the reader
 * leaving. It ends when every stream has ended, after `IdleSeconds` without a line from any of
 * them, or after `MaxSeconds` whatever happens - one process, for a bounded time.
 *
 * **Pods that turn up while it runs are picked up**, which is what makes a rollout worth
 * watching: the new pod's first lines arrive in the same view as the old pod's last.
 */
class DeploymentLogs {

    /** Seconds without a line from any pod before the follow gives up. */
    public const int IdleSeconds = 60;

    /** Seconds a follow may run, however busy the logs are. */
    public const int MaxSeconds = 900;

    /** Seconds between looking for pods that were not there when the follow started. */
    public const int RescanSeconds = 5;

    /** Microseconds between two rounds of reading, so an idle follow costs nothing to speak of. */
    private const int PauseBetweenRounds = 200_000;

    /** Bytes taken from a stream at a time. */
    private const int ChunkSize = 8192;

    public function __construct(
        private readonly StreamingCluster $cluster,
    ) {
    }

    /**
     * The last lines from every pod of the deployment, oldest first.
     *
     * @param bool $previous The containers that ran before these - what a crash left behind.
     * @param int|null $sinceSeconds Only what was written in the last so many seconds.
     * @return list<array{date: string, line: string, pod: string, container: string}>
     */
    public function recent(Deployment $deployment, bool $previous = false, ?int $sinceSeconds = null): array {
        $lines = [];
        foreach ($this->containersOf($deployment) as [$pod, $container]) {
            try {
                $log = $this->lastLinesOf($deployment->namespace, $pod, $container, $previous, $sinceSeconds);
            } catch (\Throwable $e) {
                // A pod that went away between the listing and the read is not a failure of the
                // request - the others still have something to show.
                Data::debug('No logs from', $pod, ':', KubeHelper::PrintException($e));
                continue;
            }

            foreach (KubeHelper::LogLines($log) as $line) {
                $lines[] = [...$line, 'pod' => $pod, 'container' => $container];
            }
        }

        // By the timestamps the api server wrote, so the pods read as one log rather than as one
        // pod after another.
        usort($lines, fn(array $a, array $b) => $a['date'] <=> $b['date']);

        return $lines;
    }

    /**
     * Follow every pod of the deployment, handing each batch of lines to $onLines, until it stops
     * itself. `$now` is injectable so the clocks can be tested.
     *
     * @param \Closure(list<array{date: string, line: string, pod: string, container: string}>): void $onLines
     */
    public function follow(Deployment $deployment, \Closure $onLines, ?\Closure $now = null): void {
        $now ??= fn() => time();

        $startedAt = $now();
        $lastLineAt = $startedAt;
        // Null rather than zero: the pods are looked for on the first round, not five seconds in.
        $lastScanAt = null;
        /** @var array<string, resource> $streams */
        $streams = [];
        /** @var array<string, string> $unfinished what a stream has said so far, up to its last newline */
        $unfinished = [];

        try {
            while (true) {
                // Once per round, so a test can move the clock a step at a time.
                $at = $now();

                if ($at - $startedAt >= self::MaxSeconds) {
                    Data::debug('Followed for', self::MaxSeconds, 'seconds - stopping');
                    return;
                }
                if ($at - $lastLineAt >= self::IdleSeconds) {
                    Data::debug('Nothing for', self::IdleSeconds, 'seconds - stopping');
                    return;
                }

                if ($lastScanAt === null || $at - $lastScanAt >= self::RescanSeconds) {
                    $lastScanAt = $at;
                    $this->attachNewPods($deployment, $streams);
                }

                // No pods at all - a deployment scaled to zero, or one whose pods have not been
                // placed yet - is waited out rather than ended, which is what makes a deploy
                // watchable from before its first pod exists.
                $lines = $streams ? $this->readWhateverHasArrived($streams, $unfinished) : [];
                if ($lines) {
                    $lastLineAt = $at;
                    $onLines($lines);
                } else {
                    $this->pause();
                }
            }
        } finally {
            foreach ($streams as $stream) {
                @fclose($stream);
            }
        }
    }

    /** Between two rounds that found nothing. A test winds its own clock and overrides this. */
    protected function pause(): void {
        usleep(self::PauseBetweenRounds);
    }

    /**
     * One round: whatever every stream has to say right now, in whole lines.
     *
     * @param array<string, resource> $streams
     * @param array<string, string> $unfinished
     * @return list<array{date: string, line: string, pod: string, container: string}>
     */
    private function readWhateverHasArrived(array &$streams, array &$unfinished): array {
        $lines = [];

        foreach ($streams as $key => $stream) {
            [$pod, $container] = explode('/', $key, 2);

            $chunk = @fread($stream, self::ChunkSize);
            if ($chunk === false || ($chunk === '' && feof($stream))) {
                // The pod's log ended: it was replaced, or it finished. The rest are still going.
                @fclose($stream);
                unset($streams[$key], $unfinished[$key]);
                Data::debug('Stopped following', $key);
                continue;
            }
            if ($chunk === '') {
                continue;
            }

            $buffer = ($unfinished[$key] ?? '') . $chunk;
            $lastNewline = strrpos($buffer, "\n");
            if ($lastNewline === false) {
                // Half a line so far. Kept until the rest of it arrives, rather than shown as a
                // line of its own and then again in full.
                $unfinished[$key] = $buffer;
                continue;
            }

            $unfinished[$key] = substr($buffer, $lastNewline + 1);
            foreach (KubeHelper::LogLines(substr($buffer, 0, $lastNewline)) as $parsed) {
                $lines[] = [...$parsed, 'pod' => $pod, 'container' => $container];
            }
        }

        return $lines;
    }

    /**
     * Open a stream for every container that has not got one.
     *
     * @param array<string, resource> $streams
     */
    protected function attachNewPods(Deployment $deployment, array &$streams): void {
        foreach ($this->containersOf($deployment) as [$pod, $container]) {
            $key = "{$pod}/{$container}";
            if (isset($streams[$key])) {
                continue;
            }

            $stream = $this->openStream($deployment->namespace, $pod, $container);
            if ($stream === false) {
                Data::debug('Could not follow', $key);
                continue;
            }

            stream_set_blocking($stream, false);
            $streams[$key] = $stream;
            Data::debug('Following', $key);
        }
    }

    /**
     * What the cluster is asked for, kept to three methods so a test can answer them with
     * streams of its own - a follow cannot be tested against a real api server.
     */
    protected function lastLinesOf(string $namespace, string $pod, string $container, bool $previous = false, ?int $sinceSeconds = null): string {
        return $this->cluster->getPodByName($pod, $namespace)
            ->containerLogs($container, LogQuery::For($previous, $sinceSeconds));
    }

    /**
     * Only the newest line onwards: what came before is already in the view `recent()` filled.
     *
     * @return resource|false
     */
    protected function openStream(string $namespace, string $pod, string $container) {
        return $this->cluster->openLogStream($namespace, $pod, $container, ['tailLines' => 1]);
    }

    /**
     * The deployment's pods and their containers, by the labels kso puts on the pod template.
     *
     * @return list<array{0: string, 1: string}>
     */
    protected function containersOf(Deployment $deployment): array {
        $containers = [];
        $pods = $this->cluster->getAllPods($deployment->namespace, [
            'labelSelector' => urldecode(http_build_query(['app' => "{$deployment->name},role=app"])),
        ]);

        foreach ($pods as $pod) {
            if (($pod->getAttribute('metadata.deletionTimestamp') ?? null) !== null) {
                continue;
            }
            foreach ($pod->getContainers() as $container) {
                $containers[] = [$pod->getName(), $container->getName()];
            }
        }

        return $containers;
    }

}
