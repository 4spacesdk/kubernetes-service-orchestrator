<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\User;
use App\Fixtures;
use App\Libraries\LoginThrottle;
use App\Libraries\MFALib;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The sign-in form, which is public by necessity and therefore worth looking at closely.
 *
 * It renders a view rather than answering JSON, so the assertions read the rendered page.
 * What matters is which of the three outcomes a visitor is told apart.
 *
 * Everything an operator needs in order to reach kso at all goes through this one
 * controller: the password check, the second factor, the forced renewal and the forgotten
 * password. Each of the four is a place where getting in without a password would be a
 * complete compromise of the installation, so the tests below are mostly about what does
 * *not* happen - no session, no redirect onwards, no password written.
 *
 * Two things about the harness shape what can be asserted here.
 *
 * **The session is a test double.** These tests can say what the controller put in the
 * session, and nothing at all about whether the real session handler would keep it.
 *
 * **The redirects are ordinary responses.** Three branches here used to `send()` and
 * `exit`, which took PHPUnit with them and needed a response object that threw instead of
 * sending. They return the response now, so they are reached like everything else.
 */
class LoginApiTest extends ControllerTestCase {

    /**
     * Restored rather than cleared. `EmailLib::send()` throws when the installation is not
     * configured to send mail, and the forgotten-password tests below need to choose which
     * of those two worlds they are in - but `putenv()` applies to the whole process, so
     * whatever the container was started with has to be put back for the tests that follow.
     */
    private const EmailSettings = [
        'EMAIL_SERVICE_HOST',
        'EMAIL_SERVICE_PORT',
        'EMAIL_SERVICE_USER',
        'EMAIL_SERVICE_PASS',
        'EMAIL_SERVICE_SENDER',
    ];

    /** @var array<string, string|false> */
    private array $emailSettingsAsFound = [];

    public function setUp(): void {
        parent::setUp();

        foreach (self::EmailSettings as $name) {
            $this->emailSettingsAsFound[$name] = getenv($name);
        }
    }

    /**
     * A fresh response for every request, not just for every test. The harness shares one,
     * so a page answered without a redirect kept the `Location` of the redirect before it,
     * and a refused sign-in after a successful one looked like a successful one.
     */
    public function call(string $method, string $path, ?array $params = null) {
        \CodeIgniter\Config\Services::resetSingle('response');

        return parent::call($method, $path, $params);
    }

    public function tearDown(): void {
        foreach ($this->emailSettingsAsFound as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }
        \CodeIgniter\Config\Services::resetSingle('email');

        parent::tearDown();
    }

    // <editor-fold desc="The form itself">

    public function testTheFormIsServedWithoutATokenAndAsksForCredentials(): void {
        $page = $this->body($this->get('login'));

        $this->assertStringContainsString('username', strtolower($page));
        $this->assertStringContainsString('password', strtolower($page));
    }

    /**
     * Nobody is signed in by asking for the form. Obvious, and still worth holding: the
     * page is the one URL in kso that is served to anyone at all, and a session created
     * here would be a session created for an anonymous visitor.
     */
    public function testAskingForTheFormSignsNobodyIn(): void {
        $this->get('login');

        $this->assertNull($this->whoTheSessionSaysIsSignedIn());
    }

    /**
     * The message shown after a bounce comes back from the query string, which is how
     * `twoFactor()` tells the visitor why it sent them here.
     */
    public function testAMessageInTheQueryStringIsShownOnTheForm(): void {
        $page = $this->body($this->get('login?error_message=Two+factor+authentication+not+initialized.'));

        $this->assertStringContainsString('Two factor authentication not initialized.', $page);
    }

