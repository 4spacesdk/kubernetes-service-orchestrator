<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use CodeIgniter\Cookie\Cookie;
use CodeIgniter\Test\TestResponse;
use Config\App;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The two endpoints a browser uses to get an access token and to renew one.
 *
 * Both are public by definition - a client that has no token cannot present one to ask
 * for one - so what they refuse is as much of their job as what they grant.
 *
 * **Neither endpoint decides anything about the grant.** They post the client's fields to
 * the authorisation server at `base_url('token')`, which is the auth extension's own
 * endpoint on this same host, and translate the answer. So there is no point asserting
 * that a wrong password is rejected here; the authorisation server rejects it. What is
 * worth pinning is the translation, and it is not a pass-through:
 *
 * - the refresh token is taken out of the answer and put in an encrypted, httpOnly cookie,
 *   which is the whole point of the endpoint existing - script running in the page cannot
 *   read it, so an XSS cannot walk away with a year of access;
 * - the scope is handed on untouched, and scope is the whole of a client's power
 *   everywhere else in kso (`Client::HasScope()`);
 * - every refusal the authorisation server makes - wrong code, unknown client, missing
 *   grant type, a refresh token that has expired or has already been spent - collapses
 *   into one message that says none of them apart. Although the answer it collapsed also
 *   travels back under `debug`, which is a finding of its own and has its own test.
 *
 * **How the authorisation server is stood in for.** The call out is a curl to whatever
 * `base_url('token')` resolves to, so the seam is the url. These tests point `baseURL` at
 * a `file://` directory and put the answer in a file called `token`; curl reads it and the
 * controller cannot tell the difference. The cost is that the *outgoing* fields are not
 * observable this way - a file url swallows the post body - so what is pinned here is what
 * the controller does with an answer, not what it asks. Watching the outgoing request
 * would mean listening on a real socket, and a fixed port in a suite three developers run
 * at once is a worse problem than the one it solves.
 */
class OAuthAgentApiTest extends ControllerTestCase {

    private const COOKIE_NAME = 'Tokens';
    private const COOKIE_PREFIX = 'OAuthAgent-';

    private string $upstreamDirectory;
    private string $realBaseUrl;

    public function setUp(): void {
        parent::setUp();

        // These two methods send the response themselves rather than returning it, and
        // `send()` ends in a real `setcookie()`. PHPUnit has already written to stdout by
        // then, so the call is a "headers already sent" warning and the request dies on
        // the one line this test file is about. Pretending leaves the cookie on the
        // response object, which is where the assertions read it from anyway.
        service('response')->pretend(true);

        // Per process: three test runs can be in flight at once on the same machine.
        $this->upstreamDirectory = sys_get_temp_dir() . '/kso-oauth-agent-' . getmypid();
        if (!is_dir($this->upstreamDirectory)) {
            mkdir($this->upstreamDirectory, 0777, true);
        }

        $this->realBaseUrl = config(App::class)->baseURL;
        config(App::class)->baseURL = $this->upstreamUrl();
    }

    /**
     * `localhost` rather than an empty authority: CodeIgniter's `SiteURI` reads the first
     * path segment of `file:///tmp/...` as the host and hands curl `file://tmp/tmp/...`.
     * A file url with an explicit localhost is the same file to curl and survives the
     * round trip through the framework's url building.
     */
    private function upstreamUrl(string $suffix = ''): string {
        return 'file://localhost' . $this->upstreamDirectory . $suffix . '/';
    }

    public function tearDown(): void {
        config(App::class)->baseURL = $this->realBaseUrl;

        @unlink($this->upstreamDirectory . '/token');
        @rmdir($this->upstreamDirectory);

        unset($_COOKIE[self::COOKIE_PREFIX . self::COOKIE_NAME]);
        service('superglobals')->setCookieArray([]);
        service('superglobals')->setServer('HTTPS', 'off');

        parent::tearDown();
    }

