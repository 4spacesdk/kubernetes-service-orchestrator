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

    /**
     * Something in the log of the request itself. The harness starts every request with an
     * empty log, as a fresh process would, so a line written before the request is gone by
     * the time it runs.
     */
    private function aRequestThatWritesToTheLog(): void {
        \CodeIgniter\Events\Events::on('post_controller_constructor', static function (): void {
            Data::debug('not for the caller');
        });
    }

    public function testASuccessfulResponseCarriesNoDebugLog(): void {
        $this->aRequestThatWritesToTheLog();
        $response = $this->signedIn()->get('users/me');

        $body = json_decode((string) $response->response()->getBody(), true);
        $this->assertSame('OK', $body['status']);
        $this->assertArrayNotHasKey('debug', $body);
    }

    public function testAFailedResponseCarriesNoDebugLog(): void {
        $this->aRequestThatWritesToTheLog();
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
        $this->aRequestThatWritesToTheLog();

        $this->signedIn()->get('users/me');

        $this->assertContains(true, array_map(
            fn ($line) => is_string($line) && str_contains($line, 'not for the caller'),
            Data::getDebugger()
        ));
    }

}
