<?php namespace App\Libraries\Kubernetes;

use App\Entities\Deployment;
use App\Libraries\Audit\Audit;
use App\Libraries\Crypt;

/**
 * Secrets kso makes itself, written into environment variables as placeholders:
 *
 * * `${secret.<name>}` belongs to the deployment. Every container of it - the app, its sidecars
 *   and init containers, the migration job, the cron jobs and the jobs `RunJobHelper` starts -
 *   gets the same value for the same name: a token key the app signs with and its push server
 *   checks.
 * * `${workspace.secret.<name>}` belongs to the workspace, and is shared by its deployments.
 * * `.previous` after either name is the value it had before the current one, and '' when it had
 *   none - for an app that reads with the old key while it encrypts again with the new one, as
 *   CI4's `encryption.previousKeys` does. It is never made by asking.
 *
 * What it is made of is written after the name, as a Helm chart writes it - `${secret.token |
 * randAlphaNum 32}`, see `SecretRecipe` - and is 64 hex characters without. A value is made the
 * first time it is looked up and kept, encrypted, with its recipe. Looking up is making: a preview
 * that asks makes and keeps the value the deploy will use. The table has one row per owner and
 * name, and two deploys that ask at once get that one row.
 *
 * **One name, one recipe.** A placeholder written with another recipe than the value was made
 * with is refused at deploy, rather than given a value of the wrong kind or two values for one
 * name. Rotating is how a recipe changes: the next deploy makes a new value as the placeholders
 * are written then.
 *
 * **Nothing is forgotten.** A key data was encrypted with is gone with its value, so every value
 * a secret no longer has - rotated, restored over or set over by hand - is kept as a version in
 * `*_secret_versions`, and can be shown and made current again. A deleted owner's secrets are
 * marked deleted, with who the owner was, until somebody purges them.
 *
 * A variable that uses one is secret whether it is marked or not - see `ContainerEnvironment` -
 * so the value reaches the pod through the workload's Secret, and a new value changes the
 * Secret's checksum on the pod template, which rolls the pods. Only environment variables are
 * filled in: a command or an argument is written into the manifest, where a secret must not be.
 */
class GeneratedSecrets {

    public const string Deployment = 'deployment';
    public const string Workspace = 'workspace';

    /** Owner => the current values, the owner's column, the values it no longer has. */
    private const array Tables = [
        self::Deployment => ['deployment_secrets', 'deployment_id', 'deployment_secret_versions'],
        self::Workspace => ['workspace_secrets', 'workspace_id', 'workspace_secret_versions'],
    ];

    /** Where the current value came from, when not from a recipe. */
    private const string SetByHand = 'set by hand';

    private const string Previous = '.previous';

    /**
     * Anything that starts like one, valid or not: [1] `workspace.` for a workspace's, [2] the
     * name - `.previous` included - [3] the recipe after a `|`, [4] the closing brace when there
     * is one.
     */
    private const string Placeholder = '/\$\{(workspace\.)?secret\.([^}|\s]*)\s*(?:\|([^}]*))?(\}?)/';

    /**
     * Whether a value, as written, takes a generated secret.
     */
    public static function Uses(string $written): bool {
        return str_contains($written, '${secret.') || str_contains($written, '${workspace.secret.');
    }

