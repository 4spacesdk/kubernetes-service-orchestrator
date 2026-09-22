<?php namespace App\Tests\Unit\Libraries;

use App\Libraries\OutboundUrl;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Which urls kso calls on an operator's say-so. Nothing here leaves the process: the refusals
 * happen before curl is asked, and the allowed ones are addresses, so there is nothing to look up.
 */
class OutboundUrlTest extends CIUnitTestCase {

    #[DataProvider('urlsThatAreRefused')]
    public function testAUrlThatReachesKsoItselfOrTheCloudIsRefused(string $url): void {
        $this->expectException(\InvalidArgumentException::class);

        OutboundUrl::Apply(curl_init(), $url);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function urlsThatAreRefused(): array {
        return [
            'a local file' => ['file:///proc/self/environ'],
            'another protocol' => ['gopher://example.org/'],
            'no host' => ['http:///hook'],
            'no url at all' => [''],
            'the metadata service' => ['http://169.254.169.254/latest/meta-data/'],
            'loopback' => ['http://127.0.0.1:9000/api/publish'],
            'loopback by name' => ['http://localhost/api'],
            'loopback in IPv6' => ['http://[::1]/'],
            'loopback written as IPv6' => ['http://[::ffff:127.0.0.1]/'],
            'the unspecified address' => ['http://0.0.0.0/'],
            'a name that does not resolve' => ['https://subscriber.invalid/hook'],
        ];
    }

    /**
     * Private networks are allowed: a webhook to a service in the same cluster is ordinary.
     */
    #[DataProvider('urlsThatAreCalled')]
    public function testAnOrdinaryOrPrivateAddressIsCalled(string $url): void {
        OutboundUrl::Apply(curl_init(), $url);

        $this->assertTrue(true);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function urlsThatAreCalled(): array {
        return [
            'a public address' => ['https://93.184.215.14/hook'],
            'a cluster address' => ['http://10.96.0.12:8080/hook'],
            'a private network' => ['http://192.168.1.20/hook'],
            'IPv6' => ['https://[2606:2800:21f:cb07:6820:80da:af6b:8b2c]/hook'],
        ];
    }

    #[DataProvider('addresses')]
    public function testTheRangesAreTheOnesMeant(string $address, bool $called): void {
        $this->assertSame($called, OutboundUrl::MayBeCalled($address));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function addresses(): array {
        return [
            '127.255.255.254' => ['127.255.255.254', false],
            '128.0.0.1' => ['128.0.0.1', true],
            '169.254.0.1' => ['169.254.0.1', false],
            '169.255.0.1' => ['169.255.0.1', true],
            '224.0.0.1 (multicast)' => ['224.0.0.1', false],
            '172.16.0.1' => ['172.16.0.1', true],
            'fe80::1' => ['fe80::1', false],
            'febf::1 (still link-local)' => ['febf::1', false],
            'fec0::1' => ['fec0::1', true],
            'fd00:ec2::254 (the metadata service)' => ['fd00:ec2::254', false],
            'not an address' => ['kso', false],
        ];
    }

}
