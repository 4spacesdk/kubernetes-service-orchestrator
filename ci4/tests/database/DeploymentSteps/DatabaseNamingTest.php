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
 * Two deployments must never be handed the same database or user. The plain name can
 * collide; what a deploy actually uses, `AvailableNamesFor()`, steps aside from one taken.
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
     * `my-app` and `my app` in the same namespace have the same plain name: the dash and the
     * space both become an underscore.
     */
    public function testTwoDeploymentsCanHaveTheSamePlainName(): void {
        $first = Fixtures::deployment(['namespace' => 'acme', 'name' => 'my-app']);
        $second = Fixtures::deployment(['namespace' => 'acme', 'name' => 'my app']);

        $this->assertSame(DatabaseStep::DatabaseNameFor($first), DatabaseStep::DatabaseNameFor($second));
    }

    /**
     * So the deploy does not use the plain name when another deployment on the same
     * database service has it. It gets the same with a hash on the end.
     */
    public function testANameTakenOnTheSameServiceIsSteppedAsideFrom(): void {
        $service = Fixtures::databaseService();
        Fixtures::deployment(['database_service_id' => $service->id, 'namespace' => 'acme', 'name' => 'my-app',
            'database_name' => 'acme_my_app', 'database_user' => 'acme_my_app']);
        $second = Fixtures::deployment(['database_service_id' => $service->id, 'namespace' => 'acme', 'name' => 'my app']);

        [$name, $user] = DatabaseStep::AvailableNamesFor($second);

        $this->assertMatchesRegularExpression('/^acme_my_app_[0-9a-f]{8}$/', $name);
        $this->assertSame($name, $user);
    }

    /**
     * Another service is another server, and the same name there is no collision.
     */
    public function testTheSameNameOnAnotherServiceIsFree(): void {
        Fixtures::deployment(['database_service_id' => Fixtures::databaseService()->id, 'namespace' => 'acme', 'name' => 'api',
            'database_name' => 'acme_api', 'database_user' => 'acme_api']);
        $other = Fixtures::deployment(['database_service_id' => Fixtures::databaseService()->id, 'namespace' => 'acme', 'name' => 'api']);

        $this->assertSame(['acme_api', 'acme_api'], DatabaseStep::AvailableNamesFor($other));
    }

    /**
     * A user taken is as much a collision as a database taken: two deployments sharing a
     * user is a login that reaches both.
     */
    public function testAUserTakenIsSteppedAsideFromToo(): void {
        $service = Fixtures::databaseService();
        Fixtures::deployment(['database_service_id' => $service->id, 'database_name' => 'something_else', 'database_user' => 'acme_api']);
        $second = Fixtures::deployment(['database_service_id' => $service->id, 'namespace' => 'acme', 'name' => 'api']);

        [, $user] = DatabaseStep::AvailableNamesFor($second);

        $this->assertNotSame('acme_api', $user);
    }

    /**
     * Two long names that agree for the first 64 characters used to be one database. Cut,
     * they now end in a hash of the whole name, and stay two.
     */
    public function testTwoLongNamesThatAgreeForSixtyFourCharactersStayTwo(): void {
        $prefix = str_repeat('a', 64);
        $first = Fixtures::deployment(['namespace' => 'ns', 'name' => $prefix . 'one']);
        $second = Fixtures::deployment(['namespace' => 'ns', 'name' => $prefix . 'two']);

        $this->assertSame(64, strlen(DatabaseStep::DatabaseNameFor($first)));
        $this->assertNotSame(DatabaseStep::DatabaseNameFor($first), DatabaseStep::DatabaseNameFor($second));
    }

    /**
     * The user is fitted to MySQL's 32 characters the same way. Cut only, two names that
     * agreed for 32 characters - far easier than 64 - shared a login.
     */
    public function testTwoLongNamesThatAgreeForThirtyTwoCharactersHaveTwoUsers(): void {
        $first = DatabaseStep::DatabaseUserFor(str_repeat('a', 40) . '_one');
        $second = DatabaseStep::DatabaseUserFor(str_repeat('a', 40) . '_two');

        $this->assertSame(32, strlen($first));
        $this->assertNotSame($first, $second);
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

    public function testTwoPasswordsAreNotTheSame(): void {
        $this->assertNotSame(DatabaseStep::GeneratePassword(), DatabaseStep::GeneratePassword());
    }

    /**
     * The password comes from a cryptographic source, asserted by sweeping the source for
     * the ones that are not.
     *
     * It was `rand()`: a Mersenne Twister, seeded per process, whose state can be recovered
     * from enough of its output - and the rough time a deployment was created narrows the
     * seed to something searchable. Nothing a test can observe tells the two apart, so this
     * reads the code instead, and it reads all of it rather than the one method: the next
     * thing that generates a secret will be written somewhere else.
     *
     * `uniqid()` is not swept for on purpose. It is used for a Kubernetes job name and an
     * error id, where the requirement is that two do not collide, not that nobody can guess
     * the next one.
     */
    public function testNothingInTheApplicationDrawsSecretsFromAWeakSource(): void {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APPPATH));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if ($this->callsAWeakSource((string) file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(APPPATH, '', $file->getPathname());
            }
        }

        sort($offenders);

        $this->assertSame([], $offenders, 'use random_int() or random_bytes()');
    }

    /**
     * And the sweep can see one, so an empty list above means the application is clean
     * rather than that the check looks in the wrong place. Both halves: a call is found, and
     * the same name in a comment or a string is not.
     */
    public function testTheSweepTellsACallFromAMentionOfOne(): void {
        $this->assertTrue($this->callsAWeakSource('<?php $i = rand(0, 9);'));
        $this->assertTrue($this->callsAWeakSource('<?php $i = mt_rand(0, 9);'));

        $this->assertFalse($this->callsAWeakSource('<?php /** rand() is the wrong one */ $i = random_int(0, 9);'));
        $this->assertFalse($this->callsAWeakSource('<?php $why = "rand() is the wrong one";'));
        $this->assertFalse($this->callsAWeakSource('<?php $i = $generator->rand(0, 9);'));
    }

    /**
     * Tokenised rather than searched as text.
     *
     * A comment explaining why `rand()` is the wrong function is not a call to it, and the
     * method this test guards has one - a plain search made the file its own offender. So
     * comments and strings are dropped and what is left is read as identifiers.
     */
    private function callsAWeakSource(string $source): bool {
        $weak = ['rand', 'mt_rand', 'srand', 'mt_srand'];

        $tokens = array_values(array_filter(
            token_get_all($source),
            static fn ($token) => !is_array($token)
                || !in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE, T_CONSTANT_ENCAPSED_STRING], true)
        ));

        foreach ($tokens as $i => $token) {
            if (!is_array($token) || $token[0] !== T_STRING || !in_array(strtolower($token[1]), $weak, true)) {
                continue;
            }

            // A call, not a method of the same name: `$this->rand(` has an object operator
            // in front of it, and `function rand(` a `function`.
            $before = $tokens[$i - 1] ?? null;
            if (is_array($before) && in_array($before[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
                continue;
            }

            if (($tokens[$i + 1] ?? null) === '(') {
                return true;
            }
        }

        return false;
    }

}
