<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\User;
use App\Fixtures;
use App\Libraries\MFALib;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
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
 * **The controller ends the process in three places.** See
 * `requestThatEndsTheProcess()` - without it those branches take PHPUnit down with them.
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

    public function tearDown(): void {
        foreach ($this->emailSettingsAsFound as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }

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
     * Today's behaviour, and it should not be. The view writes the message out with a bare
     * `<?=` and no escaping, so anything in `error_message` is rendered as markup - on the
     * unauthenticated sign-in page, from a link an attacker controls.
     *
     * This test passes because the hole is open. Closing it turns it red, which is the
     * point: it is here so that a fix is visible rather than silent.
     */
    public function testTheMessageFromTheQueryStringIsWrittenIntoThePageUnescaped(): void {
        $page = $this->body($this->get('login?error_message=' . urlencode('<script>alert(1)</script>')));

        $this->assertStringContainsString('<script>alert(1)</script>', $page);
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
     * Today's behaviour, and worth a decision. A wrong password and an unknown username
     * produce different messages, so the form tells an anonymous visitor whether an
     * account exists. That turns the login page into a way of testing whether a given
     * person has one. See SEC-13.
     */
    public function testWrongPasswordAndUnknownUserAreToldApart(): void {
        Fixtures::user(['username' => 'exists', 'password' => 'the-right-one']);

        $wrongPassword = $this->body($this->post('login', [
            'username' => 'exists',
            'password' => 'not-the-right-one',
        ]));
        $unknownUser = $this->body($this->post('login', [
            'username' => 'does-not-exist',
            'password' => 'anything',
        ]));

        $this->assertStringContainsString('Wrong password', $wrongPassword);
        $this->assertStringContainsString('Unknown username', $unknownUser);
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

        $this->assertStringContainsString('Wrong password', $page);
    }

    /**
     * Today's behaviour, deliberately pinned. Nothing counts wrong passwords and nothing
     * refuses the next attempt, so a password can be guessed at whatever rate the network
     * allows, and the only trace of the attempt is the web server's access log.
     *
     * This is what makes the username enumeration above expensive rather than merely
     * untidy. Adding a lockout or a delay turns this test red, and that is the point of
     * writing it down.
     */
    public function testNoNumberOfWrongPasswordsStopsTheNextAttempt(): void {
        Fixtures::user(['username' => 'guessable', 'password' => 'the-right-one']);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $refused = $this->post('login', ['username' => 'guessable', 'password' => "guess-{$attempt}"]);
            $this->assertStringContainsString('Wrong password', $this->body($refused));
        }

        $response = $this->post('login', ['username' => 'guessable', 'password' => 'the-right-one']);

        $this->assertSame(getFrontendUrl(), $this->location($response));
    }

    // </editor-fold>

    // <editor-fold desc="Where the visitor is sent afterwards">

    /**
     * Today's behaviour, and it should not be. The destination is taken from the query
     * string of the sign-in link with nothing checked about it, so a link to kso's own
     * login page can deliver a freshly authenticated operator to any address at all. The
     * operator sees kso, types their password into kso, and lands somewhere else.
     *
     * Restricting it to this installation's own hostnames turns this test red.
     */
    public function testTheDestinationIsWhateverTheLinkAsksFor(): void {
        Fixtures::user(['username' => 'operator', 'password' => 'the-right-one']);

        $response = $this->post(
            'login?request_uri=' . urlencode('https://somewhere-else.example/collect'),
            ['username' => 'operator', 'password' => 'the-right-one']
        );

        $this->assertSame('https://somewhere-else.example/collect', $this->location($response));
    }

    /**
     * Two spellings of the same idea, read one after the other, so the second wins. Both
     * are in use - `redirect_uri` is what the OAuth flow sends - and a change that reads
     * them in the other order would quietly send OAuth callers to the wrong place.
     */
    public function testRedirectUriIsPreferredOverRequestUri(): void {
        Fixtures::user(['username' => 'operator', 'password' => 'the-right-one']);

        $response = $this->post(
            'login?request_uri=' . urlencode('https://first.example/') . '&redirect_uri=' . urlencode('https://second.example/'),
            ['username' => 'operator', 'password' => 'the-right-one']
        );

        $this->assertSame('https://second.example/', $this->location($response));
    }

    /**
     * The form posts to itself without the query string, so the destination has to survive
     * the round trip in the session. Without it every sign-in that arrived from a deep link
     * would land on the frontend's front page instead.
     */
    public function testTheDestinationSurvivesTheRoundTripThroughTheForm(): void {
        $this->get('login?request_uri=' . urlencode('https://deep.example/link'));

        $this->assertSame('https://deep.example/link', $_SESSION['requestUrl'] ?? null);
        $this->assertArrayHasKey('requestUrl', $_SESSION['__ci_vars'] ?? [], 'kept as flashdata, not for ever');
    }

    /**
     * And the other half of that round trip: what the form remembered is what a successful
     * sign-in uses, even though the posted form carries no destination of its own.
     */
    public function testWhatTheFormRememberedIsWhereTheSignInGoes(): void {
        Fixtures::user(['username' => 'operator', 'password' => 'the-right-one']);

        $this->withSession($this->rememberedDestination('requestUrl', 'https://deep.example/link'));
        $response = $this->post('login', ['username' => 'operator', 'password' => 'the-right-one']);

        $this->assertSame('https://deep.example/link', $this->location($response));
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
        $response = $this->requestThatEndsTheProcess('GET', 'login/twoFactor');

        $this->assertSame(
            base_url('login') . '?error_message=Two factor authentication not initialized.',
            $response->getHeaderLine('Location')
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

        $response = $this->requestThatEndsTheProcess('POST', 'login/twoFactor', [
            'code' => $this->currentCodeFor($user),
        ]);

        $this->assertSame(
            base_url('login') . '?error_message=Two factor authentication not initialized.',
            $response->getHeaderLine('Location')
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

        $response = $this->requestThatEndsTheProcess('GET', 'login/twoFactor');

        $this->assertStringContainsString('Unknown username', $response->getHeaderLine('Location'));
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
     * Today's behaviour, and it is a bug rather than a decision. `index()` stores the
     * destination under `requestUrl` and `twoFactor()` reads it back under `request_url`,
     * so the two never meet: an operator who followed a deep link and has a second factor
     * is always delivered to the frontend's front page instead.
     *
     * Spelling the two keys the same way turns this test red.
     */
    public function testTheDestinationIsLostOnTheWayThroughTheSecondFactor(): void {
        $user = $this->userWithASecondFactor(['username' => 'mfa-operator']);

        $this->withSession(array_merge(
            ['2fa_in_progress' => 'mfa-operator'],
            $this->rememberedDestination('requestUrl', 'https://deep.example/link')
        ));
        $response = $this->post('login/twoFactor', ['code' => $this->currentCodeFor($user)]);

        $this->assertSame(getFrontendUrl(), $this->location($response));
    }

    /**
     * Under the name `twoFactor()` actually reads, the destination does survive. Held
     * separately from the test above so that fixing the spelling leaves one of the two
     * green rather than leaving nothing behind.
     */
    public function testTheDestinationIsUsedWhenItIsSpeltTheWayTwoFactorReadsIt(): void {
        $user = $this->userWithASecondFactor(['username' => 'mfa-operator']);

        $this->withSession(array_merge(
            ['2fa_in_progress' => 'mfa-operator'],
            $this->rememberedDestination('request_url', 'https://deep.example/link')
        ));
        $response = $this->post('login/twoFactor', ['code' => $this->currentCodeFor($user)]);

        $this->assertSame('https://deep.example/link', $this->location($response));
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
        $renewal = $this->requestThatEndsTheProcess('POST', 'login/renewPassword', [
            'password' => 'A-brand-new-1',
            'password_confirm' => 'A-brand-new-1',
        ]);
        $this->assertSame(getFrontendUrl(), $renewal->getHeaderLine('Location'));

        $withTheOldOne = $this->post('login', ['username' => 'renewing', 'password' => 'the-old-one']);
        $this->assertStringContainsString('Wrong password', $this->body($withTheOldOne));

        $withTheNewOne = $this->post('login', ['username' => 'renewing', 'password' => 'A-brand-new-1']);
        $this->assertSame(getFrontendUrl(), $this->location($withTheNewOne), 'and no longer asked to renew');
    }

    /**
     * The renewal form is served to anybody, and the change is only made for a session that
     * names a user. A stranger posting a perfectly valid password therefore changes nothing
     * at all - which is the right outcome, though the form says nothing about it.
     */
    public function testAStrangerCannotRenewAnybodysPassword(): void {
        Fixtures::user(['username' => 'untouched', 'password' => 'the-old-one']);

        $this->post('login/renewPassword', [
            'password' => 'A-brand-new-1',
            'password_confirm' => 'A-brand-new-1',
        ]);

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
     * The message names the *last* rule that failed, not the first: the four checks each
     * overwrite the same variable rather than stopping at the first complaint. That is why
     * '12345678' below is told about capitals and not about letters - and why "At least one
     * letter" can never be the message, since a password with no letter has no capital
     * either and the capital rule is checked afterwards.
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
            'digits only' => ['12345678', 'At least one uppercase letter'],
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
        $renewal = $this->requestThatEndsTheProcess('POST', 'login/renewPassword', [
            'password' => 'Abcdefg1',
            'password_confirm' => 'Abcdefg1',
        ]);

        $this->assertSame(getFrontendUrl(), $renewal->getHeaderLine('Location'));
    }

    /**
     * Where the user lands once the password is changed, when the sign-in that sent them
     * here remembered a destination.
     */
    public function testARenewedPasswordLandsOnTheRememberedDestination(): void {
        $user = Fixtures::user(['username' => 'renewing', 'password' => 'the-old-one']);

        $this->withSession(array_merge(
            ['user_id' => $user->id],
            $this->rememberedDestination('request_url', 'https://deep.example/link')
        ));
        $renewal = $this->requestThatEndsTheProcess('POST', 'login/renewPassword', [
            'password' => 'A-brand-new-1',
            'password_confirm' => 'A-brand-new-1',
        ]);

        $this->assertSame('https://deep.example/link', $renewal->getHeaderLine('Location'));
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
     * Today's behaviour, and the shape of it is worth being precise about, because it is not
     * the usual one. There is no reset token and nothing to click: the address is enough,
     * and the account's password is replaced with a freshly generated one there and then.
     *
     * So anyone who knows an operator's address can lock that operator out of kso, from the
     * unauthenticated form, as often as they like. The generated password is then sent by
     * e-mail in plain text. Replacing this with a token that has to be followed before
     * anything changes turns this test red.
     */
    public function testTheOldPasswordIsAlreadyDeadBeforeTheEmailIsSent(): void {
        $this->pretendEmailIsConfigured();
        Fixtures::user(['username' => 'forgetful@example.org', 'password' => 'the-old-one']);

        $told = $this->body($this->post('login/forgotPassword', ['username' => 'forgetful@example.org']));
        $this->assertStringContainsString('Check your e-mail inbox', $told);

        $withTheOldOne = $this->post('login', ['username' => 'forgetful@example.org', 'password' => 'the-old-one']);
        $this->assertStringContainsString('Wrong password', $this->body($withTheOldOne));
    }

    /**
     * And the reason that ordering matters. An installation that cannot send mail throws on
     * the way out - after the password has already been replaced - so the account is left
     * with a password that now exists nowhere at all. The operator is locked out by asking
     * for help.
     *
     * Generating the password only once the mail has gone turns this test red.
     */
    public function testAnInstallationThatCannotSendMailStillDestroysThePassword(): void {
        $this->pretendEmailIsNotConfigured();
        Fixtures::user(['username' => 'forgetful@example.org', 'password' => 'the-old-one']);

        $refusal = null;
        try {
            $this->post('login/forgotPassword', ['username' => 'forgetful@example.org']);
        } catch (\Exception $e) {
            $refusal = $e->getMessage();
        }
        $this->assertSame('Email host is not configured', $refusal);

        $withTheOldOne = $this->post('login', ['username' => 'forgetful@example.org', 'password' => 'the-old-one']);
        $this->assertStringContainsString('Wrong password', $this->body($withTheOldOne));
    }

    /**
     * The same enumeration as on the sign-in form, from a page that needs no password at
     * all: a known address is told to check its inbox and an unknown one is told it is
     * unknown. Part of SEC-13 rather than a finding of its own.
     */
    public function testAnUnknownAddressIsToldThatItIsUnknown(): void {
        $this->pretendEmailIsConfigured();

        $page = $this->body($this->post('login/forgotPassword', ['username' => 'nobody@example.org']));

        $this->assertStringContainsString('Unknown e-mail', $page);
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
     * Today's behaviour, and it is broken. `AuthExtension::checkSession()` hands back an
     * `App\Entities\User`, which has no `name()` - only the auth extension's own user
     * entity does - so the page fatals for precisely the visitors it is meant to greet.
     *
     * Nothing in kso links here, which is why it has survived. Giving the entity a `name()`
     * turns this test red, and it should then assert on the greeting instead.
     */
    public function testTheSuccessPageFatalsForASignedInUser(): void {
        $user = Fixtures::user(['username' => 'greeted', 'first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $this->withSession(['user_id' => $user->id]);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('name()');
        $this->get('login/success');
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
    private function rememberedDestination(string $key, string $url): array {
        return [$key => $url, '__ci_vars' => [$key => 'new']];
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

    private function pretendEmailIsConfigured(): void {
        putenv('EMAIL_SERVICE_HOST=smtp.invalid');
        putenv('EMAIL_SERVICE_PORT=25');
        putenv('EMAIL_SERVICE_USER=kso');
        putenv('EMAIL_SERVICE_PASS=irrelevant');
        putenv('EMAIL_SERVICE_SENDER=kso@example.org');
    }

    private function pretendEmailIsNotConfigured(): void {
        foreach (self::EmailSettings as $name) {
            putenv("{$name}=");
        }
    }

    /**
     * Send a request to one of the three branches that finish with `send()` and `exit`, and
     * hand back the response the controller had prepared.
     *
     * `exit` in a controller ends the PHP process, and PHPUnit is that process: the run
     * stops where it stands, prints nothing, and leaves an exit status of zero. A suite that
     * simply covered these branches would look like it had passed while having run perhaps
     * half of its tests - which is the failure mode this repository has already been bitten
     * by once, in `BaseController::fail()`.
     *
     * So the response service is replaced for the duration of the request with one that
     * throws instead of sending. The controller runs unchanged and sets the same headers on
     * the same object; the exception unwinds the request one statement before the `exit`.
     *
     * That the `exit` is real is worth remembering rather than working around: in production
     * it skips CodeIgniter's shutdown as well, and with it the `post_system` hook that
     * writes the access log entry.
     */
    private function requestThatEndsTheProcess(string $method, string $path, array $params = []): ResponseInterface {
        $response = new class(config('App')) extends Response {
            public const Marker = 'the controller would have ended the process here';

            public function send() {
                throw new \RuntimeException(self::Marker);
            }
        };
        Services::injectMock('response', $response);

        try {
            $this->call($method, $path, $params);
            $this->fail("{$method} {$path} was expected to end the process, and came back instead.");
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== $response::Marker) {
                throw $e;
            }
        } finally {
            Services::resetSingle('response');
        }

        return $response;
    }

    // </editor-fold>

}
