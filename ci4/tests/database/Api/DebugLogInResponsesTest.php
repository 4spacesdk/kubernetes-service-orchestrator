<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use DebugTool\Data;

/**
 * The debug log is kept, and sent only in development.
 *
 * `Data::debug()` is written to from everywhere - queries, remote servers' answers,
 * exception messages - and every response used to carry all of it to every caller. The
 * suite runs as `testing`, which stands in for every environment that is not development.
 */
class DebugLogInResponsesTest extends ControllerTestCase {

    public function testASuccessfulResponseCarriesNoDebugLog(): void {
        Data::debug('not for the caller');
        $response = $this->signedIn()->get('users/me');

        $body = json_decode((string) $response->response()->getBody(), true);
        $this->assertSame('OK', $body['status']);
        $this->assertArrayNotHasKey('debug', $body);
    }

    public function testAFailedResponseCarriesNoDebugLog(): void {
        Data::debug('not for the caller');
        $response = $this->signedIn()->put('users/mfa/setup/verify?code=123456');

        $body = json_decode((string) $response->response()->getBody(), true);
        $this->assertSame('ERROR', $body['status']);
        $this->assertArrayNotHasKey('debug', $body);
    }

    /**
     * Still written, for what reads it in the process: the cron commands store it as their
     * job's `last_log`.
     */
    public function testTheLogIsStillKept(): void {
        Data::debug('kept for the job log');

        $this->signedIn()->get('users/me');

        $this->assertContains(true, array_map(
            fn ($line) => is_string($line) && str_contains($line, 'kept for the job log'),
            Data::getDebugger()
        ));
    }

}
