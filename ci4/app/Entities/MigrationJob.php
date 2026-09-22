<?php namespace App\Entities;

use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\Libraries\Push\ChangeEvent;
use App\Libraries\Push\Events;
use App\Libraries\Push\Publisher;
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
 * @property string|null $callback_token_hash
 */
class MigrationJob extends Entity {

    public $hiddenFields = ['callback_token_hash'];

    /**
     * What the job's pod proves itself with when it reports that it started and ended.
     *
     * The two callbacks are public - the pod has no sign-in - and the ids are sequential, so
     * without it anyone who could reach the API could end a job with a log of their choosing,
     * and `validateLog()` would run the post-update commands in the live pods before the real
     * migration had finished. One token per job, handed to its pod through the job's Secret;
     * only its SHA-256 is stored, as for the password reset tokens.
     *
     * @return string the token, for the pod
     */
    public function issueCallbackToken(): string {
        $token = bin2hex(random_bytes(32));
        $this->callback_token_hash = hash('sha256', $token);
        $this->save();

        return $token;
    }

    /**
     * A job with no token - one started before there were tokens - accepts nothing.
     */
    public function acceptsCallbackToken(string $token): bool {
        $expected = (string) $this->callback_token_hash;

        return $expected !== '' && hash_equals($expected, hash('sha256', $token));
    }

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
                Publisher::getInstance()->send(
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

        Publisher::getInstance()->send(
            Events::MigrationJob_Changed_Status($this->id),
            (new ChangeEvent(null, $this->toArray()))->toArray()
        );
        Publisher::getInstance()->send(
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
                $match = $this->logMatches($spec->database_migration_verification_value);
                if (is_null($match)) {
                    $this->log .= "\nThe verification value is not a regular expression kso can read: {$spec->database_migration_verification_value}";
                    $this->save();
                    $this->updateStatus(\MigrationJobStatusTypes::Failed_LogVerification);
                    return;
                }
                $isValid = $match;
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
     * Whether the log matches this pattern, or null when the pattern cannot be read.
     *
     * Two things about it. **The arguments used to be the other way round** - the log was
     * handed to `preg_match()` as the pattern and the pattern as the subject - and a log
     * does not begin with a delimiter, so every migration verified by regex ended as
     * `preg_match(): Delimiter must not be alphanumeric` instead of as a verdict. That is
     * thrown here rather than warned about, so the request that reports a finished
     * migration answered with a server error and the job was left in the status it had:
     * neither completed nor failed, waiting for somebody to look.
     *
     * **And the stored value carries no delimiters.** The field's own hint asks for a bare
     * pattern - `(?:[a-z0-9\-]{0,61})?` - so one has to be put around it, and it has to be
     * a character the pattern does not use itself, or a pattern matching a path would end
     * at its first slash.
     */
    private function logMatches(string $pattern): ?bool {
        $delimiter = null;
        foreach (['/', '#', '~', '%', '!', '@', '|'] as $candidate) {
            if (!str_contains($pattern, $candidate)) {
                $delimiter = $candidate;
                break;
            }
        }
        if (is_null($delimiter)) {
            return null;
        }

        // Suppressed, and the return value read instead: a pattern somebody typed into a
        // form is an ordinary thing to get wrong, and a warning here is an exception that
        // leaves the job in no status at all.
        $result = @preg_match($delimiter . $pattern . $delimiter, $this->log);

        return $result === false ? null : $result === 1;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|MigrationJob[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
