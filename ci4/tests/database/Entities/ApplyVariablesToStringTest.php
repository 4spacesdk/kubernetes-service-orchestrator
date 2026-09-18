<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\Deployment;
use App\Entities\EnvironmentVariable;
use App\Fixtures;

/**
 * The placeholder substitution every deployment step runs its strings through.
 *
 * Sixteen tokens, resolved by sixteen `str_replace` calls in a row. It is how one
 * specification serves every workspace: a command, an environment variable, a CSI volume
 * handle or a hand-written custom resource is written once with `${namespace}` in it and
 * comes out different per deployment.
 *
 * Five of the tokens resolve to credentials - the database password, the mail password -
 * so a wrong mapping does not produce a visible error. It produces a workspace that quietly
 * connects with someone else's details, or a password written into a manifest that was
 * meant to hold a username.
 */
class ApplyVariablesToStringTest extends DatabaseTestCase {

    // <editor-fold desc="Every token, one at a time">

    public function testTheDeploymentsOwnFieldsAreSubstituted(): void {
        $deployment = $this->fullyWiredDeployment();

        $this->assertSame($deployment->namespace, $this->apply('${namespace}', $deployment));
        $this->assertSame($deployment->name, $this->apply('${deployment.name}', $deployment));
    }

    /**
     * The migration job is named after the deployment, so these two tokens are the same
     * value. Worth knowing before anyone relies on them differing.
     */
    public function testTheMigrationJobNameIsTheDeploymentName(): void {
        $deployment = $this->fullyWiredDeployment();

        $this->assertSame(
            $this->apply('${deployment.name}', $deployment),
            $this->apply('${migration.job.name}', $deployment)
        );
    }

    public function testTheDatabaseTokensResolveToTheServiceAndTheDeployment(): void {
        $deployment = $this->fullyWiredDeployment();

        $this->assertSame('db.test', $this->apply('${database.host}', $deployment));
        $this->assertSame('3306', $this->apply('${database.port}', $deployment));
        $this->assertSame('tenant_db', $this->apply('${database.name}', $deployment));
        $this->assertSame('tenant_user', $this->apply('${database.user}', $deployment));
        $this->assertSame('tenant-secret', $this->apply('${database.pass}', $deployment));
    }

    /**
     * Name, user and password live on the deployment; host and port on the shared service.
     * Mixing the two up is the mistake this guards: every workspace on one server shares
     * the host, and none of them shares the password.
     */
    public function testTheDatabaseCredentialsComeFromTheDeploymentNotTheSharedService(): void {
        $deployment = $this->fullyWiredDeployment([
            'database_name' => 'only-mine',
            'database_user' => 'only-me',
            'database_pass' => 'only-my-secret',
        ]);

        $this->assertSame('only-mine', $this->apply('${database.name}', $deployment));
        $this->assertSame('only-me', $this->apply('${database.user}', $deployment));
        $this->assertSame('only-my-secret', $this->apply('${database.pass}', $deployment));
    }

    public function testTheMailTokensResolveToTheWorkspacesEmailService(): void {
        $deployment = $this->fullyWiredDeployment();

        $this->assertSame('smtp.test', $this->apply('${emailService.host}', $deployment));
        $this->assertSame('587', $this->apply('${emailService.port}', $deployment));
        $this->assertSame('mailer', $this->apply('${emailService.user}', $deployment));
        $this->assertSame('mail-secret', $this->apply('${emailService.pass}', $deployment));
    }

    /**
     * The one token whose name does not match its column: `sender` reads `from`.
     */
    public function testTheSenderTokenReadsTheFromAddress(): void {
        $deployment = $this->fullyWiredDeployment();

        $this->assertSame('noreply@test.example.org', $this->apply('${emailService.sender}', $deployment));
    }

    public function testTheDomainTokenIsTheWorkspacesDomainName(): void {
        $deployment = $this->fullyWiredDeployment();

        $this->assertSame('test.example.org', $this->apply('${domain.host}', $deployment));
    }

    /**
     * Two of the three workspace tokens say what they mean. `${workspace.name}` does not:
     * it resolves to the workspace's **namespace**, not its name. Anyone writing a
     * specification would expect otherwise, so it is pinned rather than left to be
     * discovered.
     */
    public function testWorkspaceNameIsActuallyTheNamespace(): void {
        $workspace = Fixtures::workspace([
            'name_readable' => 'Acme Industries',
            'name_system' => 'acme-industries',
            'namespace' => 'acme-namespace',
            'subdomain' => 'acme',
        ]);
        $deployment = Fixtures::deployment(['workspace_id' => $workspace->id]);

        $this->assertSame('acme-namespace', $this->apply('${workspace.name}', $deployment));
        $this->assertSame('acme', $this->apply('${workspace.subdomain}', $deployment));
        $this->assertSame((string) $workspace->id, $this->apply('${workspace.id}', $deployment));
    }

