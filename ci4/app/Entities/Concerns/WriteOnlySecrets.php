<?php namespace App\Entities\Concerns;

/**
 * Fields that can be written and never read back.
 *
 * Being signed in is not the same as being entitled to a stored credential. Nothing in the
 * REST stack decides which columns may leave a resource - `allToArray()` sends the row - so
 * every password, token and client secret in the setup tables was handed in cleartext to any
 * authenticated caller, on every list read, and to anything that pulled the resource in as a
 * relation without asking for it.
 *
 * Three things together make a field write-only, and a form still savable:
 *
 * * `$hiddenFields` keeps it out of `toArray()`, which is every response and every push.
 * * `has_<field>` goes out in its place, so the form can say whether one is stored.
 * * `keepStoredSecrets()` drops an empty one from a write, so saving a form that could not
 *   show the value does not erase it.
 *
 * The class names the fields in a `SecretFields` constant and lists them in `$hiddenFields`
 * itself: a trait cannot redeclare a property the parent entity already has.
 *
 * `ContainerRegistry` and `GithubIntegration` came first and keep their own copies, because
 * each hides one more field than it flags - a registry's webhook secret and an integration's
 * setup state are withheld without a `has_` of their own.
 */
trait WriteOnlySecrets {

    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false, ?array $fieldsFilter = null): array {
        $item = parent::toArray($onlyChanged, $cast, $recursive, $fieldsFilter);

        foreach (static::SecretFields as $field) {
            $item["has_{$field}"] = strlen((string) $this->{$field}) > 0;
        }

        return $item;
    }

    /**
     * The write, with any secret that came in empty taken out of it.
     *
     * A dialog cannot show what it is not sent, so it sends the field back empty. Writing
     * that would clear the credential on every save of an unrelated field.
     *
     * Clearing one is therefore not something a write can do. Nothing asks to - a service
     * without a password is a service that cannot connect - and the field is set by
     * sending a new value.
     *
     * @param array $data
     * @return array
     */
    protected static function keepStoredSecrets($data) {
        foreach (static::SecretFields as $field) {
            if (is_array($data) && array_key_exists($field, $data) && !strlen((string) $data[$field])) {
                unset($data[$field]);
            }
        }

        return $data;
    }

}
