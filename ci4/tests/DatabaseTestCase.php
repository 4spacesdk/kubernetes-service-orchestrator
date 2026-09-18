<?php namespace App;

use OrmExtension\DataMapper\ModelDefinitionCache;

/**
 * Base for tests that need a real schema to read and write.
 *
 * The schema is built once, out of band, by pointing `php spark migrate` at the test
 * database. The test runner does not rebuild it: it migrates namespace by namespace in a
 * different order than spark does, and the auth extension's Upgrade_1_1_0 then fails on
 * oauth_access_tokens. Building it once also keeps the suite fast.
 *
 * Isolation is per test instead. This version of CodeIgniter's DatabaseTestTrait has no
 * transaction handling of its own - it relies on re-migrating between tests - so each
 * test runs inside a transaction that is rolled back afterwards. Without that, rows pile
 * up in the test database and tests start seeing each other's data.
 */
abstract class DatabaseTestCase extends TestCase {

    protected $refresh = false;
    protected $migrate = false;

    /**
     * Fail loudly when the test database is behind the migrations in the repository.
     *
     * The schema is built out of band, so nothing notices on its own when a migration is
     * added. Tests then keep passing against yesterday's schema until one of them fails
     * for a reason that has nothing to do with what it tests. This turns that into a
     * message that says what to do.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        self::clearTheModelCacheWhenTheRunEnds();

        $db = \Config\Database::connect('tests');

        $onDisk = glob(APPPATH . 'Database/Migrations/*.php') ?: [];
        $applied = (int)$db->table('migrations')
            ->where('namespace', 'App')
            ->countAllResults();

        if (count($onDisk) > $applied) {
            static::fail(sprintf(
                "The test database is %d migration(s) behind.\n"
                . "Run: npm run \"Test: setup database (run once)\"",
                count($onDisk) - $applied
            ));
        }

        // Every real installation has exactly one System row, and it has id 1 - the first
        // `System::Get()` on an empty table created it. A test database that has lost it
        // does not fail: `System::Get()` writes a fresh row on every call, hands back an
        // unconfigured System, and the row is rolled back with the test. Tests then run
        // against a state no installation is ever in, and quietly assert the wrong thing.
        if ($db->table('systems')->where('id', 1)->countAllResults() === 0) {
            static::fail(
                "The test database has no System row with id 1.\n"
                . "Run: npm run \"Test: setup database (run once)\""
            );
        }
    }

    public function setUp(): void {
        parent::setUp();

        $this->keepTheClusterOutOfIt();

        // Without this a failing write inside the transaction is only recorded, not thrown,
        // and a test goes green while nothing was written.
        $this->db->transException(true);
        $this->db->transBegin();
    }

    /**
     * No cluster, whatever the developer's `.env` says.
     *
     * `Deployment::checkStatus()` asks every step for its status, and a step answers that
     * by calling the cluster. It runs on the first line of `EmitTrigger()` - before the
     * check that stops a draft deployment - so any test that writes an environment
     * variable, a version or a volume reaches out over the network without meaning to.
     *
     * Measured at about four seconds across the api suite, and worse than the time: the
     * suite's result depended on a cluster being reachable, and on whose machine it ran.
     * Tests that want a cluster ask for one explicitly - see `IntegrationTestCase`, which
     * does not inherit this.
     *
     * `env()` reads $_ENV and $_SERVER before getenv(), so all three have to go.
     */
    protected function keepTheClusterOutOfIt(): void {
        putenv('KUBERNETES_AUTH=');
        $_ENV['KUBERNETES_AUTH'] = '';
        $_SERVER['KUBERNETES_AUTH'] = '';
    }

    public function tearDown(): void {
        $this->db->transRollback();
        parent::tearDown();
    }

    /**
     * Drop what the ORM learned about the schema when the run ends - once, not per class.
     *
     * ModelDefinitionCache caches a table's field list under the entity name alone, with no
     * database group in the key, and it always writes to WRITEPATH. A test run therefore
     * fills the application's own cache with the test database's columns, and the app then
     * queries the live database for columns only the test database has. A fresh migration
     * does not produce quite the same schema as one grown migration by migration, so the
     * two do differ.
     *
     * This used to run after every test class, which also threw away everything the run
     * had learned about its own schema: the next class re-read the field list for every
     * table it touched. Moving it to a shutdown function cut the database suite in half
     * and the api suite by a sixth, and the protection is the same - the cache is empty
     * when the process ends either way.
     *
     * A run killed outright leaves the cache behind. That was true before as well, for
     * anything killed between classes, and `php spark orm:clear:cache` is the way back.
     */
    private static bool $cacheClearScheduled = false;

    private static function clearTheModelCacheWhenTheRunEnds(): void {
        if (self::$cacheClearScheduled) {
            return;
        }
        self::$cacheClearScheduled = true;

        register_shutdown_function(static function (): void {
            ModelDefinitionCache::getInstance()->clearCache();
        });
    }

}