    /**
     * What the controller declares about itself, held next to what is actually enforced.
     *
     * `requireAuth()` is the author's statement about which methods may be reached without
     * a token; nothing calls it at runtime, the `is_public` column decides. Here the two
     * agree, and they have to: an endpoint whose job is to hand out the first token cannot
     * ask for one to do it. Closing either half would lock every client out of signing in,
     * so both are asserted - see SEC-10 for what the two drifting apart costs.
     */
    public function testBothEndpointsAreOpenInTheControllerAndInTheTable(): void {
        $controller = new \App\Controllers\OAuthAgent();

        $this->assertFalse($controller->requireAuth('token'));
        $this->assertFalse($controller->requireAuth('refresh'));

        // `from` is a reserved word, so the column is quoted by hand.
        $rows = $this->db->table('api_routes')
            ->select('`from`, is_public', false)
            ->where('to like', '%OAuthAgent%')
            ->get()
            ->getResultArray();

        $this->assertSame(
            ['oauth-agent/token' => '1', 'oauth-agent/refresh' => '1'],
            array_column($rows, 'is_public', 'from')
        );
    }

    // <editor-fold desc="token()">

    /**
     * What a client gets when the authorisation server grants the exchange.
     *
     * The list of keys is the contract the frontend is written against, and `scope` is the
     * load-bearing one: it is what every authorisation decision in kso is made from later.
     * If it were dropped or rewritten here, a client would silently have either less power
     * than it was granted or - worse - a token whose scope nobody checked.
     */
    public function testAGrantedTokenIsHandedOnWithTheScopeTheAuthorisationServerIssued(): void {
        $this->theAuthorisationServerAnswers(self::aGrant(['scope' => 'workspaces deployments']));

        $body = $this->bodyOf($this->post('oauth-agent/token', ['grant_type' => 'authorization_code']));

        $this->assertSame(
            ['access_token', 'expires_in', 'id_token', 'scope', 'token_type'],
            array_keys($body)
        );
        $this->assertSame('issued-access-token', $body['access_token']);
        $this->assertSame('issued-id-token', $body['id_token']);
        $this->assertSame('workspaces deployments', $body['scope']);
        $this->assertSame('Bearer', $body['token_type']);
        $this->assertSame(3600, $body['expires_in']);
    }

    /**
     * The refresh token is the long-lived credential, and it must never be readable by
     * script in the page. That is the reason this controller exists at all instead of the
     * browser talking to the authorisation server directly: an XSS that can read whatever
     * the token endpoint returned would otherwise get renewal rights for as long as the
     * refresh token lives, not just the hour the access token lives.
     */
    public function testTheRefreshTokenIsKeptOutOfTheResponseTheBrowserCanRead(): void {
        $this->theAuthorisationServerAnswers(self::aGrant(['refresh_token' => 'the-long-lived-secret']));

        $response = $this->post('oauth-agent/token', ['grant_type' => 'authorization_code']);
        $raw = (string) $response->response()->getBody();

        $this->assertArrayNotHasKey('refresh_token', $this->bodyOf($response));
        $this->assertStringNotContainsString('the-long-lived-secret', $raw);
    }

    /**
     * Where the refresh token goes instead.
     *
     * Each attribute is a separate defence and each one has been forgotten somewhere
     * before: without `httponly` an XSS reads it after all; without `SameSite=Strict` any
     * site can make the browser attach it to a cross-site refresh; and the value is
     * encrypted, so the cookie is useless to anything that gets at the jar but not at the
     * application key.
     */
    public function testTheRefreshTokenIsStoredInAnEncryptedCookieScriptCannotRead(): void {
        $this->theAuthorisationServerAnswers(self::aGrant(['refresh_token' => 'the-long-lived-secret']));

        $response = $this->post('oauth-agent/token', ['grant_type' => 'authorization_code']);
        $cookie = $this->cookieOf($response);

        $this->assertNotNull($cookie, 'no refresh token cookie was set');
        $this->assertTrue($cookie->isHTTPOnly());
        $this->assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
        $this->assertStringNotContainsString('the-long-lived-secret', $cookie->getValue());
        $this->assertSame('the-long-lived-secret', $this->refreshTokenIn($cookie));
    }

