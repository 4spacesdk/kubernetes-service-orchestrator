<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\Workspace;
use App\Exceptions\ValidationException;
use App\Fixtures;

/**
 * Extra hostnames a workspace answers on, typed as free text into one field.
 *
 * The field takes a comma-separated list where each entry may be a bare subdomain
 * (`app`), a full hostname on the workspace's domain (`app.example.org`), or the domain
 * itself. They are stored as written and expanded to hostnames when a manifest needs them,
 * so the same string is parsed twice by two different methods - once on the way in, with
 * validation, and once on the way out, without.
 *
 * Getting this wrong routes a customer's traffic somewhere else, so the rules are spelled
 * out one at a time rather than in one round trip.
 */
class WorkspaceAliasesTest extends DatabaseTestCase {

    // <editor-fold desc="What is accepted">

    public function testABareAliasBecomesASubdomainOfTheWorkspaceDomain(): void {
        $workspace = $this->workspaceWithAliases('app');

        $this->assertSame(['app.example.org'], $workspace->getAliasHostnames());
    }

    public function testAFullHostnameOnTheDomainIsKeptAsItIs(): void {
        $workspace = $this->workspaceWithAliases('app.example.org');

        $this->assertSame(['app.example.org'], $workspace->getAliasHostnames());
    }

    /**
     * The domain itself is allowed, which is how a workspace serves the apex.
     */
    public function testTheDomainItselfIsAllowedAsAnAlias(): void {
        $workspace = $this->workspaceWithAliases('example.org');

        $this->assertSame(['example.org'], $workspace->getAliasHostnames());
    }

    public function testSeveralAliasesAreSplitOnCommas(): void {
        $workspace = $this->workspaceWithAliases('app, files.example.org ,mail');

        $this->assertSame(
            ['app.example.org', 'files.example.org', 'mail.example.org'],
            $workspace->getAliasHostnames()
        );
    }

    public function testWhitespaceAndCaseAreNormalised(): void {
        $workspace = $this->workspaceWithAliases('  APP  ,  Files.Example.ORG  ');

        $this->assertSame(['app.example.org', 'files.example.org'], $workspace->getAliasHostnames());
    }

    public function testEmptyEntriesAreDropped(): void {
        $workspace = $this->workspaceWithAliases('app,,  ,mail,');

        $this->assertSame(['app.example.org', 'mail.example.org'], $workspace->getAliasHostnames());
    }

    public function testNoAliasesMeansNoHostnames(): void {
        $workspace = $this->workspaceWithAliases('');

        $this->assertSame([], $workspace->getAliasHostnames());
    }

    // </editor-fold>

    // <editor-fold desc="What is refused">

    /**
     * An alias has to live on the workspace's own domain. Without this a workspace could
     * claim a hostname on a domain it has no business serving.
     */
    public function testAHostnameOnAnotherDomainIsRefused(): void {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must be a subdomain or a hostname on example.org');

        $this->workspaceWithAliases('app.somewhere-else.org');
    }

    public function testAnAliasThatIsNotAValidHostnameIsRefused(): void {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('is not a valid hostname');

        $this->workspaceWithAliases('not_a_hostname');
    }

    /**
     * The workspace already answers on its own hostname, so listing it again would be a
     * second claim on the same name.
     */
    public function testAnAliasEqualToTheWorkspaceHostnameIsRefused(): void {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('is the same as the workspace hostname');

        $this->workspaceWithAliases('tenant', 'tenant');
    }

    /**
     * Written the long way it is the same claim, and it is caught the same way - the check
     * compares hostnames, not the text that was typed.
     */
    public function testTheWorkspaceHostnameIsRefusedWrittenInFull(): void {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('is the same as the workspace hostname');

        $this->workspaceWithAliases('tenant.example.org', 'tenant');
    }

    /**
     * Nothing is written when one entry in the list is bad - the whole update is refused,
     * so a half-applied list cannot happen.
     */
    public function testOneBadEntryRefusesTheWholeList(): void {
        $workspace = $this->workspaceWithAliases('app');

        try {
            $workspace->updateIngress($workspace->domain_id, 'tenant', 'files, nope_not_valid');
            $this->fail('the list was accepted');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $reloaded = new Workspace();
        $reloaded->find($workspace->id);
        $this->assertSame(['app.example.org'], $reloaded->getAliasHostnames());
    }

    // </editor-fold>

    // <editor-fold desc="Duplicates, which are handled twice and differently">

    public function testTheSameAliasTwiceIsStoredOnce(): void {
        $workspace = $this->workspaceWithAliases('app, app');

        $this->assertSame('app', $workspace->aliases);
    }

    /**
     * Storing deduplicates on the text as typed, while reading deduplicates on the
     * resulting hostname. `app` and `app.example.org` are therefore both stored - they are
     * different strings - and come back as one hostname.
     */
    public function testTwoSpellingsOfOneHostnameAreBothStoredButReadBackOnce(): void {
        $workspace = $this->workspaceWithAliases('app, app.example.org');

        $this->assertSame('app,app.example.org', $workspace->aliases);
        $this->assertSame(['app.example.org'], $workspace->getAliasHostnames());
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    private function workspaceWithAliases(string $aliases, string $subdomain = 'tenant'): Workspace {
        $domain = Fixtures::domain(['name' => 'example.org']);
        $workspace = Fixtures::workspace([
            'domain_id' => $domain->id,
            'subdomain' => $subdomain,
        ]);

        $workspace->updateIngress($domain->id, $subdomain, $aliases);

        $reloaded = new Workspace();
        $reloaded->find($workspace->id);

        return $reloaded;
    }

    // </editor-fold>

}