    // </editor-fold>

    // <editor-fold desc="How the substitution behaves">

    public function testTextAroundAndBetweenTokensIsLeftAlone(): void {
        $deployment = $this->fullyWiredDeployment();

        $this->assertSame(
            "mysql://tenant_user:tenant-secret@db.test:3306/tenant_db",
            $this->apply('mysql://${database.user}:${database.pass}@${database.host}:${database.port}/${database.name}', $deployment)
        );
    }

    public function testATokenIsReplacedEverywhereItAppears(): void {
        $deployment = $this->fullyWiredDeployment();

        $this->assertSame(
            "{$deployment->namespace}/{$deployment->namespace}",
            $this->apply('${namespace}/${namespace}', $deployment)
        );
    }

    /**
     * Anything that is not one of the sixteen is left as written, rather than emptied.
     * A typo in a specification therefore shows up in the manifest as itself, which is
     * the more findable of the two options.
     */
    public function testAnUnknownTokenIsLeftUntouched(): void {
        $deployment = $this->fullyWiredDeployment();

        $this->assertSame('${database.hostname}', $this->apply('${database.hostname}', $deployment));
        $this->assertSame('${nonsense}', $this->apply('${nonsense}', $deployment));
    }

    public function testAnEmptyStringStaysEmpty(): void {
        $this->assertSame('', $this->apply('', $this->fullyWiredDeployment()));
    }

    /**
     * The replacements run in a fixed order over the result of the previous one, so a
     * stored value containing a token that is resolved *later* is itself expanded.
     *
     * Here the database name holds `${deployment.name}`, and `${database.name}` is
     * substituted before `${deployment.name}` is - so the second pass expands what the
     * first pass inserted. Stored data becomes template source. Nothing in the system puts
     * a token in a credential today, but it is worth knowing the door is open. Tokens
     * resolved earlier, such as `${namespace}`, are not expanded this way.
     */
    public function testAStoredValueContainingALaterTokenIsItselfExpanded(): void {
        $deployment = $this->fullyWiredDeployment(['database_name' => 'db_${deployment.name}']);

        $this->assertSame("db_{$deployment->name}", $this->apply('${database.name}', $deployment));
    }

    // </editor-fold>

    // <editor-fold desc="When the relations are missing">

    /**
     * A deployment without a database service still has to produce a string. The tokens
     * resolve to nothing rather than being left in place, so a manifest built from one
     * carries an empty host instead of a literal `${database.host}`.
     */
    public function testMissingRelationsResolveToNothingRatherThanFailing(): void {
        $workspace = Fixtures::workspace(['email_service_id' => 0, 'domain_id' => 0]);
        $deployment = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'database_service_id' => 0,
        ]);

        $this->assertSame('', $this->apply('${database.host}', $deployment));
        $this->assertSame('', $this->apply('${emailService.host}', $deployment));
        $this->assertSame('', $this->apply('${domain.host}', $deployment));
    }

    /**
     * The tokens that read the deployment's own columns keep working when the relations
     * are gone - they never needed them.
     */
    public function testTheDeploymentsOwnTokensSurviveMissingRelations(): void {
        $deployment = Fixtures::deployment([
            'workspace_id' => 0,
            'database_service_id' => 0,
            'namespace' => 'still-here',
        ]);

        $this->assertSame('still-here', $this->apply('${namespace}', $deployment));
        $this->assertSame($deployment->name, $this->apply('${deployment.name}', $deployment));
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * A deployment with every relation the substitution reads: a workspace on a domain,
     * an email service on the workspace, and a database service with credentials on the
     * deployment itself.
     *
     * @param array<string, mixed> $overrides
     */
    private function fullyWiredDeployment(array $overrides = []): Deployment {
        $emailService = Fixtures::emailService();
        $domain = Fixtures::domain(['name' => 'test.example.org']);
        $workspace = Fixtures::workspace([
            'domain_id' => $domain->id,
            'email_service_id' => $emailService->id,
        ]);
        $databaseService = Fixtures::databaseService(['host' => 'db.test', 'port' => 3306]);

        return Fixtures::deployment(array_merge([
            'workspace_id' => $workspace->id,
            'database_service_id' => $databaseService->id,
            'database_name' => 'tenant_db',
            'database_user' => 'tenant_user',
            'database_pass' => 'tenant-secret',
        ], $overrides));
    }

    private function apply(string $value, Deployment $deployment): string {
        return EnvironmentVariable::ApplyVariablesToString($value, $deployment);
    }

    // </editor-fold>

}