    /**
     * A cookie without `Secure` is one the browser will also send over plain http, where
     * anyone on the path can read the refresh token off the wire. The flag is taken from
     * `IncomingRequest::isSecure()`, which is true when TLS ended at this process.
     */
    public function testTheCookieIsMarkedSecureWhenTheRequestItselfArrivedOverTls(): void {
        $this->theAuthorisationServerAnswers(self::aGrant());
        service('superglobals')->setServer('HTTPS', 'on');

        $response = $this->post('oauth-agent/token', ['grant_type' => 'authorization_code']);

        $this->assertTrue($this->cookieOf($response)->isSecure());
    }

    /**
     * Today's behaviour, and the one that is actually in production: kso is reached over
     * TLS terminated by an ingress, so `HTTPS` is not set on the request that arrives and
     * the original scheme is in `X-Forwarded-Proto`. CodeIgniter only believes that header
     * from a trusted proxy and `Config\App::$proxyIPs` is empty, so `isSecure()` is false
     * and **the refresh token cookie is issued without `Secure`**.
     *
     * The consequence is a cookie the browser will attach to an http request to the same
     * host - which is a request kso answers, since `SslRedirect` is off by default. Fixing
     * it is a configuration change rather than a change here, which is why this test pins
     * the current answer rather than the one we want.
     */
    public function testTheCookieIsNotMarkedSecureBehindAProxyThatTerminatedTls(): void {
        $this->theAuthorisationServerAnswers(self::aGrant());

        $response = $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->post('oauth-agent/token', ['grant_type' => 'authorization_code']);

        $this->assertFalse($this->cookieOf($response)->isSecure());
    }

    /**
     * Every refusal the authorisation server makes reaches the client as the same
     * sentence, whatever went wrong.
     *
     * Saying no more than "failed" is the right answer for an endpoint anyone can post to:
     * telling `invalid_client` apart from `invalid_grant` would let a stranger find out
     * which client ids exist by trying them. The cost is that a misconfigured deployment
     * and a replayed authorisation code look identical from the outside, so the one
     * message has to cover a support case as well as an attack.
     */
    #[DataProvider('theWaysTheExchangeFails')]
    public function testEveryRefusalReachesTheClientAsTheSameMessage(string $upstreamAnswer): void {
        $this->theAuthorisationServerAnswers($upstreamAnswer);

        $response = $this->post('oauth-agent/token', ['grant_type' => 'authorization_code']);
        $body = $this->bodyOf($response);

        $this->assertSame(400, $response->response()->getStatusCode());
        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('failed to fetch access token', $body['error']);
        $this->assertArrayNotHasKey('access_token', $body);
    }

    public static function theWaysTheExchangeFails(): array {
        return [
            // Wrong or already-spent authorisation code.
            'the code is not valid' => ['{"error":"invalid_grant","error_description":"authorization code is invalid"}'],
            // No client row, or a client that is not allowed this grant.
            'the client is unknown' => ['{"error":"invalid_client","error_description":"client credentials are invalid"}'],
            // The frontend posted no grant_type at all.
            'the grant type is missing' => ['{"error":"invalid_request","error_description":"The grant type was not specified"}'],
            'the grant type is one the client may not use' => ['{"error":"unauthorized_client","error_description":"The grant type is unauthorized"}'],
            // Not an OAuth answer at all: a PHP error page, or a proxy in the way.
            'the answer is not json' => ['<h1>500 Internal Server Error</h1><p>/var/www/html/ci4/app</p>'],
            'the answer is empty' => [''],
            // Everything except the one field that decides whether this was a grant.
            'the answer carries no access token' => ['{"expires_in":3600,"token_type":"Bearer","scope":"workspaces"}'],
        ];
    }

