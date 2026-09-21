<?php namespace App\Entities\Concerns;

/**
 * An environment variable that can be marked secret - on a specification, a workspace
 * template, a deployment or an init container.
 *
 * * **Every value is encrypted where it is stored**, marked or not: see
 *   AddSecretEnvironmentVariables.
 * * **A secret one is write-only.** Its value is left out of `toArray()` - every response and
 *   every push - and `has_value` goes out in its place.
 * * **A secret that comes back empty keeps what is stored.** The variables are saved by
 *   replacing them all, and a form cannot send back a value it was never shown; see
 *   Replacements(). It also stays secret: unmarking one takes a new value, so marking and
 *   unmarking cannot be used to read one back.
 */
trait SecretEnvironmentVariable {

    public const array EncryptedFields = ['value'];

    use EncryptsFields;

    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false, ?array $fieldsFilter = null): array {
        $item = parent::toArray($onlyChanged, $cast, $recursive, $fieldsFilter);

        if ($this->is_secret) {
            unset($item['value']);
        }
        $item['has_value'] = strlen((string) $this->value) > 0;

        return $item;
    }

    /**
     * What to save when a request replaces all of a parent's variables: each as name, value
     * and whether it is secret.
     *
     * @param object[] $requested each with `name`, `value` and `is_secret`
     * @param iterable<self> $stored the variables the parent has now
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    public static function Replacements(array $requested, iterable $stored): array {
        $storedByName = [];
        foreach ($stored as $variable) {
            $storedByName[$variable->name] = $variable;
        }

        return array_map(function (object $data) use ($storedByName): array {
            $name = (string) ($data->name ?? '');
            $value = (string) ($data->value ?? '');
            $previous = $storedByName[$name] ?? null;

            if ($value === '' && $previous !== null && $previous->is_secret) {
                return [$name, (string) $previous->value, true];
            }

            return [$name, $value, (bool) ($data->is_secret ?? false)];
        }, $requested);
    }

}