    /**
     * The message comes from a link anyone can write, onto the unauthenticated page where the
     * operator is about to type their password, so it is shown as text and never as markup.
     */
    public function testTheMessageFromTheQueryStringIsWrittenIntoThePageAsText(): void {
        $page = $this->body($this->get('login?error_message=' . urlencode('<script>alert(1)</script>')));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $page);
    }

    /**
     * The second way in: `twoFactor()` bounces a marker naming a user who no longer exists
     * back to the form with that username in the message.
     */
    public function testAUsernameBouncedBackFromTheCodeFormIsWrittenAsText(): void {
        $this->withSession(['2fa_in_progress' => '<img src=x onerror=alert(1)>']);

        $bounce = $this->get('login/twoFactor')->getRedirectUrl();
        $page = $this->body($this->get(substr($bounce, strlen(base_url()))));

        $this->assertStringNotContainsString('<img src=x', $page);
    }

    // </editor-fold>

    // <editor-fold desc="Checking the password">

    /**
     * The whole point of the form. A correct password puts the user's id in the session and
     * sends them on; nothing else in kso asks who you are, it only reads that id.
     */
    public function testACorrectPasswordSignsTheUserInAndSendsThemOn(): void {
        $user = Fixtures::user(['username' => 'operator', 'password' => 'the-right-one']);

        $response = $this->post('login', ['username' => 'operator', 'password' => 'the-right-one']);

        $this->assertSame(302, $response->response()->getStatusCode());
        $this->assertSame(getFrontendUrl(), $this->location($response));
        $this->assertSame((int) $user->id, (int) $this->whoTheSessionSaysIsSignedIn());
    }

    /**
     * The half that matters. A refused sign-in must leave the visitor exactly as anonymous
     * as they arrived - no id in the session, and no redirect onwards that the rest of the
     * application would honour.
     */
    #[DataProvider('theWaysASignInIsRefused')]
    public function testARefusedSignInLeavesTheVisitorAnonymous(string $username, string $password): void {
        Fixtures::user(['username' => 'operator', 'password' => 'the-right-one']);

        $response = $this->post('login', ['username' => $username, 'password' => $password]);

        $this->assertNull($this->whoTheSessionSaysIsSignedIn());
        $this->assertSame('', $this->location($response));
        $this->assertSame(200, $response->response()->getStatusCode());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function theWaysASignInIsRefused(): array {
        return [
            'the wrong password' => ['operator', 'not-the-right-one'],
            'an empty password' => ['operator', ''],
            'a username nobody has' => ['nobody', 'the-right-one'],
            'another user\'s password' => ['nobody', 'not-the-right-one'],
        ];
    }

    /**
     * A wrong password and an unknown username get the same answer, so the form cannot be
     * used to find out whether a given person has an account.
     */
    public function testWrongPasswordAndUnknownUserAreToldTheSame(): void {
        Fixtures::user(['username' => 'exists', 'password' => 'the-right-one']);

        $wrongPassword = $this->body($this->post('login', [
            'username' => 'exists',
            'password' => 'not-the-right-one',
        ]));
        $unknownUser = $this->body($this->post('login', [
            'username' => 'does-not-exist',
            'password' => 'anything',
        ]));

        $this->assertStringContainsString('Wrong username or password', $wrongPassword);
        $this->assertStringContainsString('Wrong username or password', $unknownUser);
    }

    /**
     * Nor can the time it takes. A known username costs a bcrypt check; an unknown one used
     * to return at once, which gave the answer away as surely as the message did.
     */
    public function testAnUnknownUsernameTakesAsLongAsAWrongPassword(): void {
        // At the real cost: fixtures hash at cost 4 to keep the suite fast, and at that cost
        // there is no difference to measure.
        $user = Fixtures::user(['username' => 'exists']);
        \Config\Database::connect()->table('users')->where('id', $user->id)
            ->update(['password' => User::encryptPassword('the-right-one')]);

        $timed = function(string $username): float {
            $started = hrtime(true);
            $this->post('login', ['username' => $username, 'password' => 'not-the-right-one']);
            return (hrtime(true) - $started) / 1e6;
        };
        $timed('exists');

        $known = $timed('exists');
        $unknown = $timed('does-not-exist');

        $this->assertGreaterThan($known / 2, $unknown, "known {$known} ms, unknown {$unknown} ms");
    }

    /**
     * The password is verified against the stored hash, not compared to it, so a user
     * whose row holds a hash cannot sign in by sending that hash.
     */
    public function testTheStoredHashIsNotItselfAPassword(): void {
        $user = Fixtures::user(['username' => 'hashed', 'password' => 'the-right-one']);

        $page = $this->body($this->post('login', [
            'username' => 'hashed',
            'password' => $user->password,
        ]));

        $this->assertStringContainsString('Wrong username or password', $page);
    }

    /**
     * After ten wrong passwords in a row the username is refused - including with the right
     * password, or the refusal would be a way of testing guesses.
     */
    public function testTenWrongPasswordsRefuseTheNextAttemptEvenWithTheRightOne(): void {
        Fixtures::user(['username' => 'guessable', 'password' => 'the-right-one']);

        $this->failToSignIn('guessable', LoginThrottle::MaxFailures);
        $response = $this->post('login', ['username' => 'guessable', 'password' => 'the-right-one']);

        $this->assertStringContainsString('Too many failed attempts', $this->body($response));
        $this->assertNull($this->whoTheSessionSaysIsSignedIn());
    }

    /**
     * One short of the limit is still an attempt, and a success wipes the count, so an
     * operator who mistypes now and then is never refused.
     */
    public function testASuccessfulSignInStartsTheCountAgain(): void {
        Fixtures::user(['username' => 'clumsy', 'password' => 'the-right-one']);

        $this->failToSignIn('clumsy', LoginThrottle::MaxFailures - 1);
        $this->assertSame(getFrontendUrl(), $this->location($this->post('login', ['username' => 'clumsy', 'password' => 'the-right-one'])));

        $this->failToSignIn('clumsy', LoginThrottle::MaxFailures - 1);
        $this->assertSame(getFrontendUrl(), $this->location($this->post('login', ['username' => 'clumsy', 'password' => 'the-right-one'])));
    }

    /**
     * A name nobody has is refused the same way, or the refusal would tell accounts apart.
     */
    public function testAnUnknownUsernameIsRefusedTheSameWay(): void {
        $this->failToSignIn('nobody', LoginThrottle::MaxFailures);

        $response = $this->post('login', ['username' => 'nobody', 'password' => 'anything']);

        $this->assertStringContainsString('Too many failed attempts', $this->body($response));
    }

    /**
     * Every attempt is on record: who was tried, whether it worked, from where. A name with
     * an account carries its id; one without carries none.
     */
    public function testEveryAttemptIsWrittenToTheAuditLog(): void {
        $user = Fixtures::user(['username' => 'operator', 'password' => 'the-right-one']);

        $this->post('login', ['username' => 'operator', 'password' => 'wrong']);
        $this->post('login', ['username' => 'nobody', 'password' => 'wrong']);
        $this->post('login', ['username' => 'operator', 'password' => 'the-right-one']);

        $rows = $this->signInAttempts();
        $this->assertSame(
            [
                ['operator', (string) $user->id, 'password', '0', '0'],
                ['nobody', null, 'password', '0', '0'],
                ['operator', (string) $user->id, 'password', '1', '0'],
            ],
            array_map(fn($row) => [$row['username'], $row['user_id'], $row['step'], $row['succeeded'], $row['refused']], $rows)
        );
        $this->assertNotSame('', $rows[0]['ip_address']);
        $this->assertNotNull($rows[0]['created']);
    }

    /**
     * A refused attempt is on record too, and is not a failure: counting it would keep a
     * refusal alive for as long as someone kept trying.
     */
    public function testARefusalIsLoggedButNotCounted(): void {
        Fixtures::user(['username' => 'guessable', 'password' => 'the-right-one']);
        $this->failToSignIn('guessable', LoginThrottle::MaxFailures);

        $this->failToSignIn('guessable', LoginThrottle::MaxFailures);

        $refused = array_filter($this->signInAttempts(), fn($row) => $row['refused'] === '1');
        $this->assertCount(LoginThrottle::MaxFailures, $refused);

        // The failures age out, and the refusals behind them do not keep it going.
        $this->db->table('sign_in_attempts')->where('refused', 0)
            ->update(['created' => date('Y-m-d H:i:s', time() - LoginThrottle::WindowSeconds - 1)]);
        $this->assertSame(getFrontendUrl(), $this->location($this->post('login', ['username' => 'guessable', 'password' => 'the-right-one'])));
    }

    /**
     * Failures further back than the window do not count.
     */
    public function testFailuresOutsideTheWindowDoNotCount(): void {
        Fixtures::user(['username' => 'guessable', 'password' => 'the-right-one']);
        $this->failToSignIn('guessable', LoginThrottle::MaxFailures);

        $this->db->table('sign_in_attempts')
            ->update(['created' => date('Y-m-d H:i:s', time() - LoginThrottle::WindowSeconds - 1)]);

        $this->assertSame(getFrontendUrl(), $this->location($this->post('login', ['username' => 'guessable', 'password' => 'the-right-one'])));
    }

    /**
     * The count is per username, and the same username in other capitals is the same one.
     */
    public function testTheCountIsPerUsernameWhateverTheCase(): void {
        Fixtures::user(['username' => 'guessable', 'password' => 'the-right-one']);
        Fixtures::user(['username' => 'bystander', 'password' => 'the-right-one']);

        $this->failToSignIn('GUESSABLE', LoginThrottle::MaxFailures);

        $this->assertStringContainsString('Too many failed attempts', $this->body($this->post('login', ['username' => 'guessable', 'password' => 'the-right-one'])));
        $this->assertSame(getFrontendUrl(), $this->location($this->post('login', ['username' => 'bystander', 'password' => 'the-right-one'])));
    }

    // </editor-fold>

    // <editor-fold desc="Where the visitor is sent afterwards">

    /**
     * The destination comes from a link anyone can write, and the operator has just typed
     * their password into kso's own page. A link may only send them on within kso.
     */
    #[DataProvider('destinationsElsewhere')]
    public function testASignInIsNotSentToAnotherSite(string $destination): void {
        Fixtures::user(['username' => 'operator', 'password' => 'the-right-one']);

        $response = $this->post(
            'login?request_uri=' . urlencode($destination),
            ['username' => 'operator', 'password' => 'the-right-one']
        );

        $this->assertSame(getFrontendUrl(), $this->location($response));
    }

    public static function destinationsElsewhere(): array {
        return [
            'another host' => ['https://somewhere-else.example/collect'],
            'no scheme' => ['//somewhere-else.example/collect'],
            'a backslash browsers read as a slash' => ['/\\somewhere-else.example/collect'],
            'our host as a user name' => ['http://api@somewhere-else.example/'],
            'script' => ['javascript:alert(1)'],
            'a line break' => ["/app\r\nSet-Cookie: x=1"],
        ];
    }

    /**
     * What a link may ask for: a path, the API itself - which is where an OAuth sign-in
     * returns to finish - and the frontend.
     */
    #[DataProvider('destinationsWithinKso')]
    public function testASignInIsSentOnWithinKso(\Closure $destinationFor): void {
        $destination = $destinationFor();
        Fixtures::user(['username' => 'operator', 'password' => 'the-right-one']);

        $response = $this->post(
            'login?request_uri=' . urlencode($destination),
            ['username' => 'operator', 'password' => 'the-right-one']
        );

        $this->assertSame($destination, $this->location($response));
    }

    /**
     * Closures, because a data provider runs before the application knows its own address.
     */
    public static function destinationsWithinKso(): array {
        return [
            'a path' => [fn() => '/app/workspaces/7'],
            'the API' => [fn() => base_url('/authorize') . '?client_id=kso'],
            'the frontend' => [fn() => getFrontendUrl('app/workspaces/7')],
        ];
    }

    /**
     * Two spellings of the same idea, read one after the other, so the second wins. Both
     * are in use - `redirect_uri` is what the OAuth flow sends - and a change that reads
     * them in the other order would quietly send OAuth callers to the wrong place.
     */
    public function testRedirectUriIsPreferredOverRequestUri(): void {
        Fixtures::user(['username' => 'operator', 'password' => 'the-right-one']);

        $response = $this->post(
            'login?request_uri=' . urlencode('/first') . '&redirect_uri=' . urlencode('/second'),
            ['username' => 'operator', 'password' => 'the-right-one']
        );

        $this->assertSame('/second', $this->location($response));
    }

    /**
     * A destination is checked where it is read, so one that reached the session some other
     * way is held to the same rule.
     */
    public function testARememberedDestinationElsewhereIsNotFollowedEither(): void {
        Fixtures::user(['username' => 'operator', 'password' => 'the-right-one']);

        $this->withSession($this->rememberedDestination('https://somewhere-else.example/collect'));
        $response = $this->post('login', ['username' => 'operator', 'password' => 'the-right-one']);

        $this->assertSame(getFrontendUrl(), $this->location($response));
    }

    /**
     * An OAuth sign-in returns to `/authorize` to finish. The auth extension's `/authorize`
     * remembers itself before sending the visitor to the form, and the form has to read it
     * under the same name.
     */
    public function testAnOAuthSignInReturnsToAuthorize(): void {
        Fixtures::user(['username' => 'operator', 'password' => 'the-right-one']);

        $this->withSession($this->rememberedDestination(base_url('/authorize') . '?client_id=kso'));
        $this->get('login?scope=');
        $response = $this->withSession()->post('login', ['username' => 'operator', 'password' => 'the-right-one']);

        $this->assertSame(base_url('/authorize') . '?client_id=kso', $this->location($response));
    }

    /**
     * The form posts to itself without the query string, so the destination has to survive
     * the round trip in the session. Without it every sign-in that arrived from a deep link
     * would land on the frontend's front page instead.
     */
    public function testTheDestinationSurvivesTheRoundTripThroughTheForm(): void {
        $this->get('login?request_uri=' . urlencode($this->deepLink()));

        $this->assertSame($this->deepLink(), $_SESSION['requestUrl'] ?? null);
        $this->assertArrayHasKey('requestUrl', $_SESSION['__ci_vars'] ?? [], 'kept as flashdata, not for ever');
    }

    /**
     * And the other half of that round trip: what the form remembered is what a successful
     * sign-in uses, even though the posted form carries no destination of its own.
     */
    public function testWhatTheFormRememberedIsWhereTheSignInGoes(): void {
        Fixtures::user(['username' => 'operator', 'password' => 'the-right-one']);

        $this->withSession($this->rememberedDestination($this->deepLink()));
        $response = $this->post('login', ['username' => 'operator', 'password' => 'the-right-one']);

        $this->assertSame($this->deepLink(), $this->location($response));
    }

    // </editor-fold>

    // <editor-fold desc="The second factor">

    /**
     * The password alone is not a sign-in for a user who has a second factor. This is the
     * single most important assertion in the file: if the id were written to the session
     * here, the second factor would be a page the browser could simply decline to visit.
     */
    public function testACorrectPasswordAloneDoesNotSignInAUserWithASecondFactor(): void {
        $this->userWithASecondFactor(['username' => 'mfa-operator', 'password' => 'the-right-one']);

        $response = $this->post('login', ['username' => 'mfa-operator', 'password' => 'the-right-one']);

        $this->assertNull($this->whoTheSessionSaysIsSignedIn());
        $this->assertSame('mfa-operator', $_SESSION['2fa_in_progress'] ?? null);
        $this->assertSame(base_url('login/twoFactor'), $this->location($response));
    }

    /**
     * A wrong password for a user who has a second factor must not even get as far as being
     * asked for a code - otherwise the code form itself is a signal that the password was
     * right.
     */
    public function testAWrongPasswordNeverReachesTheSecondFactor(): void {
        $this->userWithASecondFactor(['username' => 'mfa-operator', 'password' => 'the-right-one']);

        $response = $this->post('login', ['username' => 'mfa-operator', 'password' => 'wrong']);

        $this->assertArrayNotHasKey('2fa_in_progress', $_SESSION);
        $this->assertSame('', $this->location($response));
    }

    /**
     * The form is only shown to a browser whose password has already been checked, and the
     * marker in the session is the only evidence of that. A visitor without it is sent back
     * to the start and told why.
     */
    public function testTheCodeFormCannotBeReachedWithoutAPasswordCheck(): void {
        $response = $this->get('login/twoFactor');

        $this->assertSame(
            base_url('login') . '?error_message=Two factor authentication not initialized.',
            $this->location($response)
        );
        $this->assertNull($this->whoTheSessionSaysIsSignedIn());
    }

    /**
     * And the same for posting a code. Reaching `twoFactor()` directly with a code in hand
     * is exactly the attack the second factor exists to stop - a stolen or guessed code
     * with no password behind it - so the guard has to be in front of the verification, not
     * only in front of the form.
     *
     * The message is asserted in full on purpose: the second guard below - the one for a
     * marker naming a user who no longer exists - also bounces to the same page, so a
     * looser assertion would still pass with this guard removed altogether.
     */
    public function testACodeCannotBeSubmittedWithoutAPasswordCheck(): void {
        $user = $this->userWithASecondFactor(['username' => 'mfa-operator']);

        $response = $this->post('login/twoFactor', ['code' => $this->currentCodeFor($user)]);

        $this->assertSame(
            base_url('login') . '?error_message=Two factor authentication not initialized.',
            $this->location($response)
        );
        $this->assertNull($this->whoTheSessionSaysIsSignedIn());
    }

    /**
     * A marker naming somebody who no longer has an account is refused rather than followed.
     * It is what happens to a half-finished sign-in when the account is deleted in between,
     * and the alternative is a fatal error on an unauthenticated page.
     */
    public function testAMarkerNamingAUserWhoNoLongerExistsIsRefused(): void {
        $this->withSession(['2fa_in_progress' => 'deleted-since']);

        $response = $this->get('login/twoFactor');

        $this->assertStringContainsString('Unknown username', $this->location($response));
        $this->assertNull($this->whoTheSessionSaysIsSignedIn());
    }

    public function testTheCodeFormIsShownOnceThePasswordHasBeenChecked(): void {
        $this->userWithASecondFactor(['username' => 'mfa-operator']);

        $page = $this->body($this->withSession(['2fa_in_progress' => 'mfa-operator'])->get('login/twoFactor'));

        $this->assertStringContainsString('name="code"', $page);
    }

    /**
     * A wrong code leaves the sign-in exactly where it was: still half finished, still not
     * signed in. The marker stays so the visitor can try again, and nothing else does.
     */
    public function testAWrongCodeDoesNotFinishTheSignIn(): void {
        $user = $this->userWithASecondFactor(['username' => 'mfa-operator']);

        $this->withSession(['2fa_in_progress' => 'mfa-operator']);
        $response = $this->post('login/twoFactor', ['code' => $this->aCodeThatIsNotTheRightOne($user)]);

        $this->assertNull($this->whoTheSessionSaysIsSignedIn());
        $this->assertSame('mfa-operator', $_SESSION['2fa_in_progress'] ?? null);
        $this->assertSame('', $this->location($response));
        $this->assertStringContainsString('Failed to verify code', $this->body($response));
    }

    /**
     * The code is counted on its own. A six-digit code has a million values, and without a
     * limit anyone who had the password could run through them.
     */
    public function testTenWrongCodesRefuseTheNextEvenIfItIsRight(): void {
        $user = $this->userWithASecondFactor(['username' => 'mfa-operator']);
        $this->withSession(['2fa_in_progress' => 'mfa-operator']);

        for ($attempt = 0; $attempt < LoginThrottle::MaxFailures; $attempt++) {
            $this->post('login/twoFactor', ['code' => $this->aCodeThatIsNotTheRightOne($user)]);
        }
        $response = $this->post('login/twoFactor', ['code' => $this->currentCodeFor($user)]);

        $this->assertStringContainsString('Too many failed attempts', $this->body($response));
        $this->assertNull($this->whoTheSessionSaysIsSignedIn());
    }

    /**
     * The right code is what turns a checked password into a session. The marker is removed
     * at the same moment, so a code cannot be replayed against a sign-in that is already
     * finished.
     */
    public function testTheRightCodeFinishesTheSignIn(): void {
        $user = $this->userWithASecondFactor(['username' => 'mfa-operator']);

        $this->withSession(['2fa_in_progress' => 'mfa-operator']);
        $response = $this->post('login/twoFactor', ['code' => $this->currentCodeFor($user)]);

        $this->assertSame((int) $user->id, (int) $this->whoTheSessionSaysIsSignedIn());
        $this->assertArrayNotHasKey('2fa_in_progress', $_SESSION);
        $this->assertSame(getFrontendUrl(), $this->location($response));
    }

    /**
     * A user who has to change their password does not get to skip that by having a second
     * factor: the code signs them in, and the next thing they see is the renewal form.
     */
    public function testAUserWhoMustRenewIsSentToTheRenewalFormAfterTheCode(): void {
        $user = $this->userWithASecondFactor(['username' => 'mfa-operator', 'renew_password' => true]);

        $this->withSession(['2fa_in_progress' => 'mfa-operator']);
        $response = $this->post('login/twoFactor', ['code' => $this->currentCodeFor($user)]);

        $this->assertSame(base_url('login/renewPassword'), $this->location($response));
    }

    /**
     * A deep link survives the second factor.
     *
     * It did not: `index()` stored the destination under `requestUrl` and `twoFactor()`
     * read it back under `request_url`, so the two never met and an operator who followed a
     * deep link and has a second factor was always delivered to the frontend's front page.
     * One constant on the controller spells it now.
     */
    public function testTheDestinationSurvivesTheSecondFactor(): void {
        $user = $this->userWithASecondFactor(['username' => 'mfa-operator']);

        $this->withSession(array_merge(
            ['2fa_in_progress' => 'mfa-operator'],
            $this->rememberedDestination($this->deepLink())
        ));
        $response = $this->post('login/twoFactor', ['code' => $this->currentCodeFor($user)]);

        $this->assertSame($this->deepLink(), $this->location($response));
    }

    /**
     * The whole way through, which is the shape the bug actually had: each half was
     * self-consistent and the two disagreed about the name in the middle.
     */
    public function testADeepLinkArrivesAtItsDestinationThroughTheWholeSignIn(): void {
        $user = $this->userWithASecondFactor(['username' => 'mfa-operator', 'password' => 'the-right-one']);

        $this->get('login?request_uri=' . urlencode($this->deepLink()));
        $this->withSession()->post('login', ['username' => 'mfa-operator', 'password' => 'the-right-one']);
        $response = $this->withSession()->post('login/twoFactor', ['code' => $this->currentCodeFor($user)]);

        $this->assertSame($this->deepLink(), $this->location($response));
    }

    /**
     * The marker that says a password has been checked does not outlive the code being
     * accepted, or it would still be there for the next visitor on a shared browser.
     *
     * It is cleared through the session library now rather than with
     * `unset($_SESSION[...])`, which reaches around whatever handler is configured. This
     * test cannot tell the two apart - the harness's session *is* `$_SESSION` - so it holds
     * the outcome, not the mechanism.
     */
    public function testTheSecondFactorMarkerIsClearedOnceTheCodeIsAccepted(): void {
        $user = $this->userWithASecondFactor(['username' => 'mfa-operator']);

        $this->withSession(['2fa_in_progress' => 'mfa-operator']);
        $this->post('login/twoFactor', ['code' => $this->currentCodeFor($user)]);

        $this->assertArrayNotHasKey('2fa_in_progress', $_SESSION);
    }

    // </editor-fold>

    // <editor-fold desc="Renewing a password">

    /**
     * A password that has expired is still a password: the user is signed in on the way to
     * the renewal form, because the form has to know who is changing their password.
     */
    public function testAUserWhoMustRenewIsSignedInAndSentToTheRenewalForm(): void {
        $user = Fixtures::user([
            'username' => 'expired',
            'password' => 'the-right-one',
            'renew_password' => true,
        ]);

        $response = $this->post('login', ['username' => 'expired', 'password' => 'the-right-one']);

        $this->assertSame((int) $user->id, (int) $this->whoTheSessionSaysIsSignedIn());
        $this->assertSame(base_url('login/renewPassword'), $this->location($response));
    }

    /**
     * A user who must renew *and* has a second factor is not signed in by the password. The
     * renewal branch repeats the second-factor check, and a change that dropped it there
     * would leave the weaker of the two paths in place for exactly the accounts that are
     * already in a bad state.
     */
    public function testAUserWhoMustRenewStillHasToPassTheSecondFactor(): void {
        $this->userWithASecondFactor([
            'username' => 'expired-mfa',
            'password' => 'the-right-one',
            'renew_password' => true,
        ]);

        $response = $this->post('login', ['username' => 'expired-mfa', 'password' => 'the-right-one']);

        $this->assertNull($this->whoTheSessionSaysIsSignedIn());
        $this->assertSame('expired-mfa', $_SESSION['2fa_in_progress'] ?? null);
        $this->assertSame(base_url('login/twoFactor'), $this->location($response));
    }

    /**
     * The new password replaces the old one, and the old one stops working. Asserted by
     * signing in with each of them afterwards rather than by reading the row, because the
     * auth extension writes on a connection this test's transaction cannot see.
     *
     * The renewal flag is cleared at the same time - had it not been, the user would be
     * sent straight back to this form on their next sign-in, for ever.
     */
    public function testANewPasswordReplacesTheOldOne(): void {
        $user = Fixtures::user([
            'username' => 'renewing',
            'password' => 'the-old-one',
            'renew_password' => true,
        ]);

        $this->withSession(['user_id' => $user->id]);
        $renewal = $this->post('login/renewPassword', [
            'password' => 'A-brand-new-1',
            'password_confirm' => 'A-brand-new-1',
        ]);
        $this->assertSame(getFrontendUrl(), $this->location($renewal));

        $withTheOldOne = $this->post('login', ['username' => 'renewing', 'password' => 'the-old-one']);
        $this->assertStringContainsString('Wrong username or password', $this->body($withTheOldOne));

        $withTheNewOne = $this->post('login', ['username' => 'renewing', 'password' => 'A-brand-new-1']);
        $this->assertSame(getFrontendUrl(), $this->location($withTheNewOne), 'and no longer asked to renew');
    }

    /**
     * The renewal form is served to anybody, and the change is only made for a session that
     * names a user. A stranger posting a perfectly valid password therefore changes nothing
     * at all - and is told so, which used to be the missing half: the form came back with no
     * message, indistinguishable from a password that was accepted.
     */
    public function testAStrangerCannotRenewAnybodysPassword(): void {
        Fixtures::user(['username' => 'untouched', 'password' => 'the-old-one']);

        $page = $this->body($this->post('login/renewPassword', [
            'password' => 'A-brand-new-1',
            'password_confirm' => 'A-brand-new-1',
        ]));

        $this->assertSame('Your sign-in has expired. Sign in again.', $this->theWarningShownOn($page));

        $stillWorks = $this->post('login', ['username' => 'untouched', 'password' => 'the-old-one']);
        $this->assertSame(getFrontendUrl(), $this->location($stillWorks));
    }

    /**
     * Two fields, and they have to agree. The browser checks this too, which is exactly why
     * the server has to: the browser's copy is advice, and a posted form does not have to
     * come from the browser.
     */
    public function testAMistypedConfirmationChangesNothing(): void {
        $user = Fixtures::user(['username' => 'renewing', 'password' => 'the-old-one']);

        $this->withSession(['user_id' => $user->id]);
        $page = $this->body($this->post('login/renewPassword', [
            'password' => 'A-brand-new-1',
            'password_confirm' => 'A-brand-new-2',
        ]));

        $this->assertSame('Must be identical', $this->theWarningShownOn($page));

        $stillWorks = $this->post('login', ['username' => 'renewing', 'password' => 'the-old-one']);
        $this->assertSame(getFrontendUrl(), $this->location($stillWorks));
    }

    /**
     * The four rules a new password has to satisfy. This form is the only place kso has an
     * opinion about password strength at all, so a rule quietly dropped here is a rule
     * dropped everywhere.
     *
     * The message names the *first* rule that failed. The four checks used to write to one
     * variable without stopping, so it named whichever was checked last - and "At least one
     * letter" could never be it, because a password with no letter has no capital either
     * and the capital rule ran afterwards.
     */
    #[DataProvider('theWaysANewPasswordIsRefused')]
    public function testAWeakPasswordIsRefusedAndTheOldOneKept(string $password, string $expected): void {
        $user = Fixtures::user(['username' => 'renewing', 'password' => 'the-old-one']);

        $this->withSession(['user_id' => $user->id]);
        $page = $this->body($this->post('login/renewPassword', [
            'password' => $password,
            'password_confirm' => $password,
        ]));

        $this->assertSame($expected, $this->theWarningShownOn($page));

        $stillWorks = $this->post('login', ['username' => 'renewing', 'password' => 'the-old-one']);
        $this->assertSame(getFrontendUrl(), $this->location($stillWorks), 'the old password still works');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function theWaysANewPasswordIsRefused(): array {
        return [
            'seven characters' => ['Abcdef1', 'At least eight characters'],
            'no digit' => ['Abcdefgh', 'At least one number'],
            'no capital' => ['abcdefg1', 'At least one uppercase letter'],

            // The one that could not be reached: no letter and no capital, and the capital
            // rule used to be the one that got the last word.
            'digits only' => ['12345678', 'At least one letter'],

            // Too short *and* missing three of the four. The most basic complaint is the
            // one worth showing.
            'nothing at all' => ['abc', 'At least eight characters'],
        ];
    }

    /**
     * Eight characters is the boundary, and the rule is `< 8` rather than `<= 8`. A rule
     * written one character out is the kind of thing nobody notices until a password
     * manager generates one.
     */
    public function testEightCharactersIsEnough(): void {
        $user = Fixtures::user(['username' => 'renewing', 'password' => 'the-old-one']);

        $this->withSession(['user_id' => $user->id]);
        $renewal = $this->post('login/renewPassword', [
            'password' => 'Abcdefg1',
            'password_confirm' => 'Abcdefg1',
        ]);

        $this->assertSame(getFrontendUrl(), $this->location($renewal));
    }

    /**
     * Where the user lands once the password is changed, when the sign-in that sent them
     * here remembered a destination.
     */
    public function testARenewedPasswordLandsOnTheRememberedDestination(): void {
        $user = Fixtures::user(['username' => 'renewing', 'password' => 'the-old-one']);

        $this->withSession(array_merge(
            ['user_id' => $user->id],
            $this->rememberedDestination($this->deepLink())
        ));
        $renewal = $this->post('login/renewPassword', [
            'password' => 'A-brand-new-1',
            'password_confirm' => 'A-brand-new-1',
        ]);

        $this->assertSame($this->deepLink(), $this->location($renewal));
    }

    public function testTheRenewalFormAsksForThePasswordTwice(): void {
        $page = $this->body($this->get('login/renewPassword'));

        $this->assertStringContainsString('name="password"', $page);
        $this->assertStringContainsString('name="password_confirm"', $page);
    }

    // </editor-fold>

    // <editor-fold desc="Forgotten passwords">

    public function testTheForgottenPasswordFormAsksForAUsername(): void {
        $page = $this->body($this->get('login/forgotPassword'));

        $this->assertStringContainsString('name="username"', $page);
    }

    /**
     * Asking for the form changes nothing. Worth holding on its own, because the method
     * that does the work is reached from the same URL and the only thing separating them is
     * whether a `username` field was posted.
     */
    public function testAskingForTheFormResetsNobodysPassword(): void {
        Fixtures::user(['username' => 'untouched@example.org', 'password' => 'the-old-one']);

        $page = $this->body($this->get('login/forgotPassword'));

        // Nothing has been decided yet, so nothing is said. A form arriving with an answer
        // already on it would mean the lookup had run without an address to look up.
        $this->assertStringNotContainsString('alert alert-warning', $page);

        $stillWorks = $this->post('login', ['username' => 'untouched@example.org', 'password' => 'the-old-one']);
        $this->assertSame(getFrontendUrl(), $this->location($stillWorks));
    }

    // The first line of `forgotPassword()` reads the destination out of flashdata and writes
    // it straight back, so that a detour to this form does not lose where the visitor was
    // going. It is not asserted below: whether flashdata survives a request is the session
    // handler's job, and the harness replaces the session handler. Removing the line leaves
    // every test here green.

    /**
     * Asking changes nothing about the account. Anyone who knows an operator's address can
     * ask on their behalf, so the password is only replaced once the link in the mail has
     * been followed.
     */
    public function testAskingForANewPasswordLeavesTheOldOneWorking(): void {
        $this->catchEmail();
        Fixtures::user(['username' => 'forgetful@example.org', 'password' => 'the-old-one']);

        $told = $this->body($this->post('login/forgotPassword', ['username' => 'forgetful@example.org']));
        $this->assertStringContainsString('a link to choose a new password is on its way', $told);

        $withTheOldOne = $this->post('login', ['username' => 'forgetful@example.org', 'password' => 'the-old-one']);
        $this->assertSame(getFrontendUrl(), $this->location($withTheOldOne));
    }

    /**
     * The mail carries a link and no password, and the token in it is not what is stored:
     * a dump of the users table should not hand out working links.
     */
    public function testTheMailCarriesALinkAndTheDatabaseOnlyItsHash(): void {
        $email = $this->catchEmail();
        $user = Fixtures::user(['username' => 'forgetful@example.org', 'password' => 'the-old-one']);

        $this->post('login/forgotPassword', ['username' => 'forgetful@example.org']);

        $token = $this->tokenIn($email);
        $stored = $this->storedUser($user->id);
        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $stored->password_reset_token_hash);
        $this->assertStringNotContainsString($token, (string) $stored->password_reset_token_hash);
    }

    /**
     * The link goes to the installation's configured address, not to whatever `Host` the
     * request that asked for it carried.
     */
    public function testTheLinkPointsAtTheConfiguredAddress(): void {
        $email = $this->catchEmail();
        Fixtures::user(['username' => 'forgetful@example.org']);
        $asFound = getenv('BASE_URL');
        putenv('BASE_URL=https://kso.example.org');

        // A fresh App config, read with this BASE_URL - and a caller that names another host.
        \CodeIgniter\Config\Factories::reset('config');
        $_SERVER['HTTP_HOST'] = 'attacker.example';
        try {
            $this->post('login/forgotPassword', ['username' => 'forgetful@example.org']);
        } finally {
            $asFound === false ? putenv('BASE_URL') : putenv("BASE_URL={$asFound}");
            \CodeIgniter\Config\Factories::reset('config');
        }

        $this->assertStringContainsString('https://kso.example.org/api/login/resetPassword?token=', $this->bodyOf($email));
    }

    /**
     * An installation that cannot send mail says the same as one that can, and the account
     * keeps its password. It used to lose it: the password was replaced before the mail
     * failed, and then existed nowhere.
     */
    public function testAnInstallationThatCannotSendMailLeavesThePasswordAlone(): void {
        $this->pretendEmailIsNotConfigured();
        Fixtures::user(['username' => 'forgetful@example.org', 'password' => 'the-old-one']);

        $told = $this->body($this->post('login/forgotPassword', ['username' => 'forgetful@example.org']));
        $this->assertStringContainsString('a link to choose a new password is on its way', $told);

        $withTheOldOne = $this->post('login', ['username' => 'forgetful@example.org', 'password' => 'the-old-one']);
        $this->assertSame(getFrontendUrl(), $this->location($withTheOldOne));
    }

    /**
     * A known and an unknown address are told the same, so the form cannot be used to find
     * out who has an account.
     */
    public function testAnUnknownAddressIsToldTheSameAsAKnownOne(): void {
        $this->catchEmail();
        Fixtures::user(['username' => 'known@example.org']);

        $known = $this->body($this->post('login/forgotPassword', ['username' => 'known@example.org']));
        $unknown = $this->body($this->post('login/forgotPassword', ['username' => 'nobody@example.org']));

        $this->assertSame($this->theMessageOn($known), $this->theMessageOn($unknown));
    }

    /**
     * The whole way: ask, follow the link, choose, and sign in with the new password.
     */
    public function testFollowingTheLinkSetsTheNewPassword(): void {
        $email = $this->catchEmail();
        Fixtures::user(['username' => 'forgetful@example.org', 'password' => 'the-old-one']);
        $this->post('login/forgotPassword', ['username' => 'forgetful@example.org']);
        $token = $this->tokenIn($email);

        $form = $this->body($this->get('login/resetPassword?token=' . $token));
        $this->assertStringContainsString('name="password_confirm"', $form);

        $done = $this->post('login/resetPassword?token=' . $token, [
            'password' => 'A-brand-new-1',
            'password_confirm' => 'A-brand-new-1',
        ]);
        $this->assertStringStartsWith(base_url('login') . '?error_message=', $this->location($done));
        $this->assertNull($this->whoTheSessionSaysIsSignedIn(), 'the link sets a password; signing in is still the form, and the second factor');

        $withTheNewOne = $this->post('login', ['username' => 'forgetful@example.org', 'password' => 'A-brand-new-1']);
        $this->assertSame(getFrontendUrl(), $this->location($withTheNewOne));
    }

    /**
     * A link works once.
     */
    public function testALinkCannotBeUsedTwice(): void {
        $user = Fixtures::user(['username' => 'forgetful@example.org']);
        $token = $this->aResetLinkFor($user);

        $this->post('login/resetPassword?token=' . $token, ['password' => 'A-brand-new-1', 'password_confirm' => 'A-brand-new-1']);
        $again = $this->post('login/resetPassword?token=' . $token, ['password' => 'Another-new-2', 'password_confirm' => 'Another-new-2']);

        $this->assertStringContainsString('expired or has already been used', $this->body($again));
        $withTheFirst = $this->post('login', ['username' => 'forgetful@example.org', 'password' => 'A-brand-new-1']);
        $this->assertSame(getFrontendUrl(), $this->location($withTheFirst));
    }

    /**
     * An expired link, a wrong one and none at all are all turned away without a form.
     */
    #[DataProvider('linksThatDoNotWork')]
    public function testALinkThatDoesNotWorkShowsNoForm(string $which): void {
        $user = Fixtures::user(['username' => 'forgetful@example.org', 'password' => 'the-old-one']);
        $token = $this->aResetLinkFor($user, $which === 'expired' ? '-1 minute' : '+1 hour');

        $query = match ($which) {
            'expired' => $token,
            'wrong' => str_repeat('0', 64),
            'missing' => '',
        };
        $page = $this->post('login/resetPassword?token=' . $query, ['password' => 'A-brand-new-1', 'password_confirm' => 'A-brand-new-1']);

        $this->assertStringContainsString('expired or has already been used', $this->body($page));
        $withTheOldOne = $this->post('login', ['username' => 'forgetful@example.org', 'password' => 'the-old-one']);
        $this->assertSame(getFrontendUrl(), $this->location($withTheOldOne));
    }

    public static function linksThatDoNotWork(): array {
        return ['expired' => ['expired'], 'wrong' => ['wrong'], 'missing' => ['missing']];
    }

    /**
     * The new password is held to the same rules as a renewal, and a refused one leaves the
     * link working.
     */
    public function testAWeakPasswordThroughTheLinkIsRefusedAndTheLinkKept(): void {
        $user = Fixtures::user(['username' => 'forgetful@example.org']);
        $token = $this->aResetLinkFor($user);

        $refused = $this->body($this->post('login/resetPassword?token=' . $token, ['password' => 'short', 'password_confirm' => 'short']));
        $this->assertSame('At least eight characters', $this->theWarningShownOn($refused));

        $mistyped = $this->body($this->post('login/resetPassword?token=' . $token, ['password' => 'A-brand-new-1', 'password_confirm' => 'A-brand-new-2']));
        $this->assertSame('Must be identical', $this->theWarningShownOn($mistyped));

        $this->assertNotNull(User::FindByPasswordResetToken($token));
    }

    // </editor-fold>

    // <editor-fold desc="The success page">

    /**
     * A visitor with no session is sent back to the form rather than being shown anything.
     */
    public function testTheSuccessPageSendsAStrangerBackToTheForm(): void {
        $response = $this->get('login/success');

        $this->assertSame(base_url('/login'), $this->location($response));
    }

    /**
     * A signed-in visitor is greeted by name.
     *
     * The page used to fatal for precisely the visitors it exists to greet:
     * `AuthExtension::checkSession()` is typed for the auth extension's own user entity,
     * which has a `name()`, but in kso the model hands back `App\Entities\User`, which did
     * not. Nothing in kso links here, which is why it survived.
     */
    public function testTheSuccessPageGreetsASignedInUserByName(): void {
        $user = Fixtures::user(['username' => 'greeted', 'first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $this->withSession(['user_id' => $user->id]);

        $this->assertStringContainsString('Ada Lovelace', $this->body($this->get('login/success')));
    }

    /**
     * A user with no surname is greeted by the name they do have, not by it plus a space.
     */
    public function testAUserWithNoSurnameIsGreetedByTheNameTheyHave(): void {
        $user = Fixtures::user(['username' => 'greeted', 'first_name' => 'Ada', 'last_name' => '']);

        $this->withSession(['user_id' => $user->id]);

        $this->assertStringContainsString('Welcome Ada', $this->body($this->get('login/success')));
    }

    /**
     * The name is whatever the user or an administrator typed, so it is written as text.
     */
    public function testTheNameOnTheSuccessPageIsWrittenAsText(): void {
        $user = Fixtures::user(['username' => 'greeted', 'first_name' => '<b>Ada</b>', 'last_name' => '']);

        $this->withSession(['user_id' => $user->id]);

        $this->assertStringContainsString('Welcome &lt;b&gt;Ada&lt;/b&gt;', $this->body($this->get('login/success')));
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    private function body(\CodeIgniter\Test\TestResponse $response): string {
        return (string) $response->response()->getBody();
    }

    private function location(\CodeIgniter\Test\TestResponse $response): string {
        return $response->response()->getHeaderLine('Location');
    }

    /**
     * What the renewal form is complaining about, read out of the one element it complains
     * in.
     *
     * Reading the whole page instead would prove nothing: the same form carries a static
     * list of the four rules, in the same words, and "Must be identical" is on it. A test
     * asserting that the page contains that phrase passes with the message removed.
     */
    private function theWarningShownOn(string $page): string {
        preg_match('#id="validation">\s*<p>(.*?)</p>#s', $page, $matches);

        return trim($matches[1] ?? '');
    }

    /**
     * The id this request left in the session, or null if it left none.
     *
     * It is the only thing that makes a browser signed in, so "did this request sign
     * anybody in" is always this question. The harness replaces the session service, so
     * this says what the controller stored and nothing about whether a real handler
     * would keep it.
     */
    private function whoTheSessionSaysIsSignedIn(): ?string {
        return isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;
    }

    /**
     * A session carrying a destination the way CodeIgniter's flashdata carries one.
     *
     * The value is an ordinary session key, and `__ci_vars` is what marks it as lasting
     * only until it is read. `getFlashdata()` returns null without that mark, so a test
     * that set only the value would be testing nothing.
     *
     * @return array<string, mixed>
     */
    private function rememberedDestination(string $url): array {
        return ['requestUrl' => $url, '__ci_vars' => ['requestUrl' => 'new']];
    }

    /**
     * A page in the frontend, which is somewhere a sign-in may send the visitor on to.
     */
    private function deepLink(): string {
        return getFrontendUrl('app/workspaces/7');
    }

    private function userWithASecondFactor(array $overrides = []): User {
        $user = Fixtures::user($overrides);
        $user->updateMFASecret((new MFALib())->createSecret());

        return $user;
    }

    private function currentCodeFor(User $user): string {
        return (new MFALib())->getSetupCode($user->getMFASSecret());
    }

    /**
     * A six-digit code that is not the one the authenticator app would show.
     *
     * Derived from the right one rather than picked, because a constant would have a small
     * but real chance of being correct, and a test that fails once a year is worse than no
     * test. Verification allows a step either side in time, which is a different code from
     * the one next in numerical order.
     */
    private function aCodeThatIsNotTheRightOne(User $user): string {
        $right = $this->currentCodeFor($user);

        return str_pad((string) (((int) $right + 1) % 1000000), strlen($right), '0', STR_PAD_LEFT);
    }

    /**
     * Replaces the mailer with CodeIgniter's mock, which keeps what it was asked to send.
     */
    private function catchEmail(): \CodeIgniter\Test\Mock\MockEmail {
        $this->pretendEmailIsConfigured();
        $email = new \CodeIgniter\Test\Mock\MockEmail(config('Email'));
        \CodeIgniter\Config\Services::injectMock('email', $email);

        return $email;
    }

    private function bodyOf(\CodeIgniter\Test\Mock\MockEmail $email): string {
        return (string) ($email->archive['body'] ?? '');
    }

    private function tokenIn(\CodeIgniter\Test\Mock\MockEmail $email): string {
        preg_match('#resetPassword\?token=([0-9a-f]+)#', $this->bodyOf($email), $matches);

        return $matches[1] ?? '';
    }

    /**
     * A link as the mail would carry it, written straight to the row.
     */
    private function aResetLinkFor(User $user, string $expires = '+1 hour'): string {
        $token = bin2hex(random_bytes(32));
        \Config\Database::connect()->table('users')->where('id', $user->id)->update([
            'password_reset_token_hash' => hash('sha256', $token),
            'password_reset_expires' => date('Y-m-d H:i:s', strtotime($expires)),
        ]);

        return $token;
    }

    private function storedUser(int|string $id): object {
        return \Config\Database::connect()->table('users')->where('id', $id)->get()->getRow();
    }

    private function theMessageOn(string $page): string {
        preg_match('#alert alert-warning mt-4" role="alert">\s*(.*?)\s*</div>#s', $page, $matches);

        return trim($matches[1] ?? '');
    }

    private function pretendEmailIsConfigured(): void {
        putenv('EMAIL_SERVICE_HOST=smtp.invalid');
        putenv('EMAIL_SERVICE_PORT=25');
        putenv('EMAIL_SERVICE_USER=kso');
        putenv('EMAIL_SERVICE_PASS=irrelevant');
        putenv('EMAIL_SERVICE_SENDER=kso@example.org');
    }

    /**
     * @return array<int, array<string, ?string>>
     */
    private function signInAttempts(): array {
        return $this->db->table('sign_in_attempts')->orderBy('id', 'asc')->get()->getResultArray();
    }

    private function failToSignIn(string $username, int $times): void {
        for ($attempt = 0; $attempt < $times; $attempt++) {
            $this->post('login', ['username' => $username, 'password' => "guess-{$attempt}"]);
        }
    }

    private function pretendEmailIsNotConfigured(): void {
        foreach (self::EmailSettings as $name) {
            putenv("{$name}=");
        }
    }

    // </editor-fold>

}
