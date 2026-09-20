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
     * The one case the endpoint exists for: a database that is there, reachable and
     * correctly configured answers yes.
     *
     * **It could not, for any MySQL service at all, however perfect its settings.**
     * `prepareConnection()` passed `'port' => $this->port`, and a property read off a row is
     * a string even where the column is an int. CodeIgniter's MySQLi driver is
     * `declare(strict_types=1)`, so `mysqli::real_connect()` rejected `"3306"` where it
     * wants `?int`, and `testConnection()` reported the TypeError as a failed connection -
     * so the button said no to everything, and `DatabaseStep` could not create a tenant's
     * database either.
     *
     * The settings come from the test run's own database configuration rather than being
     * written out here - they are the one database this test is certain to be able to
     * reach, and nothing in the assertion depends on what they are.
     */
    public function testAReachableDatabaseIsAnsweredWithYes(): void {
        $service = Fixtures::databaseService($this->theDatabaseThisTestRunUses());

        $this->assertTrue($this->testConnection($service->id)['resource']['value']);
    }

    /**
     * A port is what a string becomes when it is read off a row, and an int is what it is
     * when somebody just typed it into the form. Both are the same port.
     */
    public function testAPortIsTheSamePortWhicheverWayItArrives(): void {
        $settings = $this->theDatabaseThisTestRunUses();

        $asString = Fixtures::databaseService(array_merge($settings, ['port' => (string) $settings['port']]));
        $asInt = Fixtures::databaseService(array_merge($settings, ['port' => (int) $settings['port']]));

        $this->assertTrue($asString->testConnection(), 'the port as a string');
        $this->assertTrue($asInt->testConnection(), 'the port as an int');
    }

    /**
     * A service saved without a port is not a broken one - the column is nullable - and the
     * driver reads an unset port as "use the default", which for MySQL is the 3306 the
     * service was going to be on anyway. The cast must not turn "not set" into port zero,
     * which nobody is listening on.
     */
    public function testAServiceWithNoPortFallsBackToTheDriversDefault(): void {
        $service = Fixtures::databaseService(array_merge($this->theDatabaseThisTestRunUses(), ['port' => null]));

        $this->assertTrue($service->testConnection());
    }

    /**
     * An id that is not there answers no, like every other way of failing to connect.
     *
     * It is an ordinary case rather than a mistake: the row can be deleted between the list
     * loading and somebody pressing the button. It used to take the request down - the
     * lookup's empty entity reached `prepareConnection()` with no driver, `match` has no arm
     * for null, and `UnhandledMatchError` is an `\Error`, so `testConnection()`'s
     * `catch (\Exception|DatabaseException)` did not turn it into a `false` the way it turns
     * every other failure into one. The user got a 500 where the honest answer is "no".
     */
    public function testAServiceThatNoLongerExistsIsAnsweredWithNo(): void {
        $body = $this->testConnection(999999);

        $this->assertSame('OK', $body['status']);
        $this->assertFalse($body['resource']['value']);
    }

    /**
     * **Pinned, not endorsed.** The same 500, one step further in and still reachable: a
     * service whose stored `driver` is neither of the two kso knows takes the request down
     * on the same `match`.
     *
     * Not hypothetical - nothing validates the column. `POST /database_services` with
     * `"driver":"postgres"` is accepted and stored, and the button on that row is then a
     * 500 for any signed-in caller.
     *
     * The guard above cannot close this one; it is the `catch` that is too narrow, which is
     * a pattern with several sites and is worth fixing as one piece rather than here. Doing
     * that should break this test.
     */
    public function testAStoredDriverThatMatchesNothingStillTakesTheRequestDown(): void {
        $service = Fixtures::databaseService(['driver' => 'postgres']);

        $this->expectException(\UnhandledMatchError::class);

        $this->testConnection($service->id);
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
     * The one database a test run is certain to be able to reach: its own.
     *
     * @return array<string, mixed>
     */
    private function theDatabaseThisTestRunUses(): array {
        $database = config('Database')->tests;

        return [
            'driver' => \DatabaseDrivers::MySQL,
            'host' => $database['hostname'],
            'port' => $database['port'],
            'user' => $database['username'],
            'pass' => $database['password'],
        ];
    }

    /**
     * @return array<string, mixed> the decoded response
     */
    private function testConnection(int $id): array {
        $response = $this->signedIn()->get("database-services/{$id}/test-connection");

        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
