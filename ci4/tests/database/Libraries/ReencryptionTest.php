<?php namespace App\Tests\Database\Libraries;

use App\DatabaseTestCase;
use App\Fixtures;
use App\Libraries\Crypt;
use App\Libraries\Reencryption;
use CodeIgniter\Config\Factories;
use Config\Encryption;

/**
 * A rotation of `ENCRYPTION_KEY` taken to the end: after the rewrite, the old key is not
 * needed for anything.
 */
class ReencryptionTest extends DatabaseTestCase {

    private const string OldKey = 'old-key-old-key-old-key-old-key!';
    private const string NewKey = 'new-key-new-key-new-key-new-key!';

    private string|false $key;
    private string|false $previousKeys;

    public function setUp(): void {
        parent::setUp();
        $this->key = getenv('ENCRYPTION_KEY');
        $this->previousKeys = getenv('ENCRYPTION_PREVIOUS_KEYS');
    }

    public function tearDown(): void {
        putenv($this->key === false ? 'ENCRYPTION_KEY' : "ENCRYPTION_KEY={$this->key}");
        putenv($this->previousKeys === false ? 'ENCRYPTION_PREVIOUS_KEYS' : "ENCRYPTION_PREVIOUS_KEYS={$this->previousKeys}");
        Factories::injectMock('config', Encryption::class, new Encryption());
        parent::tearDown();
    }

    public function testAfterTheRewriteTheOldKeyIsNotNeeded(): void {
        $this->useKeys(self::OldKey);
        $service = Fixtures::databaseService(['pass' => 'db-secret']);
        $variable = \App\Entities\EnvironmentVariable::Create('API_TOKEN', 'token-value', true);

        // Before: the new key alone cannot read it.
        $this->useKeys(self::NewKey);
        try {
            Crypt::Decrypt($this->stored('database_services', 'pass', $service->id));
            $this->fail('the new key read a value the old one wrote');
        } catch (\CodeIgniter\Encryption\Exceptions\EncryptionException) {
        }

        $this->useKeys(self::NewKey, self::OldKey);
        $result = (new Reencryption())->run();

        $this->useKeys(self::NewKey);
        $this->assertSame('db-secret', Crypt::Decrypt($this->stored('database_services', 'pass', $service->id)));
        $this->assertSame('token-value', Crypt::Decrypt($this->stored('environment_variables', 'value', $variable->id)));
        $this->assertGreaterThanOrEqual(2, $result['rewritten']);
        $this->assertSame([], $result['unreadable']);
    }

    /**
     * Written before its column was encrypted.
     */
    public function testAPlainValueIsEncrypted(): void {
        $this->useKeys(self::NewKey);
        $service = Fixtures::databaseService();
        $this->db->table('database_services')->where('id', $service->id)->update(['pass' => 'plain-password']);

        (new Reencryption())->run();

        $stored = $this->stored('database_services', 'pass', $service->id);
        $this->assertTrue(Crypt::IsEncrypted($stored));
        $this->assertSame('plain-password', Crypt::Decrypt($stored));
    }

    /**
     * A value no key reads is left, and named - the old key has to stay until it is sorted out.
     */
    public function testAValueNoKeyCanReadIsLeftAndReported(): void {
        $this->useKeys(self::NewKey);
        $service = Fixtures::databaseService();
        $unreadable = Crypt::Marker . base64_encode('not ciphertext at all, not by any key');
        $this->db->table('database_services')->where('id', $service->id)->update(['pass' => $unreadable]);

        $result = (new Reencryption())->run();

        $this->assertSame(["database_services.pass#{$service->id}"], $result['unreadable']);
        $this->assertSame($unreadable, $this->stored('database_services', 'pass', $service->id));
    }

    /**
     * From the entities, so one encrypted later is included - the environment variables are.
     */
    public function testTheColumnsAreEveryEntitysEncryptedFields(): void {
        $columns = Reencryption::Columns();

        $this->assertSame(['pass', 'tls_client_key'], $columns['database_services']);
        $this->assertSame(['database_pass'], $columns['deployments']);
        $this->assertSame(['mfa_secret_hash'], $columns['users']);
        foreach (['environment_variables', 'deployment_specification_environment_variables', 'workspace_template_environment_variables', 'init_container_environment_variables'] as $table) {
            $this->assertSame(['value'], $columns[$table], $table);
        }
        $this->assertCount(13, $columns);
    }

    // <editor-fold desc="Helpers">

    /**
     * The keys as a pod would have them. A fresh config: the cached one read the environment
     * once, and every encrypter is built from it.
     */
    private function useKeys(string $key, string $previous = ''): void {
        putenv("ENCRYPTION_KEY={$key}");
        putenv($previous === '' ? 'ENCRYPTION_PREVIOUS_KEYS' : "ENCRYPTION_PREVIOUS_KEYS={$previous}");
        Factories::injectMock('config', Encryption::class, new Encryption());
    }

    private function stored(string $table, string $column, int $id): string {
        return (string) $this->db->table($table)->where('id', $id)->get()->getRowArray()[$column];
    }

    // </editor-fold>

}
