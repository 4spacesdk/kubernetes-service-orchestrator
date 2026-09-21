<?php namespace App\Tests\Unit\Config;

use CodeIgniter\Test\CIUnitTestCase;
use Config\App;

/**
 * Which address kso writes into absolute urls - redirects, links in mails - and which it
 * does not.
 *
 * It used to be the request's `Host` header whenever there was one, so the caller chose it.
 * Now BASE_URL decides, and other names the installation answers on are listed on purpose.
 */
class BaseUrlTest extends CIUnitTestCase {

    private array $asFound = [];

    protected function setUp(): void {
        parent::setUp();
        foreach (['BASE_URL', 'ALLOWED_HOSTNAMES'] as $name) {
            $this->asFound[$name] = getenv($name);
        }
        $this->asFound['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? null;
    }

    protected function tearDown(): void {
        foreach (['BASE_URL', 'ALLOWED_HOSTNAMES'] as $name) {
            $this->asFound[$name] === false ? putenv($name) : putenv("{$name}={$this->asFound[$name]}");
        }
        if ($this->asFound['HTTP_HOST'] === null) {
            unset($_SERVER['HTTP_HOST']);
        } else {
            $_SERVER['HTTP_HOST'] = $this->asFound['HTTP_HOST'];
        }
        parent::tearDown();
    }

    public function testTheConfiguredAddressWinsOverTheHostTheRequestNames(): void {
        putenv('BASE_URL=https://kso.example.org');
        $_SERVER['HTTP_HOST'] = 'attacker.example';

        $this->assertSame('https://kso.example.org/api/', (new App())->baseURL);
    }

    public function testATrailingSlashOnTheConfiguredAddressIsNotDoubled(): void {
        putenv('BASE_URL=https://kso.example.org/');

        $this->assertSame('https://kso.example.org/api/', (new App())->baseURL);
    }

    public function testOtherNamesAreOnlyTheOnesListed(): void {
        putenv('BASE_URL=https://kso.example.org');
        putenv('ALLOWED_HOSTNAMES= kso.internal , other.example.org,');

        $this->assertSame(['kso.internal', 'other.example.org'], (new App())->allowedHostnames);
    }

    public function testNoOtherNamesUnlessListed(): void {
        putenv('BASE_URL=https://kso.example.org');
        putenv('ALLOWED_HOSTNAMES');

        $this->assertSame([], (new App())->allowedHostnames);
    }

    /**
     * An installation not set up by the chart has no BASE_URL, and keeps working as before.
     */
    public function testWithoutAConfiguredAddressTheRequestHostIsStillUsed(): void {
        putenv('BASE_URL');
        $_SERVER['HTTP_HOST'] = 'kso.lan';

        $this->assertSame('http://kso.lan/api', (new App())->baseURL);
    }

}
