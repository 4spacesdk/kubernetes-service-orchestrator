<?php namespace App\Tests\Unit\Config;

use App\Libraries\Crypt;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The key the stored credentials are encrypted with.
 *
 * It used to be written into `Config/Encryption.php` and committed, so every installation
 * shared one and anyone with the repository had it - which is also the key the OAuth agent's
 * refresh-token cookie is encrypted with, so a cookie could be both read and forged.
 *
 * It comes from `ENCRYPTION_KEY` now, and a missing one is a failure rather than a default.
 * That failure lands where it can be acted on: the chart refuses to render without the
 * value, and the migration job - which encrypts the existing rows - is the first thing that
 * asks for an encrypter.
 */
class EncryptionKeyTest extends CIUnitTestCase {

    public function testTheKeyIsNotWrittenIntoTheRepository(): void {
        $source = file_get_contents(APPPATH . 'Config/Encryption.php');

        $this->assertStringNotContainsString('ijL5a5RPmh8LbOhFgQyOgjPImnOoUHKS', $source);
        $this->assertMatchesRegularExpression('/public string \$key = \'\';/', $source);
    }

    /**
     * Nor anywhere else. `User::updateMFASecret()` had a second one of its own, written into
     * the method as a hex string, which is what the second factor was encrypted with.
     */
    public function testNoHardcodedKeyIsLeftInTheEntities(): void {
        $offenders = [];

        foreach (glob(APPPATH . 'Entities/*.php') as $file) {
            if (preg_match('/hex2bin\(\'[0-9a-f]{32,}\'\)/', (string) file_get_contents($file))) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders);
    }

    public function testAMissingKeyIsRefusedRatherThanDefaulted(): void {
        $this->withKey('', function (): void {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('ENCRYPTION_KEY is not set');

            new \Config\Encryption();
        });
    }

    /**
     * A round trip through the marker, which is what tells an encrypted value from one
     * written before there was any encryption.
     */
    public function testAValueComesBackThroughTheMarker(): void {
        $encrypted = Crypt::Encrypt('a-real-credential');

        $this->assertTrue(Crypt::IsEncrypted($encrypted));
        $this->assertStringNotContainsString('a-real-credential', (string) $encrypted);
        $this->assertSame('a-real-credential', Crypt::Decrypt($encrypted));
    }

    public function testNothingIsEncryptedTwice(): void {
        $once = Crypt::Encrypt('a-real-credential');

        $this->assertSame($once, Crypt::Encrypt($once));
    }

    /**
     * "No credential" is not something to encrypt: a row that never had one must not start
     * looking as though it does, or `has_<field>` says yes to every empty column.
     */
    public function testNothingIsMadeOutOfNothing(): void {
        $this->assertNull(Crypt::Encrypt(null));
        $this->assertSame('', Crypt::Encrypt(''));
        $this->assertNull(Crypt::Decrypt(null));
        $this->assertSame('', Crypt::Decrypt(''));
    }

    /**
     * @param callable(): void $body
     */
    private function withKey(string $value, callable $body): void {
        $original = getenv('ENCRYPTION_KEY');

        putenv('ENCRYPTION_KEY=' . $value);

        try {
            $body();
        } finally {
            if ($original === false) {
                putenv('ENCRYPTION_KEY');
            } else {
                putenv('ENCRYPTION_KEY=' . $original);
            }
        }
    }

}
