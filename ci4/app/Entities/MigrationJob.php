<?php namespace App\Entities;

use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\Libraries\ZMQ\ChangeEvent;
use App\Libraries\ZMQ\Events;
use App\Libraries\ZMQ\ZMQProxy;
use DebugTool\Data;
use App\Core\Entity;
use \App\Libraries\DeploymentSteps\DeploymentStep;

/**
 * Class MigrationJob
 * @package App\Entities
 * @property int $deployment_id
 * @property Deployment $deployment
 * @property string $status
 * @property string $started
 * @property string $ended
 * @property string $log
 * @property string $image
 * @property string $command
 */
class MigrationJob extends Entity {

    public function rerun(): void {
        $deployment = new Deployment();
        $deployment->find($this->deployment_id);

        $migrationJobStep = new MigrationJobStep();
        $migrationJobStep->tryExecuteDeployCommand($deployment);
    }

    public function updateStatus(string $value): void {
        $this->status = $value;
        $this->save();

        $deployment = new Deployment();
        $deployment->find($this->deployment_id);

        switch ($value) {
            case \MigrationJobStatusTypes::Deploying:
                // Attach to deployment
                $deployment->last_migration_job_id = $this->id;
                $deployment->save();
                ZMQProxy::getInstance()->send(
                    Events::MigrationJob_Created(),
                    (new ChangeEvent(null, $this->toArray()))->toArray()
                );
                break;
            case \MigrationJobStatusTypes::Started:
            case \MigrationJobStatusTypes::Completed:
            case \MigrationJobStatusTypes::Failed_LogVerification:
            case \MigrationJobStatusTypes::Failed_PostCommands:
                break;
        }

        ZMQProxy::getInstance()->send(
            Events::MigrationJob_Changed_Status($this->id),
            (new ChangeEvent(null, $this->toArray()))->toArray()
        );
        ZMQProxy::getInstance()->send(
            Events::MigrationJob_Changed_Status(0),
            (new ChangeEvent(null, $this->toArray()))->toArray()
        );
    }

    public function validateLog(): void {
        $deployment = new Deployment();
        $deployment->find($this->deployment_id);
        $spec = $deployment->findDeploymentSpecification();
        if (!$spec->exists()) {
            $this->log .= "\nNo deployment spec found";
            $this->save();
            $this->updateStatus(\MigrationJobStatusTypes::Failed_LogVerification);
            return;
        }

        $isValid = false;
        switch ($spec->database_migration_verification_type) {
            case \MigrationVerificationTypes::EndsWith:
                $isValid = str_ends_with($this->log, $spec->database_migration_verification_value);
                break;
            case \MigrationVerificationTypes::Regex:
                $isValid = preg_match($this->log, $spec->database_migration_verification_value) == 1;
                break;
        }


        if ($isValid) {
            $spec->deployment_specification_post_commands->find();
            if ($spec->deployment_specification_post_commands->exists()) {

                try {
                    $deploymentStep = new DeploymentStep();
                    Data::debug('found', $spec->deployment_specification_post_commands->count(), 'commands to execute');
                    foreach ($spec->deployment_specification_post_commands as $postCommand) {
                        Data::debug($postCommand->command);
                        $commandOutputLines = $deploymentStep->executeCommand(
                            $deployment,
                            strlen($postCommand->container) ? $postCommand->container : $deployment->name,
                            [
                                '/bin/sh',
                                '-c',
                                $postCommand->command
                            ],
                            $postCommand->all_pods
                        );
                        Data::debug($commandOutputLines);
                    }
                    $this->updateStatus(\MigrationJobStatusTypes::Completed);
                } catch (\Exception $e) {
                    $this->log .= "\n{$e->getMessage()}";
                    $this->save();
                    $this->updateStatus(\MigrationJobStatusTypes::Failed_PostCommands);
                }
            } else {
                $this->updateStatus(\MigrationJobStatusTypes::Completed);
            }
        } else {
            $this->updateStatus(\MigrationJobStatusTypes::Failed_LogVerification);
        }
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|MigrationJob[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