    /**
     * Today's behaviour, and a finding rather than a design: the authorisation server's
     * own answer is handed back to whoever posted, verbatim, under `debug`.
     *
     * `Data::debug()` is not conditional on the environment and `fail()` sends the whole
     * debug store, so the sentence above is the only part of the refusal that is discreet.
     * An anonymous caller learns `invalid_client` from `invalid_grant` after all - and when
     * the authorisation server answers with a PHP error page instead of json, it learns
     * paths on the server as well.
     */
    public function testTheAuthorisationServersOwnAnswerIsEchoedBackToAnAnonymousCaller(): void {
        $this->theAuthorisationServerAnswers('{"error":"invalid_client","error_description":"client credentials are invalid"}');

        $body = $this->bodyOf($this->post('oauth-agent/token', ['grant_type' => 'authorization_code']));

        $this->assertStringContainsString('invalid_client', implode("\n", $body['debug']));
    }

    /**
     * Today's behaviour, and a gap rather than a decision: `access_token` is the only
     * field checked before all six are read, so an answer that is a perfectly valid OAuth
     * grant without an `id_token` ends as an uncaught error - a 500 page for the client
     * instead of either a token or a refusal.
     *
     * It is reachable without anything going wrong: `client_credentials`, and
     * `authorization_code` without the `openid` scope, both answer without an id token.
     * Whoever gives these fields a default will have to change this test, which is the
     * point of it.
     */
    public function testAnAnswerWithoutAnIdTokenIsNotHandledAtAll(): void {
        $this->theAuthorisationServerAnswers('{"access_token":"AT","expires_in":3600,"scope":"workspaces","token_type":"Bearer"}');

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('Undefined array key "id_token"');

        $this->post('oauth-agent/token', ['grant_type' => 'client_credentials']);
    }

    /**
     * The authorisation server is a loopback call to this same host, so it fails when the
     * host is not answering - a container half way through starting, a misconfigured
     * `baseURL`. Nothing about that is a grant, and a client that treated an unreachable
     * server as anything but a refusal would be signing people in without a token.
     */
    public function testAnAuthorisationServerThatCannotBeReachedIsNotAGrant(): void {
        $this->theAuthorisationServerIsUnreachable();

        $response = $this->post('oauth-agent/token', ['grant_type' => 'authorization_code']);

        $this->assertSame(400, $response->response()->getStatusCode());
        $this->assertSame('failed to fetch access token', $this->bodyOf($response)['error']);
    }

    /**
     * The port the developer's machine publishes kso on is stripped out of the loopback
     * url before the call.
     *
     * On a developer machine `base_url()` is built from the browser's host, which is
     * `localhost:8950` - a port that only exists outside the container, where docker maps
     * it. The call to the token endpoint is made from *inside*, where the application
     * answers on the default port, so the port has to come off or every sign-in in
     * development fails to reach an authorisation server that is running fine.
     */
    public function testTheDevelopmentPortIsStrippedFromTheLoopbackUrl(): void {
        config(App::class)->baseURL = $this->upstreamUrl(':8950');
        $this->theAuthorisationServerAnswers(self::aGrant());

        $body = $this->bodyOf($this->post('oauth-agent/token', ['grant_type' => 'authorization_code']));

        $this->assertSame('issued-access-token', $body['access_token']);
    }

    // </editor-fold>

    // <editor-fold desc="refresh()">

    /**
     * A client with no cookie is refused here, before any call is made - the canned grant
     * sitting upstream would have been handed over if the call had happened.
     *
     * This is the branch that matters for a stranger: `refresh` takes no credential of its
     * own, the cookie *is* the credential, so anything that made this endpoint answer
     * without one would be handing out tokens to anybody who posted to it.
     */
    public function testARefreshWithNoCookieNeverReachesTheAuthorisationServer(): void {
        $this->theAuthorisationServerAnswers(self::aGrant());

        $response = $this->post('oauth-agent/refresh', ['grant_type' => 'refresh_token']);
        $body = $this->bodyOf($response);

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('refresh token not found', $body['error']);
        $this->assertArrayNotHasKey('access_token', $body);
    }

