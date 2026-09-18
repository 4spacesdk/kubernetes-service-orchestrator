<?php namespace App\Tests\Unit\Filters;

use App\Filters\SslRedirect;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Sending plain HTTP to HTTPS, and knowing when not to.
 *
 * Getting this wrong is expensive in both directions. Too eager and a health check on the
 * pod's own address is answered with a redirect to a hostname it cannot resolve, so the
 * pod is restarted as unhealthy. Too shy and sign-ins travel in clear text.
 *
 * The part that is easy to get wrong is **how it knows**: kso sits behind a proxy that
 * terminates TLS, so by the time the request arrives `$_SERVER['HTTPS']` is not set and the
 * original scheme is in `X-Forwarded-Proto`. A filter that only looked at the first would
 * redirect every request for ever, which is a loop rather than a mistake.
 */
class SslRedirectTest extends CIUnitTestCase {

    private const Host = 'kso.example.org';

    public function testAPlainRequestOnTheInstancesOwnHostIsRedirected(): void {
        $this->arrive(host: self::Host, uri: '/api/login');

        $response = $this->filter();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://kso.example.org/api/login', $response->getHeaderLine('Location'));
        $this->assertSame(301, $response->getStatusCode(), 'permanent, so browsers stop asking');
    }

    /**
     * Turned off is the default, and an installation that has not asked for it must not be
     * redirected - kso is also run behind proxies that do this themselves.
     */
    public function testNothingHappensWhenTheInstallationHasNotAskedForIt(): void {
        $this->arrive(host: self::Host, redirectEnabled: false);

        $this->assertNull($this->filter());
    }

    /**
     * The proxy has already terminated TLS, so `HTTPS` is unset and the truth is in the
     * header. Without this the filter redirects a request that is already secure, to the
     * same address, for ever.
     */
    #[DataProvider('theWaysARequestIsAlreadySecure')]
    public function testARequestThatIsAlreadySecureIsLeftAlone(array $server): void {
        $this->arrive(host: self::Host, server: $server);

        $this->assertNull($this->filter());
    }

    /**
     * @return array<string, array{0: array<string, string>}>
     */
    public static function theWaysARequestIsAlreadySecure(): array {
        return [
            'served directly over tls' => [['HTTPS' => 'on']],
            'served directly, announced in capitals' => [['HTTPS' => 'On']],
            'terminated by a proxy' => [['HTTP_X_FORWARDED_PROTO' => 'https']],
            'terminated by a proxy that shouts' => [['HTTP_X_FORWARDED_PROTO' => 'HTTPS']],
        ];
    }

    /**
     * `HTTPS` is set to `off` on some servers rather than being absent, which is not the
     * same as being secure.
     */
    public function testHttpsSetToOffIsNotSecure(): void {
        $this->arrive(host: self::Host, server: ['HTTPS' => 'off']);

        $this->assertInstanceOf(RedirectResponse::class, $this->filter());
    }

    /**
     * Only the hostname the instance is served on. A request that arrives on the pod's own
     * address - which is how a Kubernetes liveness probe arrives - is answered rather than
     * redirected somewhere it cannot follow.
     */
    public function testARequestOnAnotherHostnameIsLeftAlone(): void {
        $this->arrive(host: '10.42.0.17:8080');

        $this->assertNull($this->filter());
    }

    public function testARequestWithNoHostAtAllIsLeftAlone(): void {
        $this->arrive(host: '');

        $this->assertNull($this->filter());
    }

    /**
     * A request for the root carries no `REQUEST_URI` in some setups, and the redirect has
     * to go somewhere rather than to `https://host`.
     */
    public function testARequestWithoutAUriIsSentToTheRoot(): void {
        $this->arrive(host: self::Host, uri: null);

        $this->assertSame('https://kso.example.org/', $this->filter()->getHeaderLine('Location'));
    }

    // <editor-fold desc="Fixtures">

    /**
     * @param array<string, string> $server
     */
    private function arrive(
        string $host,
        ?string $uri = '/',
        bool $redirectEnabled = true,
        array $server = []
    ): void {
        putenv('SSL_REDIRECT=' . ($redirectEnabled ? '1' : ''));
        putenv('BASE_URL=' . self::Host);

        $_SERVER['HTTP_HOST'] = $host;
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['REQUEST_URI']);

        if ($uri !== null) {
            $_SERVER['REQUEST_URI'] = $uri;
        }

        foreach ($server as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }

    private function filter(): ?RedirectResponse {
        return (new SslRedirect())->before(service('request'));
    }

    protected function tearDown(): void {
        putenv('SSL_REDIRECT');
        putenv('BASE_URL');
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['REQUEST_URI']);

        parent::tearDown();
    }

    // </editor-fold>

}
