<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\KubeHelper;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A container's log, cut into the dated rows the log page shows.
 *
 * Kubernetes prefixes every line with RFC3339Nano and a space when `timestamps` is on, and
 * that prefix is **not a fixed width**: trailing zeroes in the fraction are dropped. The
 * split used to be by character position, which ate the start of the line whenever the
 * clock was round - and a container that logs on a timer is round every time.
 *
 * Held without a cluster, like the exec output beside it: the cutting is the whole of the
 * decision, and the rest is Kubernetes.
 */
class LogLinesTest extends CIUnitTestCase {

    /**
     * The timestamps a real log carries, shortest to longest. Only the last one is the
     * thirty characters the old split assumed.
     *
     * @return array<string, array{0: string}>
     */
    public static function theWidthsATimestampCanHave(): array {
        return [
            'whole second' => ['2026-09-20T08:00:00Z'],
            'milliseconds' => ['2026-09-20T08:00:00.123Z'],
            'microseconds' => ['2026-09-20T08:00:00.123456Z'],
            'nanoseconds' => ['2026-09-20T08:00:00.123456789Z'],
        ];
    }

    #[DataProvider('theWidthsATimestampCanHave')]
    public function testTheWholeLineSurvivesWhateverTheTimestampsWidth(string $timestamp): void {
        $rows = KubeHelper::LogLines("{$timestamp} Starting the server\n");

        $this->assertSame([['date' => $timestamp, 'line' => 'Starting the server']], $rows);
    }

    /**
     * A line of its own per log line, in the order they arrived, because the page numbers
     * them and follows them.
     */
    public function testEachLineBecomesItsOwnRow(): void {
        $rows = KubeHelper::LogLines(
            "2026-09-20T08:00:00Z first\n"
            . "2026-09-20T08:00:01.5Z second\n"
        );

        $this->assertSame([
            ['date' => '2026-09-20T08:00:00Z', 'line' => 'first'],
            ['date' => '2026-09-20T08:00:01.5Z', 'line' => 'second'],
        ], $rows);
    }

    /**
     * Only the *first* space is the separator. A log line is mostly spaces, and splitting
     * on the wrong one would hand the page half a sentence as a date.
     */
    public function testOnlyTheFirstSpaceSeparatesTheDateFromTheLine(): void {
        $rows = KubeHelper::LogLines("2026-09-20T08:00:00Z GET /api/health 200 in 4ms\n");

        $this->assertSame('GET /api/health 200 in 4ms', $rows[0]['line']);
    }

    /**
     * An empty line is not a row. The text ends with a newline, so the last split piece is
     * always empty, and a blank row would be drawn as one.
     */
    public function testEmptyLinesAreNotRows(): void {
        $rows = KubeHelper::LogLines("2026-09-20T08:00:00Z only\n\n");

        $this->assertCount(1, $rows);
    }

    /**
     * A container that has printed nothing has no rows, rather than one empty one.
     */
    public function testAnEmptyLogIsNoRowsAtAll(): void {
        $this->assertSame([], KubeHelper::LogLines(''));
    }

    /**
     * A line with no prefix to take off keeps its text. It cannot arrive while `timestamps`
     * is on, and this is the right way to be wrong about it: a line shown under no date is
     * readable, a date with the line inside it is not.
     */
    public function testALineWithNoTimestampKeepsItsText(): void {
        $this->assertSame(
            [['date' => '', 'line' => 'no-timestamp-here']],
            KubeHelper::LogLines("no-timestamp-here\n")
        );
    }

    /**
     * What a kubelet writes as the log when it has lost the previous container's: no timestamp,
     * a sentence. Found 2026-09-23 by `DiagnosisTest`, where it read "to retrieve container logs"
     * under the date "unable".
     */
    public function testAKubeletsOwnMessageIsNotCutAtItsFirstWord(): void {
        $message = 'unable to retrieve container logs for containerd://7c7b70c94555';

        $this->assertSame([['date' => '', 'line' => $message]], KubeHelper::LogLines("{$message}\n"));
    }

    /**
     * A timestamp with nothing after it is a blank line in the container's output, and it
     * keeps its place - the log page numbers the rows, so dropping one moves everything
     * below it.
     */
    public function testATimestampWithAnEmptyLineAfterItIsStillARow(): void {
        $this->assertSame(
            [['date' => '2026-09-20T08:00:00Z', 'line' => '']],
            KubeHelper::LogLines("2026-09-20T08:00:00Z \n")
        );
    }

}
