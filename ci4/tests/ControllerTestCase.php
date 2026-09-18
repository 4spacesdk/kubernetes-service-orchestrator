<?php namespace App;

use CodeIgniter\Config\Services;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Base for tests that send a real request through the whole stack.
 *
 * Routing, filters, the RestExtension authorization hook, the controller and the response
 * all run. That is the point: SEC-1 was not a wrong value in a manifest, it was an
 * endpoint answering a request it should have refused, and only a real request sees that.
 *
 * Four things have to be arranged first, and none of them is obvious.
 *
 * **The routes live in a database table.** `api_routes` is read by RestExtension's
 * `pre_system` hook. `TestCase::setUp()` fires that event, but `CIUnitTestCase::setUp()`
 * runs afterwards and resets the services - including the route collection - so it has to
 * fire again here, once the reset is done.
 *
 * **Both extensions have their own database group.** RestExtension reads `api_routes` and
 * AuthExtension reads the OAuth tables, and both default to `default` - the live database.
 * They are pointed at the test database here, after the reset, or a test run reads
 * production configuration to decide what it is allowed to do.
 *
 * **The OAuth storage is a PDO connection of its own.** It cannot see anything written
 * inside the test's transaction, and under REPEATABLE READ it cannot see rows another
 * connection commits after that transaction opened either. The sign-in fixtures are
 * therefore written once per class, committed, before any transaction exists - see
 * `signInFixtures()`. Everything else a test writes stays transactional and is rolled
 * back as usual.
 *
 * **Token expiry round-trips through a MySQL timestamp column** and comes back shifted by
 * the session time zone, so a token with an hour to live can read as already expired. The
 * fixture token is given a day, which is longer than any plausible skew.
 *
 * The request pipeline also reads the host from `$_SERVER`, and nothing sets it on the
 * command line.
 */
abstract class ControllerTestCase extends DatabaseTestCase {

    use FeatureTestTrait;

    /**
     * Per process, not per suite.
     *
     * These used to be three fixed strings, which was fine for one phpunit at a time and
     * wrong the moment there were two: the fixtures live in the shared test database and
     * are written and deleted per test class, so a second run tore down the first run's
     * token mid-request and tests failed with `The access token provided is invalid`. The
     * pid makes each run's fixtures its own.
     *
     * What the fixed names bought was that a run which died before its teardown was
     * overwritten by the next one instead of accumulating. `sweepFixturesOfDeadRuns()`
     * buys that back.
     */
    private const PREFIX = 'phpunit-';

    private static string $token = '';
    private static string $client = '';
    private static string $username = '';

    private static int $userId = 0;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        self::$token = self::PREFIX . getmypid() . '-access-token';
        self::$client = self::PREFIX . getmypid();
        self::$username = self::PREFIX . getmypid();