    /**
     * Why the variables cannot be saved, or null: a placeholder whose name is not `[a-z0-9_]+`, or
     * whose recipe kso does not know. Checked when they are saved, so a deploy never meets one.
     *
     * @param iterable<object|array> $variables as a request sends them, with a `value`
     */
    public static function ReasonVariablesAreInvalid(iterable $variables): ?string {
        foreach ($variables as $variable) {
            $value = (string) (((array) $variable)['value'] ?? '');
            preg_match_all(self::Placeholder, $value, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
            foreach ($matches as $match) {
                $reason = self::ReasonPlaceholderIsInvalid($match);
                if ($reason !== null) {
                    return $reason;
                }
            }
        }
        return null;
    }

    /**
     * The value as written, with its generated secrets filled in - made the first time.
     *
     * @throws \RuntimeException for one that cannot be filled in: a workspace's on a deployment in
     *                           no workspace, or one written with another recipe than it was made with
     */
    public static function Fill(string $written, Deployment $deployment): string {
        if (!self::Uses($written)) {
            return $written;
        }

        return preg_replace_callback(self::Placeholder, function (array $match) use ($deployment): string {
            $reason = self::ReasonPlaceholderIsInvalid($match);
            if ($reason !== null) {
                throw new \RuntimeException($reason);
            }
            [$name, $previous] = self::NameOf((string) $match[2]);
            if ($match[1] === 'workspace.') {
                if (!$deployment->workspace_id) {
                    throw new \RuntimeException("{$match[0]} belongs to a workspace, and {$deployment->name} is in none");
                }
                [$owner, $ownerId] = [self::Workspace, (int) $deployment->workspace_id];
            } else {
                [$owner, $ownerId] = [self::Deployment, (int) $deployment->id];
            }
            if ($previous) {
                return self::PreviousValue($owner, $ownerId, $name);
            }
            return self::Value($owner, $ownerId, $name, SecretRecipe::Parse((string) ($match[3] ?? '')));
        }, $written, -1, $count, PREG_UNMATCHED_AS_NULL);
    }

    /**
     * The owner's value for the name - made, and kept with its recipe, the first time it is asked
     * for, and after a rotation.
     *
     * @throws \RuntimeException when it was made with another recipe
     */
    public static function Value(string $owner, int $ownerId, string $name, ?SecretRecipe $recipe = null): string {
        [$table, $column] = self::Tables[$owner];
        $recipe ??= SecretRecipe::Default();
        $db = db_connect();
        $now = date('Y-m-d H:i:s');

        // The unique key on owner and name settles a race: the second insert changes nothing,
        // and both read the one row that is there.
        $db->query(
            "INSERT INTO `{$table}` (`{$column}`, `name`, `value`, `recipe`, `made`, `created`, `updated`) VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `id` = `id`",
            [$ownerId, $name, Crypt::Encrypt($recipe->make()), (string) $recipe, $now, $now, $now]
        );
        // Rotated: made anew, by the first to ask - the condition keeps a second from doing it again.
        // The version was counted up when it was rotated.
        $db->query(
            "UPDATE `{$table}` SET `value` = ?, `recipe` = ?, `origin` = 'made', `made` = ?, `updated` = ? WHERE `{$column}` = ? AND `name` = ? AND `value` IS NULL",
            [Crypt::Encrypt($recipe->make()), (string) $recipe, $now, $now, $ownerId, $name]
        );
        $row = $db->table($table)->where($column, $ownerId)->where('name', $name)->get()->getRowArray();

        if ((string) $row['recipe'] !== (string) $recipe) {
            $placeholder = ($owner === self::Workspace ? '${workspace.secret.' : '${secret.') . "{$name} | {$recipe}}";
            throw new \RuntimeException("{$placeholder} is written with another recipe than kso made it with ({$row['recipe']}) - write it the same way everywhere, or rotate it under Secrets to make it anew as written");
        }

        return (string) Crypt::Decrypt($row['value']);
    }

    /**
     * The value it had before the current one, or '' - looked up, never made.
     */
    public static function PreviousValue(string $owner, int $ownerId, string $name): string {
        [, $column, $versions] = self::Tables[$owner];
        $row = db_connect()->table($versions)
            ->where($column, $ownerId)
            ->where('name', $name)
            ->orderBy('version', 'desc')
            ->limit(1)
            ->get()->getRowArray();

        return $row === null ? '' : (string) Crypt::Decrypt($row['value']);
    }

    /**
     * The owner's secrets as a list may show them: name, recipe, made and rotated, and the
     * versions it no longer has - never a value. One rotated and not made anew yet is `pending`.
     *
     * @return list<array<string, mixed>>
     */
    public static function Of(string $owner, int $ownerId): array {
        [$table, $column] = self::Tables[$owner];
        $rows = db_connect()->table($table)
            ->where($column, $ownerId)
            ->where('deleted', null)
            ->orderBy('name', 'asc')
            ->get()->getResultArray();

        return self::Listed($owner, $ownerId, $rows);
    }

    /**
     * The secrets of the owners that were deleted, by owner - the newest deleted first.
     *
     * @return list<array<string, mixed>>
     */
    public static function Deleted(): array {
        $owners = [];
        foreach (self::Tables as $owner => [$table, $column]) {
            $rows = db_connect()->table($table)
                ->where('deleted IS NOT NULL', null, false)
                ->orderBy('name', 'asc')
                ->get()->getResultArray();
            $byOwner = [];
            foreach ($rows as $row) {
                $byOwner[(int) $row[$column]][] = $row;
            }
            foreach ($byOwner as $ownerId => $ownerRows) {
                $owners[] = [
                    'owner' => $owner,
                    'owner_id' => $ownerId,
                    'owner_name' => (string) $ownerRows[0]['owner_name'],
                    'owner_namespace' => (string) $ownerRows[0]['owner_namespace'],
                    'deleted' => self::Time($ownerRows[0]['deleted']),
                    'secrets' => self::Listed($owner, $ownerId, $ownerRows),
                ];
            }
        }
        usort($owners, fn(array $a, array $b) => strcmp((string) $b['deleted'], (string) $a['deleted']));

        return $owners;
    }

    /**
     * Whether the owner was deleted and kso still has its secrets.
     */
    public static function IsDeleted(string $owner, int $ownerId): bool {
        [$table, $column] = self::Tables[$owner];
        return db_connect()->table($table)->where($column, $ownerId)->where('deleted IS NOT NULL', null, false)->countAllResults() > 0;
    }

    /**
     * The next deploy makes a new value, as the placeholders are written then - which is also how
     * a recipe is changed. The value it had is kept as a version, and is `.previous` from now on.
     *
     * @return bool whether there was one by that name
     */
    public static function Rotate(string $owner, int $ownerId, string $name): bool {
        return Audit::Atomically(function () use ($owner, $ownerId, $name): bool {
            $row = self::Current($owner, $ownerId, $name);
            if ($row === null) {
                return false;
            }
            $now = date('Y-m-d H:i:s');
            // Rotated twice before a deploy, there is no value to keep, and no new version to count.
            $version = $row['value'] === null ? (int) $row['version'] : self::Retire($owner, $row, $now);
            [$table] = self::Tables[$owner];
            db_connect()->table($table)->where('id', $row['id'])->update([
                'value' => null,
                'recipe' => null,
                'origin' => 'made',
                'made' => null,
                'version' => $version,
                'rotated' => $now,
                'updated' => $now,
            ]);
            return true;
        });
    }

    /**
     * An earlier value made the current one again - as a new version, so the one it replaces is
     * kept too. With its recipe: placeholders written another way are refused at deploy, as ever.
     *
     * @throws \InvalidArgumentException when there is no secret, or no version, by that
     */
    public static function Restore(string $owner, int $ownerId, string $name, int $version): void {
        Audit::Atomically(function () use ($owner, $ownerId, $name, $version): void {
            $row = self::Current($owner, $ownerId, $name) ?? throw new \InvalidArgumentException('unknown secret');
            [, $column, $versions] = self::Tables[$owner];
            $kept = db_connect()->table($versions)->where($column, $ownerId)->where('name', $name)->where('version', $version)->get()->getRowArray()
                ?? throw new \InvalidArgumentException("{$name} has no version {$version} to restore");
            self::Replace($owner, $row, (string) $kept['value'], $kept['recipe'], "restored from version {$version}");
        });
    }

    /**
     * A value given by a person - one kept elsewhere, put back - made the current one, as a new
     * version. It keeps the recipe the secret was made with.
     *
     * @throws \InvalidArgumentException when there is no secret by that name, or no value
     */
    public static function Set(string $owner, int $ownerId, string $name, string $value): void {
        if ($value === '') {
            throw new \InvalidArgumentException('a value is needed');
        }
        Audit::Atomically(function () use ($owner, $ownerId, $name, $value): void {
            $row = self::Current($owner, $ownerId, $name) ?? throw new \InvalidArgumentException('unknown secret');
            // Rotated, it has no recipe until the next deploy; the one it was made with before then.
            [, $column, $versions] = self::Tables[$owner];
            $recipe = $row['recipe'] ?? db_connect()->table($versions)->where($column, $ownerId)->where('name', $name)
                ->orderBy('version', 'desc')->limit(1)->get()->getRowArray()['recipe'] ?? (string) SecretRecipe::Default();
            self::Replace($owner, $row, (string) Crypt::Encrypt($value), $recipe, self::SetByHand);
        });
    }

    /**
     * The value of one the owner has - the current one, or the version asked for - '' for one
     * rotated and not made anew yet, or null for none: without making it, asked for by a person,
     * not a deploy.
     */
    public static function Reveal(string $owner, int $ownerId, string $name, ?int $version = null): ?string {
        [$table, $column, $versions] = self::Tables[$owner];
        $row = db_connect()->table($table)->where($column, $ownerId)->where('name', $name)->get()->getRowArray();
        if ($row === null) {
            return null;
        }
        if ($version === null || $version === (int) $row['version']) {
            return (string) Crypt::Decrypt($row['value']);
        }
        $kept = db_connect()->table($versions)->where($column, $ownerId)->where('name', $name)->where('version', $version)->get()->getRowArray();

        return $kept === null ? null : (string) Crypt::Decrypt($kept['value']);
    }

    /**
     * Its owner is deleted, and they are kept: marked with when, and with who the owner was, as
     * the owner's row is gone.
     */
    public static function KeepAfterDeleting(string $owner, int $ownerId, string $ownerName, ?string $ownerNamespace): void {
        [$table, $column] = self::Tables[$owner];
        $now = date('Y-m-d H:i:s');
        db_connect()->table($table)->where($column, $ownerId)->where('deleted', null)->update([
            'deleted' => $now,
            'owner_name' => $ownerName,
            'owner_namespace' => $ownerNamespace,
            'updated' => $now,
        ]);
    }

    /**
     * A deleted owner's secrets, and every version of them, gone for good.
     *
     * @return bool whether it was a deleted owner kso still had secrets of
     */
    public static function Purge(string $owner, int $ownerId): bool {
        if (!self::IsDeleted($owner, $ownerId)) {
            return false;
        }
        [$table, $column, $versions] = self::Tables[$owner];
        Audit::Atomically(function () use ($table, $column, $versions, $ownerId): void {
            db_connect()->table($versions)->where($column, $ownerId)->delete();
            db_connect()->table($table)->where($column, $ownerId)->delete();
        });

        return true;
    }

    // <editor-fold desc="Helpers">

    /**
     * The secret's row, held until the change is written: a deploy that makes it anew at the same
     * time waits, rather than one of the two values being lost.
     */
    private static function Current(string $owner, int $ownerId, string $name): ?array {
        [$table, $column] = self::Tables[$owner];
        return db_connect()->query(
            "SELECT * FROM `{$table}` WHERE `{$column}` = ? AND `name` = ? AND `deleted` IS NULL FOR UPDATE",
            [$ownerId, $name]
        )->getRowArray();
    }

    /**
     * The value made current, the one it replaces kept - when there is one: a rotated secret has
     * none, and the value takes the version that was counted for it.
     */
    private static function Replace(string $owner, array $row, string $storedValue, ?string $recipe, string $origin): void {
        $now = date('Y-m-d H:i:s');
        $version = $row['value'] === null ? (int) $row['version'] : self::Retire($owner, $row, $now);
        [$table] = self::Tables[$owner];
        db_connect()->table($table)->where('id', $row['id'])->update([
            'value' => $storedValue,
            'recipe' => $recipe,
            'origin' => $origin,
            'made' => $now,
            'version' => $version,
            'updated' => $now,
        ]);
    }

    /**
     * The current value kept as a version, as it was stored.
     *
     * @return int the version the next value is
     */
    private static function Retire(string $owner, array $row, string $now): int {
        [, $column, $versions] = self::Tables[$owner];
        db_connect()->table($versions)->insert([
            $column => $row[$column],
            'name' => $row['name'],
            'version' => $row['version'],
            'value' => $row['value'],
            'recipe' => $row['recipe'],
            'origin' => $row['origin'],
            'made' => $row['made'],
            'retired' => $now,
            'created' => $now,
            'updated' => $now,
        ]);

        return (int) $row['version'] + 1;
    }

    /**
     * @param list<array<string, mixed>> $rows the owner's current rows
     * @return list<array<string, mixed>>
     */
    private static function Listed(string $owner, int $ownerId, array $rows): array {
        [, $column, $versions] = self::Tables[$owner];
        $kept = [];
        if ($rows !== []) {
            $all = db_connect()->table($versions)
                ->select('name, version, recipe, origin, made, retired')
                ->where($column, $ownerId)
                ->orderBy('version', 'desc')
                ->get()->getResultArray();
            foreach ($all as $version) {
                $kept[$version['name']][] = [
                    'version' => (int) $version['version'],
                    'recipe' => (string) $version['recipe'],
                    'origin' => (string) $version['origin'],
                    'made' => self::Time($version['made']),
                    'retired' => self::Time($version['retired']),
                ];
            }
        }

        return array_map(fn(array $row) => [
            'name' => (string) $row['name'],
            'recipe' => (string) $row['recipe'],
            'created' => self::Time($row['created']),
            'rotated' => self::Time($row['rotated']),
            'pending' => $row['value'] === null,
            'version' => (int) $row['version'],
            'origin' => (string) $row['origin'],
            'made' => self::Time($row['made']),
            'versions' => $kept[$row['name']] ?? [],
        ], $rows);
    }

    /**
     * The name, and whether it asks for the value before the current one.
     *
     * @return array{0: string, 1: bool}
     */
    private static function NameOf(string $written): array {
        return str_ends_with($written, self::Previous)
            ? [substr($written, 0, -strlen(self::Previous)), true]
            : [$written, false];
    }

    /**
     * @param array<int, ?string> $match of Placeholder
     */
    private static function ReasonPlaceholderIsInvalid(array $match): ?string {
        [$placeholder, , $name, $recipe, $closed] = $match + [null, null, null, null, null];
        if ($closed !== '}' || !preg_match('/^[a-z0-9_]+(\.previous)?$/', (string) $name)) {
            return "{$placeholder} is not a secret kso can make - the name is a-z, 0-9 and _, as in \${secret.api_key}, with .previous after it for the value before the current one";
        }
        if (self::NameOf((string) $name)[1]) {
            return trim((string) $recipe) === '' ? null : "{$placeholder}: the previous value takes no recipe - it is what it was made as";
        }
        $parsed = SecretRecipe::Parse((string) $recipe);

        return is_string($parsed) ? "{$placeholder}: {$parsed}" : null;
    }

    private static function Time(?string $time): ?string {
        return $time === null ? null : date('c', strtotime($time));
    }

    // </editor-fold>

}
