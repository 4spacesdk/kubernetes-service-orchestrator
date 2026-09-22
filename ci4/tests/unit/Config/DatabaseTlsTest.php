<?php namespace App\Tests\Unit\Config;

use AuthExtension\OAuth2\ServerLib;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * TLS to kso's own database, from the certificates the chart mounts - for the application's
 * connections and for the OAuth storage's own, which used to connect without.
 */
class DatabaseTlsTest extends CIUnitTestCase {

    private const Settings = ['DB_SSL_CA', 'DB_SSL_CERT', 'DB_SSL_KEY', 'DB_SSL_VERIFY'];

    private array $asFound = [];

    protected function setUp(): void {
        parent::setUp();
        foreach (self::Settings as $name) {
            $this->asFound[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void {
        foreach ($this->asFound as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }
        parent::tearDown();
    }

    public function testNoTlsUnlessACertificateIsGiven(): void {
        $database = new Database();

        $this->assertFalse($database->default['encrypt']);
        $this->assertSame([], ServerLib::pdoOptions($database->default));
    }

    public function testTheCaIsCheckedAndTheHostNameToo(): void {
        putenv('DB_SSL_CA=/etc/kso/database-tls/ca.crt');

        $database = new Database();

        $expected = ['ssl_ca' => '/etc/kso/database-tls/ca.crt', 'ssl_verify' => true];
        $this->assertSame($expected, $database->default['encrypt']);
        $this->assertSame($expected, $database->sessions['encrypt'], 'the sessions connect the same way');
    }

    public function testTheHostNameCheckCanBeTurnedOff(): void {
        putenv('DB_SSL_CA=/etc/kso/database-tls/ca.crt');
        putenv('DB_SSL_VERIFY=false');

        $this->assertFalse((new Database())->default['encrypt']['ssl_verify']);
    }

    public function testAClientCertificateIsSentWhenGiven(): void {
        putenv('DB_SSL_CA=/etc/kso/database-tls/ca.crt');
        putenv('DB_SSL_CERT=/etc/kso/database-tls/tls.crt');
        putenv('DB_SSL_KEY=/etc/kso/database-tls/tls.key');

        $encrypt = (new Database())->default['encrypt'];

        $this->assertSame('/etc/kso/database-tls/tls.crt', $encrypt['ssl_cert']);
        $this->assertSame('/etc/kso/database-tls/tls.key', $encrypt['ssl_key']);
    }

    /**
     * The OAuth storage builds its own PDO connection from the group. Before CI4AuthExtension
     * v1.3.1 it left `encrypt` out, and tokens went to the database in the clear.
     */
    public function testTheOAuthStorageConnectsTheSameWay(): void {
        putenv('DB_SSL_CA=/etc/kso/database-tls/ca.crt');

        $options = ServerLib::pdoOptions((new Database())->default);

        $this->assertSame('/etc/kso/database-tls/ca.crt', $options[\Pdo\Mysql::ATTR_SSL_CA]);
        $this->assertTrue($options[\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT]);
    }

}