        self::sweepFixturesOfDeadRuns();
        self::$userId = self::signInFixtures();
    }

    public static function tearDownAfterClass(): void {
        self::removeSignInFixtures();

        parent::tearDownAfterClass();
    }

    public function setUp(): void {
        parent::setUp();

        $_SERVER['HTTP_HOST'] = 'kso.test';

        config('RestExtension')->databaseGroupName = $this->DBGroup;
        config('AuthExtension')->dbGroupName = $this->DBGroup;

        $this->forgetTheLastRequest();
        $this->forgetTheListenersFromTheLastTest();

        Events::trigger('pre_system');
    }

    /**
     * Drop the listeners the previous `pre_system` registered, so this one replaces them
     * rather than adding to them.
     *
     * `RestExtension\Hooks::preSystem()` subscribes to `post_controller_constructor` and
     * `post_system` every time it runs, and nothing unsubscribes. Firing the event once
     * per test therefore left four and three more listeners behind per test - every one of
     * them doing the full authorization check and access-log write on every request that
     * followed. By the end of the suite a single request ran three hundred of them.
     *
     * It is why a test file took five times as long inside the suite as it did on its own,
     * and why the cost grew with position rather than with what the test did.
     */
    private function forgetTheListenersFromTheLastTest(): void {
        Events::removeAllListeners('post_controller_constructor');
        Events::removeAllListeners('post_system');
    }

    /**
     * Drop everything the previous request left behind in process-global state.
     *
     * Two singletons outlive a request here, and in production that does not matter because
     * a request is a process. In one test process it does:
     *
     * - `RestRequest` remembers who the last request was made by, so an authenticated test
     *   leaves its user behind and a later unauthenticated one is quietly treated as signed
     *   in - which changes what `/settings` returns.
     * - `DebugTool\Data` is the bag the whole response envelope is built from, status and
     *   resource included, so a failed request can answer with the previous request's
     *   resource still attached.
     * - The `response` service is one object for the whole process, so the headers one
     *   request set are still on it when the next one runs. A test asserting that a
     *   request did *not* redirect otherwise has that question answered by whichever test
     *   happened to run before it.
     *
     * Any one of them makes the suite order-dependent, which is worse than a failing test.
     *
     * `protected` rather than `private` because a test that sends more than one request
     * needs this between them too - and the failure when it does not is a passing test,
     * not a failing one. `RestGetSweepTest` sends twenty-two requests in a row, and without
     * a reset the one endpoint that never sets `count` was handed the previous endpoint's
     * and looked correct.
     */
    protected function forgetTheLastRequest(): void {
        $restRequest = (new \ReflectionClass(\RestExtension\RestRequest::class))->getProperty('instance');
        $restRequest->setValue(null, null);

        $store = (new \ReflectionClass(\DebugTool\Data::class))->getProperty('store');
        $store->setValue(null, ['status' => null]);

        Services::resetSingle('response');

        // `Login::index()` branches on the $_POST superglobal rather than on the request,
        // and the test harness does not clear it between calls. Without this, a GET after
        // a POST takes the sign-in branch with no credentials in it.
        $_POST = [];
        $_GET = [];
    }

    public function tearDown(): void {
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_AUTHORIZATION']);

        parent::tearDown();
    }

    /**
     * Send the following requests as a signed-in user.
     *
     * One user per test class, shared by every test in it. It is not rolled back with the
     * rest of the fixtures, so a test that cares which user it is should not change this
     * one - make its own and look at that instead.
     */
    protected function signedIn(): static {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . self::$token;

        return $this;
    }

    /**
     * The id of the user `signedIn()` acts as.
     */
    protected function signedInUserId(): int {
        return self::$userId;
    }

    /**
     * An OAuth client, a user and a non-expiring access token, committed.
     *
     * Written on the shared connection before any test transaction has opened, which is
     * the only way the OAuth storage's own connection can see them.
     */
    private static function signInFixtures(): int {
        $db = \Config\Database::connect('tests');

        self::removeSignInFixtures();

        $db->table('oauth_clients')->replace([
            'client_id' => self::$client,
            'client_secret' => '',
            'redirect_uri' => '',
            'grant_types' => 'client_credentials',
            'scope' => '',
            'user_id' => '',
        ]);

        $db->table('users')->insert([
            'username' => self::$username,
            'first_name' => 'PHP',
            'last_name' => 'Unit',
            'password' => '',
            'renew_password' => 0,
            'mfa_secret_hash' => '',
            'created' => date('Y-m-d H:i:s'),
        ]);
        $userId = (int) $db->insertID();

        $db->table('oauth_access_tokens')->insert([
            'access_token' => self::$token,
            'client_id' => self::$client,
            'user_id' => (string) $userId,
            'expires' => date('Y-m-d H:i:s', time() + DAY),
            'scope' => null,
        ]);

        return $userId;
    }

    private static function removeSignInFixtures(): void {
        $db = \Config\Database::connect('tests');

        $db->table('oauth_access_tokens')->where('access_token', self::$token)->delete();
        $db->table('users')->where('username', self::$username)->delete();
        $db->table('oauth_clients')->where('client_id', self::$client)->delete();
    }

    /**
     * Delete the fixtures of runs that are no longer running.
     *
     * A run killed between `setUpBeforeClass()` and `tearDownAfterClass()` - ^C, a fatal,
     * a container restart - leaves its three rows behind, and with a pid in the name
     * nothing would ever overwrite them. Each leftover client id carries the pid that
     * wrote it, so the ones belonging to a process that is gone can be removed; anything
     * belonging to a live pid is another run in flight and is left alone.
     */
    private static function sweepFixturesOfDeadRuns(): void {
        $db = \Config\Database::connect('tests');

        $clients = $db->table('oauth_clients')
            ->like('client_id', self::PREFIX, 'after')
            ->get()
            ->getResultArray();

        foreach ($clients as $client) {
            $pid = (int) substr($client['client_id'], strlen(self::PREFIX));
            if ($pid === 0 || posix_kill($pid, 0)) {
                continue;
            }

            $db->table('oauth_access_tokens')->where('client_id', $client['client_id'])->delete();
            $db->table('users')->where('username', $client['client_id'])->delete();
            $db->table('oauth_clients')->where('client_id', $client['client_id'])->delete();
        }
    }

}
