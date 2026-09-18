<?php namespace App\Tests\Unit\Config;

use CodeIgniter\Session\Handlers\DatabaseHandler;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Session as SessionConfig;

/**
 * The session handler has to be one the framework will actually build.
 *
 * CodeIgniter 4.7 added a check to `Services::session()`: with the database handler it
 * reads the connection's platform and accepts only the literal strings "MySQLi" and
 * "Postgre". `getPlatform()` returns whatever `DBDriver` is set to, and OrmExtension sets
 * that to its own namespaced connection class - so the default group fails the check and
 * no session can be created at all. Every page that touches a session, the sign-in form
 * included, died on an InvalidArgumentException after the upgrade.
 *
 * Sessions therefore use a database group of their own, pointed at the same database with
 * the framework's own driver.
 *
 * **No feature test can catch this.** CodeIgniter's test harness substitutes its own
 * session, so the real handler is never built during a test run - which is how a suite of
 * green tests sat alongside a sign-in page that would not load. The check has to be made
 * against the configuration itself, and that is what this does. It reads the configured
 * driver rather than opening a connection, which is the same value `getPlatform()` returns.
 */
class SessionHandlerIsUsableTest extends CIUnitTestCase {

    public function testTheSessionDatabaseGroupUsesADriverTheFrameworkAccepts(): void {
        $session = config(SessionConfig::class);

        if ($session->driver !== DatabaseHandler::class) {
            $this->markTestSkipped('Sessions are not stored in the database.');
        }

        $database = config('Database');

        // Not `defaultGroup`: Config\Database rewrites that to 'tests' while the suite is
        // running, so reading it here would check the test connection and let the
        // production one through. Production always falls back to 'default'.
        $group = $session->DBGroup ?? 'default';

        $this->assertTrue(
            property_exists($database, $group),
            "Config\\Session points at the database group '{$group}', which does not exist."
        );

        $this->assertContains(
            $database->{$group}['DBDriver'],
            ['MySQLi', 'Postgre'],
            "Services::session() only builds a handler for MySQLi and Postgre, and the "
            . "'{$group}' group uses '{$database->{$group}['DBDriver']}'. Sessions will "
            . 'throw on every request that needs one.'
        );
    }

    /**
     * The point of the separate group is that it is the same database - a session written
     * by one connection has to be readable by the other.
     */
    public function testTheSessionGroupPointsAtTheSameDatabaseAsTheApplication(): void {
        $session = config(SessionConfig::class);

        if ($session->driver !== DatabaseHandler::class || $session->DBGroup === null) {
            $this->markTestSkipped('Sessions share the default connection.');
        }

        $database = config('Database');
        $sessions = $database->{$session->DBGroup};

        foreach (['hostname', 'database', 'username', 'port'] as $setting) {
            $this->assertSame(
                $database->default[$setting],
                $sessions[$setting],
                "The sessions group has a different {$setting} than the application."
            );
        }
    }

}
