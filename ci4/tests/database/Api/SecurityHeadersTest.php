<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;

/**
 * The headers a browser is told to apply to what kso sends it.
 *
 * They were all off: `secureheaders` was commented out in `Config/Filters.php`, and the
 * responses carried `Server: Apache/2.4.66 (Unix)` and `X-Powered-By: PHP/8.3.15` - two
 * version numbers and an invitation to look up what is wrong with them.
 *
 * **Only half of this is testable here**, and it is worth knowing which half. kso is served
 * as two things: `/api`, which is this application, and `/app`, which is the built Vue app -
 * static files Apache serves without PHP ever running. A test that sends a request through
 * the framework reaches the first and can say nothing about the second, so the headers are
 * set in **both** places: `docker/apache/httpd.conf` for every response the server makes,
 * and this filter for the API. The Apache half is asserted as configuration below, which is
 * weaker, and was checked against a running container by hand.
 */
class SecurityHeadersTest extends ControllerTestCase {

    public function testAnApiResponseCarriesTheSecurityHeaders(): void {
        $response = $this->signedIn()->get('deployments')->response();

        $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('none', $response->getHeaderLine('X-Permitted-Cross-Domain-Policies'));
        $this->assertSame('same-origin', $response->getHeaderLine('Referrer-Policy'));
    }

    /**
     * A refusal does **not** get them from the framework, and this says so rather than
     * leaving it to be discovered.
     *
     * `RestExtension\Hooks::exceptionHandler()` builds the response itself and calls
     * `send()` on it, so an unauthorized request never reaches an `after` filter. Nothing
     * here can fix that without changing the extension's error handling, and it is the
     * clearest argument for setting the headers in Apache as well: that layer sees every
     * response, including the ones the framework never finishes.
     */
    public function testARefusedRequestDoesNotGetThemFromTheFramework(): void {
        try {
            $response = $this->get('deployments')->response();
        } catch (\RestExtension\Exceptions\UnauthorizedException) {
            $response = \CodeIgniter\Config\Services::response();
        }

        $this->assertSame('', $response->getHeaderLine('X-Frame-Options'));
    }

    // <editor-fold desc="What Apache is configured to send">

    /**
     * The headers the framework cannot set for the Vue app, asserted where they are written.
     *
     * Configuration held against a list is a weak test - it says the line is there, not that
     * Apache sends it - but the alternative is no test at all, and the failure it guards
     * against is somebody tidying the config file.
     */
    public function testTheServerIsConfiguredToSendThemForEverythingItServes(): void {
        $conf = (string) file_get_contents(ROOTPATH . '../docker/apache/httpd.conf');

        foreach ([
            'Header always set X-Frame-Options "SAMEORIGIN"',
            'Header always set X-Content-Type-Options "nosniff"',
            'Header always set X-Permitted-Cross-Domain-Policies "none"',
            'Header always set Referrer-Policy "same-origin"',
            'Header always unset X-Powered-By',
            'ServerTokens Prod',
            'ServerSignature Off',
        ] as $line) {
            $this->assertStringContainsString($line, $conf);
        }
    }

    /**
     * HSTS only when the request arrived over TLS.
     *
     * kso usually sits behind a proxy that terminates it, so the scheme is in
     * `X-Forwarded-Proto`. Sent from an installation that is not on HTTPS, the header is a
     * promise nothing can keep: a browser that honoured it would refuse the site until the
     * max-age ran out.
     */
    public function testHstsIsTiedToTheRequestHavingBeenOverTls(): void {
        $conf = (string) file_get_contents(ROOTPATH . '../docker/apache/httpd.conf');

        $this->assertStringContainsString('SetEnvIf X-Forwarded-Proto "^https$" KSO_OVER_TLS', $conf);
        $this->assertStringContainsString('SetEnvIf HTTPS "^on$" KSO_OVER_TLS', $conf);
        $this->assertStringContainsString(
            'Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains" env=KSO_OVER_TLS',
            $conf
        );
    }

    /**
     * And the version number PHP puts on every response, switched off in the image.
     */
    public function testPhpDoesNotAnnounceItsVersion(): void {
        $this->assertStringContainsString(
            'expose_php=Off',
            (string) file_get_contents(ROOTPATH . '../docker/Dockerfile')
        );
    }

    /**
     * The Content-Security-Policy on the app and the sign-in pages: only scripts kso serves.
     * The access token lives in localStorage, so a script that ran there would hold the
     * clusters.
     *
     * Checked against a production build in a browser when it was written; this holds the
     * parts that matter - no inline script, no eval - and the scope.
     */
    public function testTheAppAndTheSignInPagesOnlyRunScriptsKsoServes(): void {
        $conf = (string) file_get_contents(ROOTPATH . '../docker/apache/httpd.conf');

        $this->assertSame(1, preg_match('#Header always set Content-Security-Policy "([^"]+)" "expr=([^"]+)"#', $conf, $m));
        [, $policy, $scope] = $m;

        $this->assertStringContainsString("script-src 'self';", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertDoesNotMatchRegularExpression("#script-src[^;]*'unsafe-inline'#", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString('${ZMQ_EXTERNAL_URL}', $policy, 'the push socket, by name');
        $this->assertStringContainsString('(app|api/login)', $scope);

        // The variable always expands: an unset one leaves the literal in the header.
        $this->assertStringContainsString('ENV ZMQ_EXTERNAL_URL=""', (string) file_get_contents(ROOTPATH . '../docker/Dockerfile'));
    }
    // </editor-fold>

}
