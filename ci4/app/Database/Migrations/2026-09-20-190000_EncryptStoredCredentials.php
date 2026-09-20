<?php namespace App\Database\Migrations;

use App\Libraries\Crypt;
use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * The credentials kso holds for other systems, encrypted where they are stored.
 *
 * Two things happen here, and the order matters. The columns are widened first: ciphertext
 * is longer than what it replaces - a key, an IV and base64 on top - and `database_pass` was
 * `varchar(63)`, so encrypting in place would have truncated it. Then every row is read,
 * encrypted and written back.
 *
 * Done row by row through the query builder rather than through the entities, because the
 * entities encrypt on write now: reading one would decrypt, and writing it would encrypt
 * what was already encrypted. The builder sees the column as it is.
 *
 * `users.mfa_secret_hash` is the odd one. It was already encrypted, with a passphrase
 * written into `User::updateMFASecret()` - the same on every installation, and in the
 * repository - so it is decrypted with that one and re-encrypted with the installation's
 * key. The old passphrase is repeated below because it has to be: the value cannot be read
 * without it, and it is in the git history either way.
 *
 * Running twice is harmless. `Crypt::Encrypt()` leaves a value that already carries the
 * marker alone.
 */
class EncryptStoredCredentials extends Migration {

    /**
     * Every column that holds a credential for another system, and its table.
     *
     * Not the `oauth_*` tables. Those are read by the vendored OAuth server through a PDO
     * connection of its own - `checkClientCredentials()` compares `client_secret` in SQL and
     * `getPrivateKey()` signs with what it reads - so encrypting them would break signing in
     * rather than protect anything.
     *
     * Not `users.password` either: that is a bcrypt hash and has to stay one.
     */
    private const array Columns = [
        'container_registries' => ['gcloud_credentials', 'azure_client_secret', 'harbor_password', 'pull_password', 'webhook_secret'],
        'database_services' => ['pass'],
        'deployments' => ['database_pass'],
        'email_services' => ['pass'],
        'github_integrations' => ['client_secret', 'private_key', 'webhook_secret'],
        'podio_integrations' => ['client_secret', 'app_token'],
        'webhooks' => ['auth_bearer_token'],
        'webhook_deliveries' => ['auth_bearer_token'],
        'users' => ['mfa_secret_hash'],
    ];

    public function up() {
        $db = Database::connect();

        foreach (self::Columns as $table => $columns) {
            $this->forge->modifyColumn($table, array_combine(
                $columns,
                array_map(static fn (string $column) => ['name' => $column, 'type' => 'TEXT', 'null' => true], $columns)
            ));
        }

        foreach (self::Columns as $table => $columns) {
            foreach ($db->table($table)->select(array_merge(['id'], $columns))->get()->getResultArray() as $row) {
                $update = [];

                foreach ($columns as $column) {
                    $value = (string) ($row[$column] ?? '');
                    if ($value === '' || Crypt::IsEncrypted($value)) {
                        continue;
                    }

                    if ($table === 'users' && $column === 'mfa_secret_hash') {
                        $value = self::readTheOldMfaSecret($value);
                        if ($value === null) {
                            continue;
                        }
                    }

                    $update[$column] = Crypt::Encrypt($value);
                }

                if ($update !== []) {
                    $db->table($table)->where('id', $row['id'])->update($update);
                }
            }
        }
    }

    /**
     * The value as `User::updateMFASecret()` used to write it, or null when it cannot be
     * read - in which case it is left alone rather than encrypted as the gibberish it would
     * otherwise become. The user sets up two-factor again; encrypting an unreadable value
     * would leave them locked out with nothing to point at.
     */
    private static function readTheOldMfaSecret(string $stored): ?string {
        $message = base64_decode($stored, true);
        if ($message === false) {
            return null;
        }

        $cipher = 'AES-256-CBC';
        $passPhrase = hex2bin('c17319112b52d6b472136d7938121233783d723814746a67c81a451c4d238d18');
        $nonceSize = openssl_cipher_iv_length($cipher);

        $plaintext = openssl_decrypt(
            mb_substr($message, $nonceSize, null, '8bit'),
            $cipher,
            $passPhrase,
            OPENSSL_RAW_DATA,
            mb_substr($message, 0, $nonceSize, '8bit')
        );

        return $plaintext === false || $plaintext === '' ? null : $plaintext;
    }

    public function down() {

    }

}
