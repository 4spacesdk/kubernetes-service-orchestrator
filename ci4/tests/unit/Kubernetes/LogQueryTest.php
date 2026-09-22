<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\LogQuery;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * What kso asks the api server for when it reads a log.
 *
 * Small, and worth pinning: `previous` and `sinceSeconds` are the difference between seeing what
 * a crashed container said and seeing nothing at all, and a query built twice would drift.
 */
class LogQueryTest extends CIUnitTestCase {

    public function testTheDefaultIsTheLastLinesOfTheRunningContainer(): void {
        $query = LogQuery::For();

        $this->assertSame(['timestamps' => true, 'tailLines' => LogQuery::TailLines], $query);
    }

    public function testThePreviousContainerIsAskedForByName(): void {
        $this->assertTrue(LogQuery::For(previous: true)['previous']);
        $this->assertArrayNotHasKey('previous', LogQuery::For(previous: false));
    }

    /**
     * A window without a bigger cap would answer "the last hour" with a hundred lines from the
     * end of it, which is the one thing the window was asked for instead of.
     */
    public function testAWindowRaisesTheCapOnLines(): void {
        $query = LogQuery::For(sinceSeconds: 3600);

        $this->assertSame(3600, $query['sinceSeconds']);
        $this->assertSame(LogQuery::TailLinesWithinAWindow, $query['tailLines']);
        $this->assertGreaterThan(LogQuery::TailLines, $query['tailLines']);
    }

    /**
     * Only the windows the web app offers. A number of somebody's own is not an error - it is a
     * view of a log, and the ordinary view is a fine answer to a query string that makes no sense.
     */
    public function testOnlyTheOfferedWindowsAreTakenAndAnythingElseIsTheDefault(): void {
        foreach (LogQuery::Windows as $window) {
            $this->assertSame($window, LogQuery::WindowFrom((string) $window));
        }

        foreach (['', '0', '-60', '99999', 'an hour', null, '15 minutes'] as $nonsense) {
            $this->assertNull(LogQuery::WindowFrom($nonsense), var_export($nonsense, true));
        }
    }

}
