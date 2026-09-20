<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\DatabaseService;
use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
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
        if (strlen($deployment->database_name) > 0
            && strlen($deployment->database_user) > 0
            && strlen($deployment->database_pass) > 0) {
            return DeploymentStepHelper::DatabaseStatus_Success;
        } else if (strlen($deployment->database_name) > 0
            || strlen($deployment->database_user) > 0
            || strlen($deployment->database_pass) > 0) {
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
     * The database a deployment gets, named after the namespace and the deployment it
     * belongs to.
     *
     * Out here rather than inside the method that connects, so that what a deployment is
     * about to be given can be asked for without a database server answering first.
     *
     * **Two deployments can be given the same name.** Everything outside `[A-Za-z0-9_]` is
     * dropped rather than replaced, so `my-app` and `my.app` in one namespace both become
     * `my_app`; and the whole thing is cut to 64 characters, so two long names that agree
     * for the first 64 collapse as well. Held as tests rather than fixed here - what to do
     * about it is its own decision.
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

        return strlen($name) > 64 ? substr($name, 0, 64) : $name;
    }

    /**
     * The user that owns it: the same name, cut to the 32 characters MySQL allows.
     *
     * Cut half as short as the database name, so two deployments share a user more easily
     * than they share a database - and a shared user is a login that can reach both.
     */
    public static function DatabaseUserFor(string $databaseName): string {
        return substr($databaseName, 0, 32);
    }

    /**
     * The password that user is created with.
     *
     * Thirteen characters: a leading `@`, which MSSQL's complexity rules want, and twelve
     * from the alphabet below. **`rand()` is not a cryptographic source** - it is seeded
     * from the process and its output is predictable to anyone who can watch enough of it.
     * That is a finding of its own; this is here so that what it produces can be looked at.
     */
    public static function GeneratePassword(): string {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $count = mb_strlen($chars);

        $password = "@"; // Must include @  for MSSQL
        for ($i = 0; $i < 12; $i++) {
            $index = rand(0, $count - 1);
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

        $dbName = self::DatabaseNameFor($deployment);
        $dbUser = self::DatabaseUserFor($dbName);
        $dbPass = self::GeneratePassword();

        $databaseService = new DatabaseService();
        $databaseService->find($deployment->database_service_id);

        $db = $databaseService->prepareConnection();

        switch ($databaseService->driver) {
            case \DatabaseDrivers::MySQL:
                $db->query("CREATE DATABASE IF NOT EXISTS {$dbName}");
                $db->query("CREATE USER IF NOT EXISTS '{$dbUser}' IDENTIFIED BY '{$dbPass}'");
                $db->query("GRANT ALL PRIVILEGES ON {$dbName}.* TO '{$dbUser}'@'%'");
                break;
            case \DatabaseDrivers::MSSQL:
                $forge = Database::forge($db);
                $forge->createDatabase($dbName);
                $db->setDatabase($dbName);
                $db->query("CREATE LOGIN [$dbUser] WITH PASSWORD = '$dbPass'");
                $db->query("CREATE USER [$dbUser] FOR LOGIN [$dbUser]");
                $db->query("EXEC sp_addrolemember 'db_owner', [$dbUser]");
                break;
        }

        $deployment->database_name = $dbName;
        $deployment->database_user = $dbUser;
        $deployment->database_pass = $dbPass;
        $deployment->save();
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
