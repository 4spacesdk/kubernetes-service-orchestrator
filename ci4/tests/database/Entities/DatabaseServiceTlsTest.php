<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\DatabaseService;
use App\Fixtures;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * TLS to a database service, against the database this test run uses - a MySQL that offers TLS
 * with a certificate of its own making.
 *
 * MySQLi reads the certificates from files, so they are written for the connection and removed
 * again, whether it succeeds or not.
 */
class DatabaseServiceTlsTest extends DatabaseTestCase {

    public function testWithoutTlsTheConnectionIsInTheClear(): void {
        $this->assertSame('', $this->cipherOf($this->theTestDatabase()));
    }

    /**
     * Encrypted, but nothing says who answered - what a service without a CA gets.
     */
    public function testTlsWithoutACaIsEncrypted(): void {
        $this->assertNotSame('', $this->cipherOf($this->theTestDatabase(['tls' => true])));
    }

    /**
     * With a CA, the server has to have a certificate it signed. This one signed nothing.
     */
    public function testAServerTheCaDidNotSignIsRefused(): void {
        $service = $this->theTestDatabase(['tls' => true, 'tls_ca' => self::ACertificate()]);

        $this->assertNotNull($service->connectionProblem());
    }

    /**
     * Neither MySQLi nor PDO can check the CA without the host name, so turning the check off
     * turns off both - and the CA is not looked at.
     */
    public function testWithoutTheCheckTheCaIsNotLookedAt(): void {
        $service = $this->theTestDatabase(['tls' => true, 'tls_ca' => self::ACertificate(), 'tls_verify' => false]);

        $this->assertNotSame('', $this->cipherOf($service));
    }

    public function testTheCertificatesAreNotLeftOnDisk(): void {
        $before = $this->temporaryFiles();

        $this->theTestDatabase([
            'tls' => true,
            'tls_ca' => self::ACertificate(),
            'tls_client_cert' => self::ACertificate(),
            'tls_client_key' => 'not a key',
        ])->connectionProblem();

        $this->assertSame($before, $this->temporaryFiles());
    }

    /**
     * No CA per connection: `Encrypt`, checked against the image's trust store. The driver is
     * only in the amd64 image.
     */
    #[RequiresPhpExtension('sqlsrv')]
    public function testMssqlIsAskedToEncrypt(): void {
        $service = Fixtures::databaseService(['driver' => \DatabaseDrivers::MSSQL, 'tls' => true, 'tls_ca' => self::ACertificate()]);

        $this->assertTrue($service->prepareConnection()->encrypt);
    }

    public function testTheClientKeyIsWriteOnly(): void {
        $service = Fixtures::databaseService(['tls_client_key' => 'the key']);

        $item = $service->toArray();

        $this->assertArrayNotHasKey('tls_client_key', $item);
        $this->assertTrue($item['has_tls_client_key']);
    }

    private function theTestDatabase(array $overrides = []): DatabaseService {
        $database = config('Database')->tests;

        return Fixtures::databaseService(array_merge([
            'host' => $database['hostname'],
            'port' => $database['port'],
            'user' => $database['username'],
            'pass' => $database['password'],
        ], $overrides));
    }

    private function cipherOf(DatabaseService $service): string {
        return (string) $service->prepareConnection()->query("SHOW STATUS LIKE 'Ssl_cipher'")->getRow()->Value;
    }

    /**
     * @return list<string>
     */
    private function temporaryFiles(): array {
        return glob(sys_get_temp_dir() . '/kso-db-tls-*') ?: [];
    }

    private static function ACertificate(): string {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $certificate = openssl_csr_sign(openssl_csr_new(['commonName' => 'Nobody\'s CA'], $key), null, $key, 1);
        openssl_x509_export($certificate, $pem);

        return $pem;
    }

}
