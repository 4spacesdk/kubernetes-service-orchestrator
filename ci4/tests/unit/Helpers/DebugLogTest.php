<?php namespace App\Tests\Unit\Helpers;

use App\Helpers\DebugLog;
use CodeIgniter\Test\CIUnitTestCase;
use DebugTool\Data;

/**
 * The debug log goes out with a response in development only. The suite runs as `testing`,
 * which stands in for every environment that is not.
 */
class DebugLogTest extends CIUnitTestCase {

    protected function setUp(): void {
        parent::setUp();
        Data::del('debug');
    }

    public function testTheResponseBodyLeavesTheLogOut(): void {
        Data::set('status', 'OK');
        Data::debug('not for the caller');

        $body = DebugLog::ResponseBody();

        $this->assertSame('OK', $body['status']);
        $this->assertArrayNotHasKey('debug', $body);
        $this->assertNotEmpty(Data::getDebugger(), 'the log itself is kept');
    }

    /**
     * RestExtension's handler answers a refused request with the whole log in it. By the
     * time it runs behind the guard, there is none.
     */
    public function testTheGuardDropsTheLogBeforeTheHandlerItWrapsRuns(): void {
        Data::debug('not for the caller');
        $seen = null;

        $guard = DebugLog::Guard(function (\Throwable $e) use (&$seen): void {
            $seen = [$e->getMessage(), Data::getDebugger()];
        });
        $guard(new \RuntimeException('refused'));

        $this->assertSame(['refused', []], $seen);
    }

    public function testTheGuardWithNothingToWrapDoesNotFail(): void {
        DebugLog::Guard(null)(new \RuntimeException('refused'));

        $this->assertSame([], Data::getDebugger());
    }

}
