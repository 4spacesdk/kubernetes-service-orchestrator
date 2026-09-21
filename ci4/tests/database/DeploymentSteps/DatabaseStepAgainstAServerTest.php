<?php namespace App\Tests\Database\DeploymentSteps;

use App\DatabaseTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\DatabaseStep;
use CodeIgniter\Database\Exceptions\DatabaseException;

/**
 * `startDeployCommand()` against a real MySQL server: the one the test database lives on.
 *
 * What it creates is a customer's database and a login to it, so the question worth asking
 * with a real server is whether a second deployment can ever be handed the first one's.
 * Everything made here is dropped again in `tearDown()`, whatever the test did.
 */
class DatabaseStepAgainstAServerTest extends DatabaseTestCase {

    /** @var string[] databases and users this test may have left on the server */
    private array $made = [];

    public function tearDown(): void {
        $server = $this->server();
        foreach ($this->made as $name) {
            $server->query("DROP DATABASE IF EXISTS {$name}");
            $server->query("DROP USER IF EXISTS '{$name}'");
        }
        $server->close();

        parent::tearDown();
    }

    public function testADeployCreatesTheDatabaseAndAUserThatCanReachIt(): void {
        $deployment = $this->deploymentOn($this->aService(), 'ksotest', 'first');

        (new DatabaseStep())->startDeployCommand($deployment);

        $this->assertSame('ksotest_first', $deployment->database_name);
        $this->assertTrue($this->databaseExists('ksotest_first'));
        $this->assertTrue($this->canSignIn('ksotest_first', $deployment->database_pass, 'ksotest_first'));
    }

    /**
     * `my-app` and `my app` are one plain name. The second gets the name with a hash on
     * the end instead of the first one's database.
     */
    public function testASecondDeploymentWithTheSameNameGetsItsOwnDatabase(): void {
        $service = $this->aService();
        $first = $this->deploymentOn($service, 'ksotest', 'my-app');
        $second = $this->deploymentOn($service, 'ksotest', 'my app');

        (new DatabaseStep())->startDeployCommand($first);
        (new DatabaseStep())->startDeployCommand($second);

        $this->made[] = $second->database_name;
        $this->made[] = $second->database_user;
        $this->assertSame($second->database_name, $second->database_user, 'short enough to be its own user, with the suffix once');
        $this->assertNotSame($first->database_name, $second->database_name);
        $this->assertNotSame($first->database_user, $second->database_user);
        $this->assertFalse($this->canSignIn($second->database_user, $second->database_pass, $first->database_name));
    }

    /**
     * A database that is already on the server and that kso does not know about - left by a
     * deployment since deleted, or made by hand - is refused, not handed over. And nothing
     * is left behind by the refusal.
     */
    public function testADatabaseAlreadyOnTheServerIsRefusedAndNothingIsLeftBehind(): void {
        $this->server()->query('CREATE DATABASE ksotest_taken');
        $this->made[] = 'ksotest_taken';
        $deployment = $this->deploymentOn($this->aService(), 'ksotest', 'taken');

        try {
            (new DatabaseStep())->startDeployCommand($deployment);
            $this->fail('an existing database was handed to a new deployment');
        } catch (DatabaseException $e) {
            $this->assertStringContainsString('exists', $e->getMessage());
        }

        $this->assertFalse($this->userExists('ksotest_taken'));
        $stored = new Deployment();
        $stored->find($deployment->id);
        $this->assertSame('', (string) $stored->database_name);
    }

    /**
     * A user already on the server is refused too, and the database made just before it is
     * dropped again.
     */
    public function testAUserAlreadyOnTheServerIsRefusedAndTheDatabaseIsDroppedAgain(): void {
        $this->server()->query("CREATE USER 'ksotest_user' IDENTIFIED BY 'irrelevant-1A'");
        $this->made[] = 'ksotest_user';
        $deployment = $this->deploymentOn($this->aService(), 'ksotest', 'user');

        try {
            (new DatabaseStep())->startDeployCommand($deployment);
            $this->fail('an existing user was handed to a new deployment');
        } catch (DatabaseException) {
            // What we came for.
        }

        $this->assertFalse($this->databaseExists('ksotest_user'));
    }

    // <editor-fold desc="Fixtures">

    private function aService() {
        return Fixtures::databaseService([
            'host' => getenv('database.tests.hostname') ?: 'db',
            'port' => (int) (getenv('database.tests.port') ?: 3306),
            'user' => getenv('database.tests.username') ?: 'root',
            'pass' => getenv('database.tests.password') ?: 'root',
        ]);
    }

    private function deploymentOn($service, string $namespace, string $name): Deployment {
        $deployment = Fixtures::deployment([
            'database_service_id' => $service->id,
            'namespace' => $namespace,
            'name' => $name,
            'database_name' => '',
            'database_user' => '',
            'database_pass' => '',
        ]);
        $this->made[] = DatabaseStep::DatabaseNameFor($deployment);

        return $deployment;
    }

    private function server(): \CodeIgniter\Database\BaseConnection {
        return \Config\Database::connect([
            'DSN' => '',
            'hostname' => getenv('database.tests.hostname') ?: 'db',
            'username' => getenv('database.tests.username') ?: 'root',
            'password' => getenv('database.tests.password') ?: 'root',
            'database' => '',
            'DBDriver' => 'MySQLi',
            'port' => (int) (getenv('database.tests.port') ?: 3306),
            'DBDebug' => false,
            'charset' => 'utf8mb4',
            'DBCollat' => 'utf8mb4_general_ci',
        ], false);
    }

    private function databaseExists(string $name): bool {
        return $this->server()->query('SHOW DATABASES LIKE ' . $this->db->escape($name))->getNumRows() > 0;
    }

    private function userExists(string $name): bool {
        return $this->server()->query('SELECT 1 FROM mysql.user WHERE user = ' . $this->db->escape($name))->getNumRows() > 0;
    }

    private function canSignIn(string $user, string $password, string $database): bool {
        $mysqli = mysqli_init();
        try {
            return @$mysqli->real_connect(
                getenv('database.tests.hostname') ?: 'db',
                $user,
                $password,
                $database,
                (int) (getenv('database.tests.port') ?: 3306)
            ) === true;
        } catch (\mysqli_sql_exception) {
            return false;
        }
    }

    // </editor-fold>

}
