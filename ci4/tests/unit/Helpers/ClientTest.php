<?php namespace App\Tests\Unit\Helpers;

use App\Entities\User;
use App\Helpers\AuthToken;
use App\Helpers\Client;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionProperty;

/**
 * Who is making this request, and what are they allowed to do.
 *
 * `Client` is the one place the rest of kso asks those two questions, so everything here is
 * an authorisation decision wearing a different hat. A wrong answer from `IsAdmin()` is a
 * customer editing another customer's deployment; a wrong answer from `IsAuthorized()` is an
 * unauthenticated request treated as signed in.
 *
 * The state is static and lives for the request, which is fine in production - a process
 * handles one request - but in a test run it survives into the next test. Everything set here
 * is put back in `tearDown()`.
 */
class ClientTest extends CIUnitTestCase {

    // <editor-fold desc="The client's app version">

    /**
     * The app version decides which responses the web app can understand, so it has to be the
     * version and nothing else. Clients do not all send a bare number: some send
     * `1.2.3 (build 44)`, and the build half has to be dropped or every comparison against a
     * minimum supported version is made against a string that is not a version at all.
     *
     * A client that sends no version is an old one, from before the parameter existed, which
     * is why the fallback is the oldest version rather than the current one.
     */
    #[DataProvider('theVersionsAClientSends')]
    public function testTheAppVersionIsWhatTheClientSentUpToTheFirstSpace(?string $sent, string $expected): void {
        if ($sent !== null) {
            $_GET['app_version'] = $sent;
        }

        Client::Init();

        $this->assertSame($expected, Client::$appVersion);
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function theVersionsAClientSends(): array {
        return [
            'a bare version is kept whole'      => ['2.4.1', '2.4.1'],
            'the build half is dropped'         => ['1.2.3 (build 44)', '1.2.3'],
            'only the first word ever survives' => ['1.2.3 (build 44) debug', '1.2.3'],
            'no parameter means the oldest'     => [null, '1.0.0'],

            // An empty parameter is not a missing parameter: the client did send something, so
            // the fallback does not apply and the version ends up empty. Pinned because a
            // version comparison against an empty string is a different bug to hunt than a
            // version comparison against 1.0.0.
            'an empty parameter stays empty'    => ['', ''],

            // `strpos()` returns 0 for a leading space, which is falsy, so the split never
            // happens and the whole string survives. This is current behaviour, not intended
            // behaviour - see the note in the report.
            'a leading space defeats the split' => [' 1.2.3 (build 44)', ' 1.2.3 (build 44)'],
        ];
    }

    // </editor-fold>

    // <editor-fold desc="Is anybody signed in">

    /**
     * Nothing has signed the request in yet, and that has to read as unauthorised rather than
     * raise. Without the `isset()` guard the static is read before it is ever written, which
     * is a fatal error on every anonymous request - the login page included.
     *
     * The static cannot be put back into its untouched state once written, so this test gets
     * its own process.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testARequestThatNobodySignedInIsNotAuthorized(): void {
        $this->assertFalse(Client::IsAuthorized());
    }

    /**
     * A lookup that found nobody still returns a `User`, just an empty one. Treating that as
     * signed in would hand every anonymous caller an account with no id and no type.
     */
    public function testAUserThatWasNeverFoundIsNotAuthorized(): void {
        Client::SetUser(new User());

        $this->assertFalse(Client::IsAuthorized());
    }

    public function testAUserThatExistsIsAuthorized(): void {
        Client::SetUser($this->user(type: 'user'));

        $this->assertTrue(Client::IsAuthorized());
    }

    // </editor-fold>

    // <editor-fold desc="What the signed in user may do">

    /**
     * The four questions are a ladder, not four separate flags: a `service` account is also a
     * developer, an owner and an admin, and a plain `user` is none of them. Breaking the ladder
     * in the permissive direction lets a customer's own login reach the endpoints that create
     * and delete deployments; breaking it in the other direction locks the internal service
     * account out of the jobs it runs unattended.
     */
    #[DataProvider('theTypesAUserCanHave')]
    public function testEachUserTypeReachesExactlyTheLevelsBelowIt(
        string $type,
        bool   $isService,
        bool   $isDeveloper,
        bool   $isOwner,
        bool   $isAdmin
    ): void {
        Client::SetUser($this->user(type: $type));

        $this->assertSame($isService, Client::IsService(), "IsService() for {$type}");
        $this->assertSame($isDeveloper, Client::IsDeveloper(), "IsDeveloper() for {$type}");
        $this->assertSame($isOwner, Client::IsOwner(), "IsOwner() for {$type}");
        $this->assertSame($isAdmin, Client::IsAdmin(), "IsAdmin() for {$type}");
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: bool, 3: bool, 4: bool}>
     */
    public static function theTypesAUserCanHave(): array {
        return [
            //                    type          service developer owner  admin
            'a service account' => ['service',   true,   true,     true,  true],
            'a developer'       => ['developer', false,  true,     true,  true],
            'an owner'          => ['owner',     false,  false,    true,  true],
            'an admin'          => ['admin',     false,  false,    false, true],
            'a plain user'      => ['user',      false,  false,    false, false],

            // A type nobody recognises has to fall through the whole ladder. If it did not,
            // a typo in the database would be a privilege escalation.
            'an unknown type'   => ['',          false,  false,    false, false],
        ];
    }

    // </editor-fold>

    // <editor-fold desc="What the token grants">

    /**
     * The user type says what kind of account this is; the scope says what the token that
     * account signed in with was actually granted. Both have to hold, which is what makes a
     * narrowly scoped token useful in the first place - a scope check that answered yes to
     * anything would make every token a full access token.
     */
    public function testAScopeIsGrantedOnlyWhenTheTokenCarriesIt(): void {
        Client::SetToken(['scope' => 'system:read system:write']);

        $this->assertTrue(Client::HasScope('system:read'));
        $this->assertTrue(Client::HasScope('system:write'));
        $this->assertFalse(Client::HasScope('system:delete'), 'a scope the token was never granted');
    }

    /**
     * A token granted nothing grants nothing. This is the shape a client credentials token
     * arrives in when no scope was asked for, and it must not be read as a wildcard.
     */
    public function testATokenWithoutScopesGrantsNothing(): void {
        Client::SetToken(['scope' => '']);

        $this->assertFalse(Client::HasScope('system:read'));
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    private function user(string $type): User {
        $user       = new User();
        $user->id   = 7;
        $user->type = $type;

        return $user;
    }

    /**
     * Static state outlives the test that set it. The properties are typed and have no
     * default, so they cannot be put back to untouched - the next best thing is to leave them
     * holding the values an untouched `Client` behaves like: nobody signed in, and a token
     * that grants nothing.
     */
    protected function tearDown(): void {
        unset($_GET['app_version']);

        Client::$appVersion = null;
        Client::SetUser(new User());

        (new ReflectionProperty(Client::class, 'token'))->setValue(null, new AuthToken(['scope' => '']));

        parent::tearDown();
    }

    // </editor-fold>

}
