<?php namespace App\Tests\Unit\Config;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Cookie;

/**
 * The session cookie is Secure when kso is reached over https, so it is never sent in the
 * clear - and not otherwise, so a development setup on plain http can still sign in.
 */
class CookieTest extends CIUnitTestCase {

    protected function tearDown(): void {
        Factories::reset('config');
        parent::tearDown();
    }

    public function testSecureOverHttps(): void {
        $this->assertTrue($this->cookieFor('https://kso.example.org/api/')->secure);
    }

    public function testNotSecureOverHttp(): void {
        $this->assertFalse($this->cookieFor('http://localhost:8950/api/')->secure);
    }

    private function cookieFor(string $baseUrl): Cookie {
        $app = new App();
        $app->baseURL = $baseUrl;
        Factories::injectMock('config', 'App', $app);

        return new Cookie();
    }

}