    /**
     * Today's behaviour, and worth a decision: this refusal is sent with HTTP 200 while
     * every other refusal from these two endpoints is sent with 400. `fail()` defaults to
     * 200 and this one call site does not pass a code.
     *
     * The frontend survives it because it reads `error` out of the body, but anything that
     * reads the status line - a proxy, a monitor, a new client - cannot tell this apart
     * from a successful renewal.
     */
    public function testTheMissingCookieRefusalIsTheOneThatAnswersWithHttp200(): void {
        $this->theAuthorisationServerAnswers(self::aGrant());

        // Read straight away, both of them: the response object is a shared service, so
        // the second request overwrites the status the first one left on it.
        $withoutCookie = $this->post('oauth-agent/refresh', ['grant_type' => 'refresh_token'])
            ->response()->getStatusCode();

        $this->theAuthorisationServerAnswers('{"error":"invalid_grant"}');
        $this->aBrowserHoldingARefreshToken('spent-token');
        $withRejectedToken = $this->post('oauth-agent/refresh', ['grant_type' => 'refresh_token'])
            ->response()->getStatusCode();

        $this->assertSame(200, $withoutCookie);
        $this->assertSame(400, $withRejectedToken);
    }

    /**
     * A cookie that cannot be decrypted is the same as no cookie, not an error and not an
     * empty token posted to the authorisation server.
     *
     * It happens for real whenever the application's encryption key is rotated: every
     * browser out there is holding a cookie encrypted with the old key, and they all have
     * to fall back to signing in again rather than getting a 500.
     */
    public function testACookieThatCannotBeDecryptedIsTreatedAsNoRefreshTokenAtAll(): void {
        $this->theAuthorisationServerAnswers(self::aGrant());
        $this->theBrowserSendsBack('not-something-this-key-encrypted');

        $body = $this->bodyOf($this->post('oauth-agent/refresh', ['grant_type' => 'refresh_token']));

        $this->assertSame('refresh token not found', $body['error']);
    }

    /**
     * A refresh token that has expired, or that has already been spent once, is refused by
     * the authorisation server and reaches the client as the same opaque message as every
     * other failure. Rotation is the reason a spent token is worth its own case: the
     * server hands out a new refresh token on every renewal, so a replayed one is the
     * signal that someone is using a stolen cookie.
     */
    #[DataProvider('theWaysARefreshTokenIsNoLongerGood')]
    public function testARefreshTokenTheServerNoLongerAcceptsIsRefused(string $upstreamAnswer): void {
        $this->theAuthorisationServerAnswers($upstreamAnswer);
        $this->aBrowserHoldingARefreshToken('no-longer-good');

        $response = $this->post('oauth-agent/refresh', ['grant_type' => 'refresh_token']);

        $this->assertSame(400, $response->response()->getStatusCode());
        $this->assertSame('failed to fetch access token', $this->bodyOf($response)['error']);
        $this->assertArrayNotHasKey('access_token', $this->bodyOf($response));
    }

    public static function theWaysARefreshTokenIsNoLongerGood(): array {
        return [
            'it has expired' => ['{"error":"invalid_grant","error_description":"refresh token has expired"}'],
            'it has already been used' => ['{"error":"invalid_grant","error_description":"Invalid refresh token"}'],
            'the client is not the one it was issued to' => ['{"error":"invalid_client"}'],
        ];
    }

    /**
     * A renewal carries the same five fields as the first exchange, so a client does not
     * have to know which of the two it called. The scope on a renewal is the one the
     * authorisation server decided, not the one the client asked for in its post - a
     * client that could widen its own scope by asking on renewal would make the original
     * grant meaningless.
     */
    public function testARenewedTokenCarriesTheScopeTheAuthorisationServerIssued(): void {
        $this->theAuthorisationServerAnswers(self::aGrant([
            'access_token' => 'renewed-access-token',
            'scope' => 'workspaces',
        ]));
        $this->aBrowserHoldingARefreshToken('still-good');

        $body = $this->bodyOf($this->post('oauth-agent/refresh', [
            'grant_type' => 'refresh_token',
            'scope' => 'workspaces deployments users',
        ]));

        $this->assertSame(
            ['access_token', 'expires_in', 'scope', 'token_type', 'id_token'],
            array_keys($body)
        );
        $this->assertSame('renewed-access-token', $body['access_token']);
        $this->assertSame('workspaces', $body['scope']);
        $this->assertSame('issued-id-token', $body['id_token']);
    }

