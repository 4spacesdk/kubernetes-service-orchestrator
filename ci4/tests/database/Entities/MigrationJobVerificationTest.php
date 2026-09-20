<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\MigrationJob;
use App\Fixtures;

/**
 * Whether a finished migration is read as having worked.
 *
 * The migration container posts its log to `/migration-jobs/{id}/ended`, and
 * `validateLog()` is what turns that log into `completed` or `failed-log-verification` -
 * and, on the way, decides whether the specification's post commands run at all. A
 * specification says how to read it: the log has to end with a given string, or match a
 * given pattern.
 *
 * The pattern half never worked. `preg_match()` was called with the log as the pattern and
 * the pattern as the subject, and a log does not start with a delimiter, so the call raised
 * `Delimiter must not be alphanumeric` - thrown here, not warned about. The request that
 * reports a finished migration answered with a server error, and the job kept the status it
 * already had: not completed, not failed, just waiting.
 */
class MigrationJobVerificationTest extends DatabaseTestCase {

    // <editor-fold desc="A pattern">

    public function testALogMatchingThePatternCompletesTheJob(): void {
        $job = $this->jobVerifiedBy('regex', 'Done\.$', "Migrating...\nDone.");

        $job->validateLog();

        $this->assertSame(\MigrationJobStatusTypes::Completed, $job->status);
    }

    public function testALogNotMatchingThePatternFailsTheJob(): void {
        $job = $this->jobVerifiedBy('regex', 'Done\.$', "Migrating...\nFailed.");

        $job->validateLog();

        $this->assertSame(\MigrationJobStatusTypes::Failed_LogVerification, $job->status);
    }

    /**
     * The value is stored without delimiters - that is what the field's hint asks for - so
     * kso puts them around it, and the one it picks has to be a character the pattern does
     * not use. A pattern matching a URL would otherwise end at its first slash, which is
     * not a refusal but a *different pattern*, silently.
     */
    public function testAPatternContainingASlashIsReadWhole(): void {
        $matches = $this->jobVerifiedBy('regex', 'https?://host/path$', 'hit https://host/path');
        $doesNot = $this->jobVerifiedBy('regex', 'https?://host/path$', 'hit https://host/elsewhere');

        $matches->validateLog();
        $doesNot->validateLog();

        $this->assertSame(\MigrationJobStatusTypes::Completed, $matches->status);
        $this->assertSame(\MigrationJobStatusTypes::Failed_LogVerification, $doesNot->status);
    }

    /**
     * A pattern nobody can read is an ordinary thing to type into a form, so it fails the
     * job and says why in the log - rather than raising out of the request and leaving the
     * job in neither state.
     */
    public function testAPatternThatCannotBeReadFailsTheJobAndSaysSo(): void {
        $job = $this->jobVerifiedBy('regex', '(unclosed', "Migrating...\nDone.");

        $job->validateLog();

        $this->assertSame(\MigrationJobStatusTypes::Failed_LogVerification, $job->status);
        $this->assertStringContainsString('not a regular expression', $job->log);
        $this->assertStringContainsString('(unclosed', $job->log);
    }

    // </editor-fold>

    // <editor-fold desc="The other kind, which always worked">

    public function testTheEndsWithVerificationIsUnchanged(): void {
        $ends = $this->jobVerifiedBy('ends-with', 'Done.', "Migrating...\nDone.");
        $doesNot = $this->jobVerifiedBy('ends-with', 'Done.', "Done.\nMigrating...");

        $ends->validateLog();
        $doesNot->validateLog();

        $this->assertSame(\MigrationJobStatusTypes::Completed, $ends->status);
        $this->assertSame(\MigrationJobStatusTypes::Failed_LogVerification, $doesNot->status);
    }

    // </editor-fold>

    /**
     * A job whose specification verifies the log the given way. Its own specification each
     * time: the verification is read off the deployment's specification, and these tests
     * differ in nothing else.
     */
    private function jobVerifiedBy(string $type, string $value, string $log): MigrationJob {
        $specification = Fixtures::deploymentSpecification([
            'database_migration_verification_type' => $type,
            'database_migration_verification_value' => $value,
        ]);

        $job = new MigrationJob();
        $job->deployment_id = Fixtures::deployment(['deployment_specification_id' => $specification->id])->id;
        $job->status = \MigrationJobStatusTypes::Started;
        $job->log = $log;
        $job->save();

        return $job;
    }

}
