<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\Quantity;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Kubernetes' quantities, in the units kso compares them in.
 *
 * Worth pinning to the character: metrics.k8s.io writes cpu in nanocores (`36236n`) where the
 * deployment's own limit is in millicores, and memory in `Ki` where the limit is in `Mi`. Every
 * one of those is a factor of a thousand or a bit more, and a number that is wrong by a thousand
 * still looks like a number - it just makes a pod at its limit look idle.
 */
class QuantityTest extends CIUnitTestCase {

    public function testCpuComesBackInMillicoresWhateverItWasWrittenAs(): void {
        // Whole cores, the ordinary way to write a limit.
        $this->assertSame(1000, Quantity::Millicores('1'));
        $this->assertSame(2500, Quantity::Millicores('2.5'));

        // Millicores, which is what kso's own fields hold.
        $this->assertSame(250, Quantity::Millicores('250m'));

        // And what metrics-server actually answers with.
        $this->assertSame(0, Quantity::Millicores('36236n'), 'a container using next to nothing');
        $this->assertSame(9, Quantity::Millicores('8956861n'));
        $this->assertSame(1500, Quantity::Millicores('1500000u'));
        $this->assertSame(0, Quantity::Millicores('0'));
    }

    public function testMemoryComesBackInBytesWhateverItWasWrittenAs(): void {
        $this->assertSame(65304 * 1024, Quantity::Bytes('65304Ki'), 'what metrics-server answers with');
        $this->assertSame(64 * 1024 ** 2, Quantity::Bytes('64Mi'));
        $this->assertSame(1024 ** 3, Quantity::Bytes('1Gi'));
        $this->assertSame(1000, Quantity::Bytes('1k'));
        $this->assertSame(1_000_000, Quantity::Bytes('1M'));
        $this->assertSame(512, Quantity::Bytes('512'), 'a plain count of bytes');
    }

    /**
     * `Mi` is not `M` with an `i` after it - a factor of 1.05, which is the kind of wrong nobody
     * spots on a graph.
     */
    public function testTheBinarySuffixesAreNotReadAsTheDecimalOnes(): void {
        $this->assertNotSame(Quantity::Bytes('1M'), Quantity::Bytes('1Mi'));
        $this->assertSame(1_048_576, Quantity::Bytes('1Mi'));
        $this->assertSame(1_000_000, Quantity::Bytes('1M'));
    }

    /**
     * Null, not zero. A pod using no cpu and a pod nobody could measure look the same as a
     * number and are not the same thing - one is idle, the other is missing.
     */
    public function testWhatCannotBeReadIsNothingRatherThanZero(): void {
        foreach ([null, '', '   ', 'plenty', 'Mi', '12x'] as $nonsense) {
            $this->assertNull(Quantity::Millicores($nonsense), var_export($nonsense, true));
            $this->assertNull(Quantity::Bytes($nonsense), var_export($nonsense, true));
        }
    }

}