    /**
     * The whole round trip, cookie and all: the cookie `token()` set is the one the
     * browser sends back to `refresh()`, and the rotated refresh token that comes out of
     * the renewal replaces it.
     *
     * If the new one were not written back, the browser would keep presenting the spent
     * one and every renewal after the first would fail - which looks like a session that
     * drops an hour after sign-in, and is the kind of thing that only shows up in
     * production.
     */
    public function testTheRotatedRefreshTokenReplacesTheOneTheBrowserWasHolding(): void {
        $this->theAuthorisationServerAnswers(self::aGrant(['refresh_token' => 'first-refresh-token']));
        $fromSignIn = $this->cookieOf($this->post('oauth-agent/token', ['grant_type' => 'authorization_code']));

        // What the browser sends back on the next request.
        $this->theBrowserSendsBack($fromSignIn->getValue());

        $this->theAuthorisationServerAnswers(self::aGrant(['refresh_token' => 'second-refresh-token']));
        $afterRenewal = $this->cookieOf($this->post('oauth-agent/refresh', ['grant_type' => 'refresh_token']));

        $this->assertSame('first-refresh-token', $this->refreshTokenIn($fromSignIn));
        $this->assertSame('second-refresh-token', $this->refreshTokenIn($afterRenewal));
    }

    /**
     * The same gap on the renewal, where it is worse: an authorisation server that does
     * not rotate refresh tokens answers a renewal without a `refresh_token`, which is
     * allowed, and every renewal would then end in a server error rather than a token.
     */
    public function testARenewalWithoutANewRefreshTokenIsNotHandledEither(): void {
        $this->theAuthorisationServerAnswers('{"access_token":"AT","expires_in":3600,"scope":"workspaces","token_type":"Bearer","id_token":"IT"}');
        $this->aBrowserHoldingARefreshToken('still-good');

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('Undefined array key "refresh_token"');

        $this->post('oauth-agent/refresh', ['grant_type' => 'refresh_token']);
    }

    // </editor-fold>

    // <editor-fold desc="The authorisation server, stood in for">

    /**
     * Put the authorisation server's answer where the controller's curl will read it.
     */
    private function theAuthorisationServerAnswers(string $body): void {
        file_put_contents($this->upstreamDirectory . '/token', $body);
    }

    private function theAuthorisationServerIsUnreachable(): void {
        @unlink($this->upstreamDirectory . '/token');
    }

    /**
     * A well-formed answer to either grant, with every field the controller reads.
     */
    private static function aGrant(array $overrides = []): string {
        return json_encode(array_merge([
            'access_token' => 'issued-access-token',
            'expires_in' => 3600,
            'id_token' => 'issued-id-token',
            'refresh_token' => 'issued-refresh-token',
            'scope' => 'workspaces deployments',
            'token_type' => 'Bearer',
        ], $overrides));
    }

    /**
     * A browser arriving with a refresh token cookie, encrypted the way the controller
     * wrote it.
     */
    private function aBrowserHoldingARefreshToken(string $refreshToken): void {
        $this->theBrowserSendsBack(service('encrypter')->encrypt(json_encode(['refreshToken' => $refreshToken])));
    }

    /**
     * Put a cookie on the next request.
     *
     * Both places, because the request reads its cookies through the `superglobals`
     * service and that service took its copy of `$_COOKIE` before this test ran.
     */
    private function theBrowserSendsBack(string $cookieValue): void {
        $_COOKIE[self::COOKIE_PREFIX . self::COOKIE_NAME] = $cookieValue;
        service('superglobals')->setCookie(self::COOKIE_PREFIX . self::COOKIE_NAME, $cookieValue);
    }

    private function refreshTokenIn(Cookie $cookie): ?string {
        return json_decode(service('encrypter')->decrypt($cookie->getValue()), true)['refreshToken'] ?? null;
    }

    private function cookieOf(TestResponse $response): ?Cookie {
        return $response->response()->getCookie(self::COOKIE_NAME, self::COOKIE_PREFIX);
    }

    private function bodyOf(TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true) ?? [];
    }

    // </editor-fold>

}
