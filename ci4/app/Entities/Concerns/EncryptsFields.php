<?php namespace App\Entities\Concerns;

use App\Libraries\Crypt;

/**
 * Fields stored encrypted and read back in the clear.
 *
 * The class names them in an `EncryptedFields` constant. Everything else is the entity's
 * ordinary property access: a value written to one of them is encrypted on the way into
 * `$attributes`, so whatever saves the entity stores ciphertext without knowing anything
 * about it, and a value read off one is decrypted on the way out.
 *
 * **Property access, not a model event.** `afterFind` would look like the obvious place and
 * would be wrong: a relation is not built through it. `ResultBuilder::arrangeIncludedRelations()`
 * makes the related entity straight from the columns of the joined row, so a deployment's
 * database service - loaded because it is a relation, not because anyone asked - would have
 * come back as ciphertext. This is the same reason `WriteOnlySecrets` sits on the entity.
 *
 * A row written before the migration, or by a pod still running the previous image during a
 * rolling upgrade, carries no marker and is handed back as it is. `Crypt` is what decides
 * that; see the note there.
 */
trait EncryptsFields {

    public function __get(string $key) {
        $value = parent::__get($key);

        return in_array($key, static::EncryptedFields, true) && is_string($value)
            ? Crypt::Decrypt($value)
            : $value;
    }

    public function __set(string $key, $value = null) {
        if (in_array($key, static::EncryptedFields, true) && is_string($value)) {
            $value = Crypt::Encrypt($value);
        }

        return parent::__set($key, $value);
    }

}
