<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\DatabaseService;
use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;

class DatabaseStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::Database;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Deployment;
    }

    public function getName(): string {
        return 'Database';
    }

    public function getTriggers(): array {
        return [

        ];
    }

    public function hasPreviewCommand(): bool {
        return false;
    }

    public function hasStatusCommand(): bool {
        return true;
    }

    public function hasDeployCommand(): bool {
        return true;
    }

    public function hasTerminateCommand(): bool {
        return false;
    }

    public function hasKubernetesEvents(): bool {
        return false;
    }

    public function hasKubernetesStatus(): bool {
        return false;
    }

    public function getSuccessStatus(Deployment $deployment): string {
        return DeploymentStepHelper::DatabaseStatus_Success;
    }

    public function getPreview(Deployment $deployment): string {
        return '';
    }

    public function getStatus(Deployment $deployment): string {
        // Cast: `database_pass` is nullable since it was encrypted at rest, and a deployment
        // whose database has not been made yet has null rather than the empty string the
        // column used to be stuck with. `strlen(null)` is a deprecation, which this suite
        // turns into a failed test and production turns into a log line nobody reads.
        if (strlen((string) $deployment->database_name) > 0
            && strlen((string) $deployment->database_user) > 0
            && strlen((string) $deployment->database_pass) > 0) {
            return DeploymentStepHelper::DatabaseStatus_Success;
        } else if (strlen((string) $deployment->database_name) > 0
            || strlen((string) $deployment->database_user) > 0
            || strlen((string) $deployment->database_pass) > 0) {
            return DeploymentStepHelper::DatabaseStatus_Failed;
        } else {
            return DeploymentStepHelper::DatabaseStatus_NotPerformed;
        }
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (!$deployment->database_service_id) {
            return 'Missing database service';
        }
        $databaseService = new DatabaseService();
        $databaseService->find($deployment->database_service_id);
        if (!$databaseService->exists()) {
            return 'Database service no longer exists';
        }
        return null;
    }

    /**
     * MySQL's limits. MSSQL allows more, and the shorter of the two is what both get.
     */
    private const int MaxDatabaseName = 64;
    private const int MaxUserName = 32;

    /**
     * The database a deployment gets, named after the namespace and the deployment it
     * belongs to.
     *
     * Out here rather than inside the method that connects, so that what a deployment is
     * about to be given can be asked for without a database server answering first.
     *
     * Everything outside `[A-Za-z0-9_]` is dropped or replaced, so this alone does not make
     * the name unique - `my-app` and `my app` in one namespace are both `acme_my_app`.
     * `AvailableNamesFor()` is what a deploy uses, and it steps aside from a name taken.
     *
     * A name too long for the limit is cut and ends in a hash of the whole thing. It used to
     * be cut only, so two long names that agreed for the first 64 characters were one
     * database.
     */
    public static function DatabaseNameFor(Deployment $deployment): string {
        $fixer = function($name): string {
            $name = strtolower($name); // To lowercase
            $name = str_replace([' ', '-'], '_', $name); // Replace space with _
            $name = str_replace(',', '', $name); // Remove ,
            $name = preg_replace("/[^A-Za-z0-9_]/", '', $name); // Remove all non-alphabetic
            return $name;
        };

        $name = "{$fixer($deployment->namespace)}_{$fixer($deployment->name)}";

        return self::FitTo($name, self::MaxDatabaseName, "{$deployment->namespace}/{$deployment->name}");
    }

    /**
     * The user that owns it: the database name, fitted to the 32 characters MySQL allows the
     * same way. It used to be cut only, and at half the length of the database name, so two
     * deployments shared a user far more easily than a database - and a shared user is a
     * login that can reach both.
     */
    public static function DatabaseUserFor(string $databaseName): string {
        return self::FitTo($databaseName, self::MaxUserName, $databaseName);
    }

    /**
     * The name and user a deploy creates: the plain ones, or - when another deployment on the
     * same database service already has either - the same with a hash of this deployment's
     * namespace and name on the end. Two deployments are never handed the same database or
     * the same user by kso.
     *
     * Only what kso knows about. A database or user that exists on the server some other way
     * is refused by the server when the deploy tries to create it.
     *
     * @return array{0: string, 1: string} the database name and the user
     * @throws \Exception when even the suffixed names are taken
     */
    public static function AvailableNamesFor(Deployment $deployment): array {
        $name = self::DatabaseNameFor($deployment);
        $user = self::DatabaseUserFor($name);
        if (!self::IsTakenByAnotherDeployment($deployment, $name, $user)) {
            return [$name, $user];
        }

        $identity = "{$deployment->namespace}/{$deployment->name}#{$deployment->id}";
        $suffix = '_' . substr(sha1($identity), 0, 8);
        $name = substr($name, 0, self::MaxDatabaseName - strlen($suffix)) . $suffix;
        $user = strlen($name) <= self::MaxUserName
            ? $name
            : substr($name, 0, self::MaxUserName - strlen($suffix)) . $suffix;
        if (!self::IsTakenByAnotherDeployment($deployment, $name, $user)) {
            return [$name, $user];
        }

        throw new \Exception("The database name '{$name}' is taken by another deployment");
    }

    private static function IsTakenByAnotherDeployment(Deployment $deployment, string $name, string $user): bool {
        return \Config\Database::connect()->table('deployments')
            ->where('database_service_id', $deployment->database_service_id)
            ->where('id !=', (int) $deployment->id)
            ->groupStart()
                ->where('database_name', $name)
                ->orWhere('database_user', $user)
            ->groupEnd()
            ->countAllResults() > 0;
    }

    /**
     * `$name` as it is when it fits; otherwise cut, with an underscore and eight characters
     * of a hash of `$identity` on the end, so two long names that share a beginning stay two.
     */
    private static function FitTo(string $name, int $length, string $identity): string {
        if (strlen($name) <= $length) {
            return $name;
        }

        return substr($name, 0, $length - 9) . '_' . substr(sha1($identity), 0, 8);
    }

    /**
     * The password that user is created with.
     *
     * Thirteen characters: a leading `@`, which MSSQL's complexity rules want, and twelve
     * from the alphabet below.
     *
     * **`random_int()`, not `rand()`.** This is the password to a customer's database, and
     * `rand()` is a Mersenne Twister seeded from the process: its whole state can be
     * recovered from enough of its output, and a rough idea of when a deployment was created
     * narrows the seed to something searchable. The two are one character apart to write and
     * nothing alike to attack. `random_int()` takes its bytes from the operating system, and
     * throws rather than falling back on a weaker source when it cannot.
     *
     * The alphabet is unchanged. Twelve characters out of sixty-two is about seventy-one
     * bits, which is not the weak part of this and was not worth changing along with it.
     *
     * @throws \Random\RandomException when the system has no randomness to give
     */
    public static function GeneratePassword(): string {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $count = mb_strlen($chars);

        $password = "@"; // Must include @  for MSSQL
        for ($i = 0; $i < 12; $i++) {
            $index = random_int(0, $count - 1);
            $password .= mb_substr($chars, $index, 1);
        }

        return $password;
    }

    /**
     * @throws \Exception
     */
    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        if ($this->getStatus($deployment) == DeploymentStepHelper::DatabaseStatus_Success) {
            throw new \Exception('Database already created');
        }

        [$dbName, $dbUser] = self::AvailableNamesFor($deployment);
        $dbPass = self::GeneratePassword();

        $databaseService = new DatabaseService();
        $databaseService->find($deployment->database_service_id);

        $db = $databaseService->prepareConnection();

        // Without `IF NOT EXISTS`: a database or user that is already there belongs to
        // somebody, and it used to be handed to this deployment - the database with its data,
        // the user with a grant to this database too, and the password kso stored for it not
        // the one the user has. The server refusing is the answer.
        //
        // Each statement is checked, because outside development CodeIgniter answers a failed
        // query with `false` rather than an exception. What was created before a refusal is
        // removed again, so a failed deploy leaves nothing behind for the next one to trip on.
        $undo = [];
        try {
            switch ($databaseService->driver) {
                case \DatabaseDrivers::MySQL:
                    self::Run($db, "CREATE DATABASE {$dbName}");
                    $undo[] = "DROP DATABASE {$dbName}";
                    self::Run($db, "CREATE USER '{$dbUser}' IDENTIFIED BY '{$dbPass}'");
                    $undo[] = "DROP USER '{$dbUser}'";
                    self::Run($db, "GRANT ALL PRIVILEGES ON {$dbName}.* TO '{$dbUser}'@'%'");
                    break;
                case \DatabaseDrivers::MSSQL:
                    self::Run($db, "CREATE DATABASE [{$dbName}]");
                    $undo[] = "USE [master]; DROP DATABASE [{$dbName}]";
                    self::Run($db, "CREATE LOGIN [{$dbUser}] WITH PASSWORD = '{$dbPass}'");
                    $undo[] = "USE [master]; DROP LOGIN [{$dbUser}]";
                    $db->setDatabase($dbName);
                    self::Run($db, "CREATE USER [{$dbUser}] FOR LOGIN [{$dbUser}]");
                    self::Run($db, "EXEC sp_addrolemember 'db_owner', [{$dbUser}]");
                    break;
            }
        } catch (\Throwable $e) {
            foreach (array_reverse($undo) as $statement) {
                try {
                    $db->query($statement);
                } catch (\Throwable) {
                    // The refusal is what the caller needs to hear about.
                }
            }
            throw $e;
        }

        $deployment->database_name = $dbName;
        $deployment->database_user = $dbUser;
        $deployment->database_pass = $dbPass;
        $deployment->save();
    }

    /**
     * @throws DatabaseException with the server's own reason
     */
    private static function Run(BaseConnection $db, string $sql): void {
        if ($db->query($sql) === false) {
            $error = $db->error();
            throw new DatabaseException((string) ($error['message'] ?? 'The database server refused the statement'));
        }
    }

    public function startTerminateCommand(Deployment $deployment): void {
        throw new \Exception('Database cannot be terminated');
    }

    public function getKubernetesEvents(Deployment $deployment): array {
        return [];
    }

    public function getKubernetesStatus(Deployment $deployment): array {
        return [];
    }

}
