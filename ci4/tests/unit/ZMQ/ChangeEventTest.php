<?php namespace App\Tests\Unit\ZMQ;

use App\Libraries\ZMQ\ChangeEvent;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The payload every push event carries: what a row looked like, and what it looks like now.
 *
 * `Parse()` and `toArray()` are the two ends of the wire - the application builds one, the
 * zmq client reads it back - so they are tested as a round trip rather than separately.
 *
 * `getDiff()` is the interesting one and has **no call sites at all** in the application,
 * the zmq server or the zmq client. It is tested here for what it would answer if anything
 * asked, because its answer is surprising: see the test.
 */
class ChangeEventTest extends CIUnitTestCase {

    public function testAnEventSurvivesTheRoundTripThroughTheWire(): void {
        $event = new ChangeEvent(
            ['status' => 'Draft'],
            ['status' => 'Active'],
            ['reason' => 'deployed']
        );

        $this->assertSame(
            [
                'previous' => ['status' => 'Draft'],
                'next' => ['status' => 'Active'],
                'extra' => ['reason' => 'deployed'],
            ],
            ChangeEvent::Parse($event->toArray())->toArray()
        );
    }

    /**
     * Most events carry no extra, and `Parse()` has to accept a payload without the key at
     * all - the application writes plenty of them that way.
     */
    public function testAPayloadWithoutAnExtraParses(): void {
        $event = ChangeEvent::Parse(['previous' => null, 'next' => ['id' => 7]]);

        $this->assertNull($event->extra);
        $this->assertSame(['previous' => null, 'next' => ['id' => 7], 'extra' => null], $event->toArray());
    }

    /**
     * A created row has no previous state, and then the whole of `next` is the change.
     * That is the shape `new ChangeEvent(null, ...)` produces, which is how nearly every
     * caller in the application builds one.
     *
     * The guard is `isset() && is_array()` and no test can tell the `is_array()` half from
     * its absence: `previous` is declared `?array`, so anything that is set is an array
     * already. Dropping it is an equivalent change, not an untested one.
     */
    public function testWithNoPreviousStateTheWholeRowIsTheChange(): void {
        $event = new ChangeEvent(null, ['id' => 7, 'status' => 'Draft']);

        $this->assertSame(['id' => 7, 'status' => 'Draft'], $event->getDiff());
    }

    public function testAChangedFieldIsReportedWithItsNewValue(): void {
        $event = new ChangeEvent(
            ['id' => 7, 'status' => 'Draft', 'name' => 'api'],
            ['id' => 7, 'status' => 'Active', 'name' => 'api']
        );

        $this->assertSame(['status' => 'Active'], $event->getDiff());
    }

    public function testAnAddedFieldIsReported(): void {
        $event = new ChangeEvent(['id' => 7], ['id' => 7, 'version' => '1.2.3']);

        $this->assertSame(['version' => '1.2.3'], $event->getDiff());
    }

    /**
     * Today's behaviour, and the reason this method is worth pinning rather than assuming.
     *
     * The diff is two `array_diff_assoc()` calls merged, so a key that is in `previous` and
     * gone from `next` comes back **under its old value** - indistinguishable from a field
     * that was just set to it. A reader cannot tell "status was removed" from "status is
     * now Draft". Nothing depends on it today because nothing calls `getDiff()`, and this
     * is what a first caller would have to know.
     */
    public function testARemovedFieldComesBackLookingLikeItWasSetToItsOldValue(): void {
        $event = new ChangeEvent(['id' => 7, 'status' => 'Draft'], ['id' => 7]);

        $this->assertSame(['status' => 'Draft'], $event->getDiff());
    }

    public function testTwoIdenticalStatesDifferInNothing(): void {
        $event = new ChangeEvent(['id' => 7, 'status' => 'Active'], ['id' => 7, 'status' => 'Active']);

        $this->assertSame([], $event->getDiff());
    }

}
