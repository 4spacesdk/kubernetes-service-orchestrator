<?php namespace App\Tests\Unit\Kubernetes;

use App\Entities\Deployment;
use App\Libraries\Kubernetes\DeploymentLogs;
use App\Libraries\Kubernetes\StreamingCluster;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Following every pod of a deployment in one process.
 *
 * Each pod is a socket pair: the test writes into one end, the loop reads the other. That is the
 * part a file cannot stand in for - a log that has said nothing yet is not a log that has ended,
 * and the difference is what decides whether a pod is dropped.
 *
 * The clock is handed in and moves a second per round, so the two ways a follow stops are decided
 * here rather than waited for.
 */
class DeploymentLogsTest extends CIUnitTestCase {

    public function testEveryPodsLinesComeThroughSaidWhoseTheyAre(): void {
        $logs = new FakeDeploymentLogs(['api-1', 'api-2']);
        $logs->onRound(1, fn() => $logs->say('api-1', "1 from one\n") + $logs->say('api-2', "2 from two\n"));

        $lines = $logs->collect($this->deployment());

        $this->assertSame([['1', 'from one', 'api-1'], ['2', 'from two', 'api-2']], $lines);
    }

    /**
     * A read stops wherever the bytes stop. Half a line is kept until the rest turns up: shown at
     * once, it would appear twice, the second time in full.
     */
    public function testALineThatArrivesInPiecesIsShownOnceAndWhole(): void {
        $logs = new FakeDeploymentLogs(['api-1']);
        $logs->onRound(1, fn() => $logs->say('api-1', '1 half a li'));
        $logs->onRound(2, fn() => $logs->say('api-1', "ne, then the rest\n"));

        $lines = $logs->collect($this->deployment());

        $this->assertSame([['1', 'half a line, then the rest', 'api-1']], $lines);
    }

    /**
     * The pod a rollout starts while somebody is watching: its first lines belong in the same
     * view as the last of the pod it replaces.
     */
    public function testAPodThatTurnsUpWhileItRunsIsFollowedToo(): void {
        $logs = new FakeDeploymentLogs(['api-old']);
        $logs->onRound(1, fn() => $logs->say('api-old', "1 still serving\n"));
        $logs->onRound(6, fn() => $logs->podAppears('api-new'));
        $logs->onRound(7, fn() => $logs->say('api-new', "2 just started\n"));

        $lines = $logs->collect($this->deployment());

        $this->assertSame([['1', 'still serving', 'api-old'], ['2', 'just started', 'api-new']], $lines);
    }

    /**
     * One pod's log ending is not the end of the follow - that is what a rollout looks like from
     * the old pod's side.
     */
    public function testAPodWhoseLogEndsIsDroppedAndTheRestGoOn(): void {
        $logs = new FakeDeploymentLogs(['api-old', 'api-new']);
        $logs->onRound(1, fn() => $logs->say('api-old', "1 last words\n"));
        $logs->onRound(2, fn() => $logs->podEnds('api-old'));
        $logs->onRound(3, fn() => $logs->say('api-new', "2 carrying on\n"));

        $lines = $logs->collect($this->deployment());

        $this->assertSame([['1', 'last words', 'api-old'], ['2', 'carrying on', 'api-new']], $lines);
    }

    public function testItGivesUpAfterALongEnoughSilence(): void {
        $logs = new FakeDeploymentLogs(['api-1']);
        $logs->onRound(1, fn() => $logs->say('api-1', "1 one line\n"));

        $logs->collect($this->deployment());

        // Counted from the last line, not from the start.
        $this->assertSame($logs->lastLineAt + DeploymentLogs::IdleSeconds, $logs->seconds);
    }

    /**
     * And it stops even when the logs never go quiet: one process, for a bounded time.
     */
    public function testItStopsAtTheLimitHoweverBusyTheLogsAre(): void {
        $logs = new FakeDeploymentLogs(['api-1']);
        $logs->everyRound(fn() => $logs->say('api-1', "1 a line every round\n"));

        $logs->collect($this->deployment());

        $this->assertSame(DeploymentLogs::MaxSeconds, $logs->seconds);
    }

    private function deployment(): Deployment {
        $deployment = new Deployment();
        $deployment->name = 'api';
        $deployment->namespace = 'ns';
        return $deployment;
    }

}

/**
 * `DeploymentLogs` with the cluster replaced by socket pairs, and a clock the test winds.
 */
class FakeDeploymentLogs extends DeploymentLogs {

    /** @var array<string, resource> the end the test writes into */
    private array $writers = [];
    /** @var array<string, string> said before the loop attached to that pod */
    private array $waiting = [];
    /** @var array<int, \Closure> */
    private array $rounds = [];
    private ?\Closure $eachRound = null;
    private int $round = 0;
    public int $seconds = 0;
    public ?int $lastLineAt = null;

    /**
     * @param list<string> $pods
     */
    public function __construct(private array $pods) {
        // Never called: everything that would reach the cluster is answered here.
        parent::__construct(new StreamingCluster('http://127.0.0.1:9'));
    }

    public function onRound(int $round, \Closure $what): void {
        $this->rounds[$round] = $what;
    }

    public function everyRound(\Closure $what): void {
        $this->eachRound = $what;
    }

    /**
     * What the pod says. Before its stream is open - the first round, where the loop has not
     * attached to anything yet - it is kept and written the moment it is: a log that was written
     * before anybody looked is exactly what `tailLines` brings along.
     */
    public function say(string $pod, string $text): int {
        $key = "{$pod}/app";
        if (isset($this->writers[$key])) {
            fwrite($this->writers[$key], $text);
        } else {
            $this->waiting[$key] = ($this->waiting[$key] ?? '') . $text;
        }
        return 1;
    }

    public function podAppears(string $pod): int {
        $this->pods[] = $pod;
        return 1;
    }

    /** The pod's log ends, as it does when the pod goes away. */
    public function podEnds(string $pod): int {
        foreach ($this->writers as $key => $writer) {
            if (str_starts_with($key, "{$pod}/")) {
                fclose($writer);
                unset($this->writers[$key]);
            }
        }
        return 1;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}> date, line and pod of every line
     */
    public function collect(Deployment $deployment): array {
        $lines = [];

        parent::follow(
            $deployment,
            function (array $batch) use (&$lines) {
                $this->lastLineAt = $this->seconds;
                foreach ($batch as $line) {
                    $lines[] = [$line['date'], $line['line'], $line['pod']];
                }
            },
            function () {
                // One call per round: the loop reads the clock once, so this is where the round
                // happens. The clock starts at zero, so a stop lands on the constant it is about.
                $this->round++;
                $this->seconds = $this->round - 1;
                ($this->rounds[$this->round] ?? $this->eachRound ?? fn() => null)();
                return $this->seconds;
            }
        );

        return $lines;
    }

    /** The rounds are the clock here; there is nothing to wait for. */
    protected function pause(): void {
    }

    protected function containersOf(Deployment $deployment): array {
        return array_map(fn(string $pod) => [$pod, 'app'], $this->pods);
    }

    protected function openStream(string $namespace, string $pod, string $container) {
        // A socket pair, not a file: an empty one reads as "nothing yet", and only closing the
        // writing end makes it read as ended.
        [$reader, $writer] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $key = "{$pod}/{$container}";
        $this->writers[$key] = $writer;
        if (isset($this->waiting[$key])) {
            fwrite($writer, $this->waiting[$key]);
            unset($this->waiting[$key]);
        }
        return $reader;
    }

    protected function lastLinesOf(string $namespace, string $pod, string $container, bool $previous = false, ?int $sinceSeconds = null): string {
        return '';
    }

}
