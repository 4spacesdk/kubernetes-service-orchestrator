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
     * @throws \Exception
     */
    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        if ($this->getStatus($deployment) == DeploymentStepHelper::DatabaseStatus_Success) {
            throw new \Exception('Database already created');
        }

        $fixer = function($name): string {
            $name = strtolower($name); // To lowercase
            $name = str_replace([' ', '-'], '_', $name); // Replace space with _
            $name = str_replace(',', '', $name); // Remove ,
            $name = preg_replace("/[^A-Za-z0-9_]/", '', $name); // Remove all non-alphabetic
            return $name;
        };
        $dbName =  "{$fixer($deployment->namespace)}_{$fixer($deployment->name)}";

        if (strlen($dbName) > 64) {
            $dbName = substr($dbName, 0, 64);
        }

        $dbUser = substr($dbName, 0, 32);

        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $count = mb_strlen($chars);
        $dbPass = "@"; // Must include @  for MSSQL
        for ($i = 0; $i < 12; $i++) {
            $index = rand(0, $count - 1);
            $dbPass .= mb_substr($chars, $index, 1);
        }

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
