<?php namespace App\Tests\Unit\Config;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Cookie;

/**
 * The session cookie is Secure when kso is reached over https and the request came over TLS, so it
 * is never sent in the clear - and not otherwise, so a development setup on plain http can still
 * sign in, and a call from inside the cluster over plain http gets an answer.
 *
 * CodeIgniter throws rather than send a Secure cookie on a request it does not think is secure.
 * With the base url alone deciding, every plain-http call to an https installation - the scheduler's
 * callout among them - ended in "Attempted to send a secure cookie over a non-secure connection".
 */
class CookieTest extends CIUnitTestCase {

    private mixed $https = null;

    protected function setUp(): void {
        parent::setUp();
        $this->https = $_SERVER['HTTPS'] ?? null;
    }

    protected function tearDown(): void {
        if ($this->https === null) {
            unset($_SERVER['HTTPS']);
        } else {
            $_SERVER['HTTPS'] = $this->https;
        }
        Factories::reset('config');
        parent::tearDown();
    }

    public function testSecureOverHttps(): void {
        $this->assertTrue($this->cookieFor('https://kso.example.org/api/', 'on')->secure);
    }

    public function testNotSecureOverHttp(): void {
        $this->assertFalse($this->cookieFor('http://localhost:8950/api/', null)->secure);
    }

    /**
     * The scheduler's callout, a probe: plain http inside the cluster to an https installation.
     */
    public function testNotSecureForAPlainHttpRequestToAnHttpsInstallation(): void {
        $this->assertFalse($this->cookieFor('https://kso.example.org/api/', null)->secure);
    }

    public function testHttpsOffIsNotTls(): void {
        $this->assertFalse($this->cookieFor('https://kso.example.org/api/', 'off')->secure);
    }

    private function cookieFor(string $baseUrl, ?string $https): Cookie {
        if ($https === null) {
            unset($_SERVER['HTTPS']);
        } else {
            $_SERVER['HTTPS'] = $https;
        }

        $app = new App();
        $app->baseURL = $baseUrl;
        Factories::injectMock('config', 'App', $app);

        return new Cookie();
    }

}
