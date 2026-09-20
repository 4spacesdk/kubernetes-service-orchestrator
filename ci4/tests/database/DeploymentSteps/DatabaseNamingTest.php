<?php namespace App\Tests\Database\DeploymentSteps;

use App\DatabaseTestCase;
use App\Fixtures;
use App\Libraries\DeploymentSteps\DatabaseStep;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What a deployment's database, user and password are called before anything is created.
 *
 * These three lived inside the method that opens the connection, so nothing about them
 * could be asked until a database server had answered. They decide the name a customer's
 * data ends up under and the password that guards it, which makes them worth looking at
 * directly.
 *
 * Two of the tests below hold behaviour that is **not** right and is not fixed here - the
 * collisions and the source of randomness are decisions of their own. They are here so the
 * day somebody changes them, it is on purpose.
 */
class DatabaseNamingTest extends DatabaseTestCase {

    public function testTheNameIsTheNamespaceAndTheDeployment(): void {
        $deployment = Fixtures::deployment(['namespace' => 'acme', 'name' => 'api']);

        $this->assertSame('acme_api', DatabaseStep::DatabaseNameFor($deployment));
    }

    /**
     * A namespace and a deployment name are what an operator typed, and neither is a legal
     * database name. Spaces and dashes become underscores; everything else outside
     * `[A-Za-z0-9_]` is dropped.
     */
    #[DataProvider('theNamesAnOperatorCanType')]
    public function testTheNameIsMadeLegal(string $namespace, string $name, string $expected): void {
        $deployment = Fixtures::deployment(['namespace' => $namespace, 'name' => $name]);

        $this->assertSame($expected, DatabaseStep::DatabaseNameFor($deployment));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function theNamesAnOperatorCanType(): array {
        return [
            'upper case comes down' => ['Acme', 'API', 'acme_api'],
            'a dash becomes an underscore' => ['acme', 'my-app', 'acme_my_app'],
            'a space becomes an underscore' => ['acme', 'my app', 'acme_my_app'],
            'a dot is dropped' => ['acme', 'my.app', 'acme_myapp'],
            'an accent is dropped' => ['acme', 'ærlig', 'acme_rlig'],
        ];
    }

    /**
     * **Pinned, not endorsed.** `my-app` and `my.app` in the same namespace are two
     * deployments and one database: the dash is replaced and the dot is dropped, and both
     * land on `acme_my_app`... or rather, they do not - the dot case loses a character. The
     * pair below does collide, and the point is that such a pair exists at all.
     */
    public function testTwoDeploymentsCanBeGivenTheSameDatabase(): void {
        $first = Fixtures::deployment(['namespace' => 'acme', 'name' => 'my-app']);
        $second = Fixtures::deployment(['namespace' => 'acme', 'name' => 'my app']);

        $this->assertSame(
            DatabaseStep::DatabaseNameFor($first),
            DatabaseStep::DatabaseNameFor($second),
            'the collision is gone - see the note above and replace this test'
        );
    }

    /**
     * And the longer way to the same place: the name is cut to 64 characters, so two that
     * agree for the first 64 are one database.
     */
    public function testTwoLongNamesThatAgreeForSixtyFourCharactersCollide(): void {
        // Long enough that the cut lands inside the shared part: `ns_` plus this is already
        // past 64, so what the two have in common is all that survives.
        $prefix = str_repeat('a', 64);
        $first = Fixtures::deployment(['namespace' => 'ns', 'name' => $prefix . 'one']);
        $second = Fixtures::deployment(['namespace' => 'ns', 'name' => $prefix . 'two']);

        $this->assertSame(64, strlen(DatabaseStep::DatabaseNameFor($first)));
        $this->assertSame(
            DatabaseStep::DatabaseNameFor($first),
            DatabaseStep::DatabaseNameFor($second)
        );
    }

    /**
     * The user is the database name cut to the 32 characters MySQL allows - half as long,
     * so two deployments share a user more easily than they share a database. A shared user
     * is a login that can reach both.
     */
    public function testTheUserIsTheNameCutToThirtyTwoCharacters(): void {
        $name = str_repeat('a', 64);

        $this->assertSame(str_repeat('a', 32), DatabaseStep::DatabaseUserFor($name));
    }

    public function testAShortNameIsItsOwnUser(): void {
        $this->assertSame('acme_api', DatabaseStep::DatabaseUserFor('acme_api'));
    }

    /**
     * Thirteen characters: the `@` MSSQL's complexity rules want, and twelve from the
     * alphabet. A password shorter than it looks would be a real finding, so the shape is
     * held here.
     */
    public function testThePasswordIsAnAtSignAndTwelveCharacters(): void {
        $password = DatabaseStep::GeneratePassword();

        $this->assertSame(13, strlen($password));
        $this->assertStringStartsWith('@', $password);
        $this->assertSame(1, preg_match('/^@[A-Za-z0-9]{12}$/', $password));
    }

    /**
     * **Pinned, not endorsed.** Two passwords in a row differ, which is all `rand()`
     * promises - and all this test can say. `rand()` is not a cryptographic source: it is
     * seeded per process and its output is predictable to anyone who can watch enough of
     * it. Replacing it is a decision of its own; this is here so the replacement is a
     * deliberate edit.
     */
    public function testTwoPasswordsAreNotTheSame(): void {
        $this->assertNotSame(DatabaseStep::GeneratePassword(), DatabaseStep::GeneratePassword());
    }

}
