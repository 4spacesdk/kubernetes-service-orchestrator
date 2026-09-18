<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\KubeHelper;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The shell's output is the same however the network cut it into frames.
 *
 * Found by the cluster suite: `echo first; echo second` came back as one frame locally and
 * as four in Cloud Build, with each CRLF on its own. Trimming frame by frame turned those
 * into empty lines, and locally a `\r` was left on every line but the last.
 */
class ExecOutputLinesTest extends CIUnitTestCase {

    public static function framings(): array {
        return [
            'one frame' => [["first\r\nsecond\r\n"]],
            'a frame per line' => [["first\r\n", "second\r\n"]],
            'line endings on their own' => [["first", "\r\n", "second", "\r\n"]],
            'a CRLF cut in half' => [["first\r", "\nsec", "ond\r", "\n"]],
        ];
    }

    #[DataProvider('framings')]
    public function testTheLinesDoNotDependOnTheFrames(array $frames): void {
        $messages = array_map(fn ($output) => ['channel' => 'stdout', 'output' => $output], $frames);

        $this->assertSame(['first', 'second'], KubeHelper::ExecOutputLines($messages));
    }

    public function testOnlyStdoutIsShown(): void {
        $this->assertSame(['out'], KubeHelper::ExecOutputLines([
            ['channel' => 'stderr', 'output' => "err\r\n"],
            ['channel' => 'stdout', 'output' => "out\r\n"],
        ]));
    }

    /**
     * What the endpoint has always answered when nothing was printed, and what the page
     * expects.
     */
    public function testNothingPrintedIsOneEmptyLine(): void {
        $this->assertSame([''], KubeHelper::ExecOutputLines([]));
    }

    /**
     * Blank lines in the middle are output too. Only the ending is dropped.
     */
    public function testABlankLineInTheMiddleIsKept(): void {
        $this->assertSame(['a', '', 'b'], KubeHelper::ExecOutputLines([
            ['channel' => 'stdout', 'output' => "a\r\n\r\nb\r\n"],
        ]));
    }
}
