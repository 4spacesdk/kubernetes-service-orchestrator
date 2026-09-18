<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use RestExtension\Exceptions\UnauthorizedException;

/**
 * `GET /database-services/{id}/test-connection`, the one endpoint on this controller.
 *
 * It is the button beside a database service in the UI, and its whole job is to tell an
 * administrator whether the host, port, user and password that were just typed in
 * actually work - before a deployment relies on them, because the next thing that opens
 * this connection is `DatabaseStep`, which creates the tenant's database and user during
 * a deploy.
 *
 * So the answer is a yes or a no, never an error: a wrong password, a firewall or a
 * hostname that does not resolve are the ordinary cases this endpoint exists to report,
 * and every one of them raises an exception somewhere underneath.
 */
class DatabaseServicesApiTest extends ControllerTestCase {

    /**
     * The ordinary failure. A host that does not resolve is a typo in a form, and it has to
     * come back as OK with a `false` in it - a 500 would show the user a stack trace where
     * the product means to show a red cross.
     */
    public function testADatabaseThatCannotBeReachedIsAnsweredWithNoRatherThanAnError(): void {
        $service = Fixtures::databaseService(['host' => 'no-such-host.invalid']);

        $body = $this->testConnection($service->id);

        $this->assertSame('OK', $body['status']);
        $this->assertFalse($body['resource']['value']);
    }

    /**
     * A real boolean, not the string `"false"`.
     *
     * The frontend draws the mark straight off this value, and every non-empty string is
     * true in JavaScript - a `"false"` on the wire would turn every failed connection test
     * into a green tick, which is worse than no test at all.
     */
    public function testTheAnswerIsABooleanTheBrowserCanTrust(): void {
        $service = Fixtures::databaseService(['host' => 'no-such-host.invalid']);

        $this->assertIsBool($this->testConnection($service->id)['resource']['value']);
    }

    /**
     * **This endpoint cannot answer yes.** Not for a wrong password - for any MySQL service
     * at all, including one whose settings are perfect.
     *
     * `DatabaseService::prepareConnection()` passes `'port' => $this->port`, and a property
     * read off a row is a string even where the column is an int. CodeIgniter's MySQLi
     * driver is `declare(strict_types=1)`, so `mysqli::real_connect()` rejects `"3306"`
     * where it wants `?int`, and `testConnection()` reports the TypeError as a failed
     * connection. Casting the port to int makes the very same service connect.
     *
     * The button therefore says no to everything, and `DatabaseStep` cannot create a
     * tenant database either. Pinned here because it is what the product does today:
     * **fixing the cast should break this test**, and the fix is to replace this with the
     * assertion that a reachable database answers true.
     *
     * The settings come from the test run's own database configuration rather than being
     * written out here - they are the one database this test is certain to be able to
     * reach, and nothing in the assertion depends on what they are.
     */
    public function testAReachableDatabaseIsStillAnsweredWithNoBecauseThePortIsSentAsAString(): void {
        $database = config('Database')->tests;
        $service = Fixtures::databaseService([
            'driver' => \DatabaseDrivers::MySQL,
            'host' => $database['hostname'],
            'port' => $database['port'],
            'user' => $database['username'],
            'pass' => $database['password'],
        ]);

        $this->assertFalse(
            $this->testConnection($service->id)['resource']['value'],
            'the connection test now succeeds - see the note above and replace this test'
        );

        // The diagnosis, so a future reader does not have to rediscover it: the only thing
        // standing between this service and a connection is the type of the port.
        $service->port = (int) $service->port;
        $this->assertTrue($service->testConnection());
    }

    /**
     * **An id that is not there takes the request down.** Every other controller in kso
     * looks the row up and checks `exists()` before it uses it; this one does not, so a
     * service deleted between the list loading and the button being pressed reaches
     * `prepareConnection()` with no driver, and `match ($this->driver)` has no arm for
     * null.
     *
     * `UnhandledMatchError` is an `\Error`, and `testConnection()` catches
     * `\Exception|DatabaseException` - so the guard that turns every other failure into a
     * `false` does not catch this one either. The user gets a 500 where the honest answer
     * is "no".
     *
     * Pinned as today's behaviour. Guarding on `exists()`, the way the other controllers
     * do, should break this test.
     */
    public function testAServiceThatNoLongerExistsFailsWithAPhpErrorInsteadOfAnsweringNo(): void {
        $this->expectException(\UnhandledMatchError::class);

        $this->testConnection(999999);
    }

    /**
     * The endpoint reads a row that holds a database password, and it is a way to probe
     * what the host can reach from inside the cluster. `PublicSurfaceTest` pins the table;
     * this proves the hook acts on this path, which carries an id and is not covered
     * there.
     */
    public function testTheEndpointDoesNotAnswerWithoutAToken(): void {
        $service = Fixtures::databaseService(['host' => 'no-such-host.invalid']);

        $this->expectException(UnauthorizedException::class);

        $this->get("database-services/{$service->id}/test-connection");
    }

    // <editor-fold desc="Helpers">

    /**
     * @return array<string, mixed> the decoded response
     */
    private function testConnection(int $id): array {
        $response = $this->signedIn()->get("database-services/{$id}/test-connection");

        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
