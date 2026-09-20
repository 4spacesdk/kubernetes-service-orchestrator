<?php namespace App\Libraries;

use CodeIgniter\Config\Services;
use CodeIgniter\Encryption\Exceptions\EncryptionException;

/**
 * The stored credentials, encrypted with a key that is not in the repository.
 *
 * kso keeps other systems' credentials so it can use them: a registry's password, a
 * database server's, a customer's webhook token. They were columns of plain text, so a
 * backup, a replica or a read-only SQL account was the whole set. Encrypting them here does
 * not protect against a compromised pod - that one has the key in its environment - but it
 * does separate "can read the database" from "has every credential kso holds", and those
 * are usually different people.
 *
 * **Ciphertext says what it is.** A value starts with `enc:v1:` and is base64 after that.
 * Without a marker there is no way to tell an encrypted value from a password that happens
 * to look like one, and a half-migrated table - a row written by an older pod during a
 * rolling upgrade, say - would be indistinguishable from a decryption that silently
 * returned rubbish. A value without the marker is handed back as it is, which is what makes
 * the upgrade survivable; the migration rewrites every row it finds.
 *
 * The key comes from `ENCRYPTION_KEY`. Rotating it means putting the old one in
 * `ENCRYPTION_PREVIOUS_KEYS` - CodeIgniter tries the current key first and falls back - and
 * re-saving the rows.
 */
class Crypt {

    public const string Marker = 'enc:v1:';

    /**
     * Null and the empty string stay as they are: "no credential" is not something to
     * encrypt, and a row that never had one should not start looking as though it does.
     */
    public static function Encrypt(?string $value): ?string {
        if ($value === null || $value === '') {
            return $value;
        }

        if (self::IsEncrypted($value)) {
            return $value;
        }

        return self::Marker . base64_encode(Services::encrypter()->encrypt($value));
    }

    /**
     * @throws EncryptionException when the value carries the marker but no key can read it
     */
    public static function Decrypt(?string $value): ?string {
        if ($value === null || !self::IsEncrypted($value)) {
            return $value;
        }

        $raw = base64_decode(substr($value, strlen(self::Marker)), true);
        if ($raw === false) {
            throw new EncryptionException('Encrypted value is not base64');
        }

        return Services::encrypter()->decrypt($raw);
    }

    public static function IsEncrypted(?string $value): bool {
        return is_string($value) && str_starts_with($value, self::Marker);
    }

}
