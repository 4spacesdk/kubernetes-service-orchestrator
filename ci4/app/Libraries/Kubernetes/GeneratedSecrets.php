<?php namespace App\Libraries\Kubernetes;

use App\Entities\Deployment;
use App\Libraries\Crypt;

/**
 * Secrets kso makes itself, written into environment variables as placeholders:
 *
 * * `${secret.<name>}` belongs to the deployment. Every container of it - the app, its sidecars
 *   and init containers, the migration job, the cron jobs and the jobs `RunJobHelper` starts -
 *   gets the same value for the same name: a token key the app signs with and its push server
 *   checks.
 * * `${workspace.secret.<name>}` belongs to the workspace, and is shared by its deployments.
 *
 * What it is made of is written after the name, as a Helm chart writes it - `${secret.token |
 * randAlphaNum 32}`, see `SecretRecipe` - and is 64 hex characters without. A value is made the
 * first time it is looked up and kept, encrypted, with its recipe. Looking up is making: a preview
 * that asks makes and keeps the value the deploy will use. The table has one row per owner and
 * name, and two deploys that ask at once get that one row.
 *
 * **One name, one recipe.** A placeholder written with another recipe than the value was made
 * with is refused at deploy, rather than given a value of the wrong kind or two values for one
 * name. Rotating is how a recipe changes: it forgets the value, and the next deploy makes a new
 * one as the placeholders are written then.
 *
 * A variable that uses one is secret whether it is marked or not - see `ContainerEnvironment` -
 * so the value reaches the pod through the workload's Secret, and a new value changes the
 * Secret's checksum on the pod template, which rolls the pods. Only environment variables are
 * filled in: a command or an argument is written into the manifest, where a secret must not be.
 */
class GeneratedSecrets {

    public const string Deployment = 'deployment';
    public const string Workspace = 'workspace';

    private const array Tables = [
        self::Deployment => ['deployment_secrets', 'deployment_id'],
        self::Workspace => ['workspace_secrets', 'workspace_id'],
    ];

    /**
     * Anything that starts like one, valid or not: [1] `workspace.` for a workspace's, [2] the
     * name, [3] the recipe after a `|`, [4] the closing brace when there is one.
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
            $recipe = SecretRecipe::Parse((string) ($match[3] ?? ''));
            if ($match[1] === 'workspace.') {
                if (!$deployment->workspace_id) {
                    throw new \RuntimeException("{$match[0]} belongs to a workspace, and {$deployment->name} is in none");
                }
                return self::Value(self::Workspace, (int) $deployment->workspace_id, $match[2], $recipe);
            }
            return self::Value(self::Deployment, (int) $deployment->id, $match[2], $recipe);
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
            "INSERT INTO `{$table}` (`{$column}`, `name`, `value`, `recipe`, `created`, `updated`) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `id` = `id`",
            [$ownerId, $name, Crypt::Encrypt($recipe->make()), (string) $recipe, $now, $now]
        );
        // Rotated: made anew, by the first to ask - the condition keeps a second from doing it again.
        $db->query(
            "UPDATE `{$table}` SET `value` = ?, `recipe` = ?, `updated` = ? WHERE `{$column}` = ? AND `name` = ? AND `value` IS NULL",
            [Crypt::Encrypt($recipe->make()), (string) $recipe, $now, $ownerId, $name]
        );
        $row = $db->table($table)->where($column, $ownerId)->where('name', $name)->get()->getRowArray();

        if ((string) $row['recipe'] !== (string) $recipe) {
            $placeholder = ($owner === self::Workspace ? '${workspace.secret.' : '${secret.') . "{$name} | {$recipe}}";
            throw new \RuntimeException("{$placeholder} is written with another recipe than kso made it with ({$row['recipe']}) - write it the same way everywhere, or rotate it under Secrets to make it anew as written");
        }

        return (string) Crypt::Decrypt($row['value']);
    }

    /**
     * The owner's secrets as a list may show them: name, recipe, made and rotated - never the
     * value. One rotated and not made anew yet is `pending`.
     *
     * @return list<array{name: string, recipe: string, created: ?string, rotated: ?string, pending: bool}>
     */
    public static function Of(string $owner, int $ownerId): array {
        [$table, $column] = self::Tables[$owner];
        $rows = db_connect()->table($table)
            ->select('name, recipe, created, rotated, value IS NULL AS pending', false)
            ->where($column, $ownerId)
            ->orderBy('name', 'asc')
            ->get()->getResultArray();

        return array_map(fn(array $row) => [
            'name' => (string) $row['name'],
            'recipe' => (string) $row['recipe'],
            'created' => self::Time($row['created']),
            'rotated' => self::Time($row['rotated']),
            'pending' => (bool) $row['pending'],
        ], $rows);
    }

    /**
     * Forget the value: the next deploy makes a new one, as the placeholders are written then -
     * which is also how a recipe is changed.
     *
     * @return bool whether there was one by that name
     */
    public static function Rotate(string $owner, int $ownerId, string $name): bool {
        [$table, $column] = self::Tables[$owner];
        $now = date('Y-m-d H:i:s');
        $db = db_connect();
        $db->table($table)
            ->where($column, $ownerId)
            ->where('name', $name)
            ->update(['value' => null, 'recipe' => null, 'rotated' => $now, 'updated' => $now]);

        return $db->affectedRows() > 0;
    }

    /**
     * The value of one the owner has, '' for one rotated and not made anew yet, or null for none -
     * without making it: asked for by a person, not a deploy.
     */
    public static function Reveal(string $owner, int $ownerId, string $name): ?string {
        [$table, $column] = self::Tables[$owner];
        $row = db_connect()->table($table)->where($column, $ownerId)->where('name', $name)->get()->getRowArray();

        return $row === null ? null : (string) Crypt::Decrypt($row['value']);
    }

    /**
     * Gone with their owner.
     */
    public static function DeleteAllOf(string $owner, int $ownerId): void {
        [$table, $column] = self::Tables[$owner];
        db_connect()->table($table)->where($column, $ownerId)->delete();
    }

    /**
     * @param array<int, ?string> $match of Placeholder
     */
    private static function ReasonPlaceholderIsInvalid(array $match): ?string {
        [$placeholder, , $name, $recipe, $closed] = $match + [null, null, null, null, null];
        if ($closed !== '}' || !preg_match('/^[a-z0-9_]+$/', (string) $name)) {
            return "{$placeholder} is not a secret kso can make - the name is a-z, 0-9 and _, as in \${secret.api_key}";
        }
        $parsed = SecretRecipe::Parse((string) $recipe);

        return is_string($parsed) ? "{$placeholder}: {$parsed}" : null;
    }

    private static function Time(?string $time): ?string {
        return $time === null ? null : date('c', strtotime($time));
    }

}
