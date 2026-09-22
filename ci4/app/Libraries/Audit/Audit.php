<?php namespace App\Libraries\Audit;

use App\Entities\AuditEvent;
use App\Entities\ContainerImageScan;
use App\Entities\ContainerImageScanRecord;
use App\Entities\Deletion;
use App\Entities\MigrationJob;
use App\Entities\WebhookDelivery;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;
use OrmExtension\Extensions\Entity;

/**
 * The audit trail: one row in `audit_events` for everything that changes, with who did it
 * (`AuditContext`) and what changed.
 *
 * Writes to entities are recorded by the entities themselves - see `Audited` - in the same
 * transaction as the write, so a change without its row cannot happen. Actions that are not a
 * write - a deploy, a command run in a pod, a sign-in - are recorded with `Record()` where they
 * happen.
 *
 * All signed-in users are trusted (see the plan's note on authorisation), so the trail is not
 * there to keep anyone out. It answers "who did that, and what did it look like before".
 */
final class Audit {

    public const string Created = 'created';
    public const string Updated = 'updated';
    public const string Deleted = 'deleted';
    public const string RelationAdded = 'relation_added';
    public const string RelationRemoved = 'relation_removed';

    /**
     * Rows kso writes as it goes, which say what happened in their own table already: the
     * trail would be the same thing again, only more of it.
     */
    public const array NotAudited = [
        AuditEvent::class,
        ContainerImageScan::class,
        ContainerImageScanRecord::class,
        Deletion::class,
        MigrationJob::class,
        WebhookDelivery::class,
    ];

    /** Bookkeeping every table has, which is not a change anyone made. */
    private const array Bookkeeping = ['id', 'created', 'updated', 'created_by_id', 'updated_by_id', 'deletion_id'];

    /** What a secret is shown as - whether it changed, never what to. */
    public const string Redacted = '[redacted]';

    /** Longer values are cut here: a manifest or a log does not need to be in the trail twice. */
    private const int LongestValue = 2000;

    public static function Covers(Entity $entity): bool {
        return !in_array($entity::class, self::NotAudited, true);
    }

    private static int $savepoints = 0;

    /**
     * Run a write and record it together - both or neither.
     *
     * Inside a transaction already, a savepoint: CodeIgniter only rolls back the outermost
     * transaction, so a nested one that failed would leave its write for whoever commits.
     *
     * @template T
     * @param callable(): T $write
     * @return T
     */
    public static function Atomically(callable $write): mixed {
        $db = Database::connect();

        if ($db->transDepth > 0) {
            $savepoint = 'audit_' . ++self::$savepoints;
            $db->query("SAVEPOINT {$savepoint}");
            try {
                $result = $write();
                $db->query("RELEASE SAVEPOINT {$savepoint}");
                return $result;
            } catch (\Throwable $e) {
                $db->query("ROLLBACK TO SAVEPOINT {$savepoint}");
                throw $e;
            }
        }

        $db->transBegin();
        try {
            $result = $write();
            $db->transCommit();
            return $result;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * An action that is not a write, or one kso wants named: `deployment.deploy`, `pod.exec`,
     * `user.sign_in`.
     *
     * @param Entity|array{type: string, id?: int, name?: string}|null $resource what it was done to:
     *   an entity, or something kso does not keep a row for - a pod
     * @param array<string, mixed> $details
     * @param int|null $userId who, when the request does not say - a sign-in, before it has one
     */
    public static function Record(string $action, Entity|array|null $resource = null, array $details = [], ?int $userId = null): void {
        $actor = AuditContext::Current();
        if ($userId !== null) {
            $actor['user_id'] = $userId;
        }
        if ($resource instanceof Entity) {
            $resource = [
                'type' => self::TypeOf($resource),
                'id' => $resource->id ? (int) $resource->id : null,
                'name' => self::NameOf($resource),
            ];
        }

        $inserted = Database::connect()->table('audit_events')->insert([
            'created' => date('Y-m-d H:i:s'),
            'user_id' => $actor['user_id'],
            'client_id' => $actor['client_id'],
            'ip_address' => $actor['ip_address'],
            'source' => $actor['source'],
            'action' => $action,
            'resource_type' => $resource['type'] ?? null,
            'resource_id' => $resource['id'] ?? null,
            'resource_name' => isset($resource['name']) ? mb_substr((string) $resource['name'], 0, 255) : null,
            'details' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        // Outside development a failed query is `false`, not an exception. The write it goes
        // with is rolled back by `Atomically()` on the way out.
        if ($inserted === false) {
            throw new DatabaseException('The audit trail could not be written');
        }
    }

    /**
     * The fields as stored - encrypted ones still encrypted - so they can be compared.
     *
     * @return array<string, mixed>
     */
    public static function StoredFields(Entity $entity, bool $original): array {
        $fields = array_flip($entity->_getModel()->getTableFields());
        $values = $original ? $entity->getOriginal() : $entity->toRawArray();

        return array_intersect_key(is_array($values) ? $values : [], $fields);
    }

    /**
     * Field => [before, after], for the fields that are different. Secrets say that they
     * changed, not what to; bookkeeping and the entity's `AuditIgnoredFields` are left out.
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function Changes(Entity $entity, array $before, array $after): array {
        $ignored = [...self::Bookkeeping, ...self::ConstantOf($entity, 'AuditIgnoredFields')];
        $secret = [
            ...self::ConstantOf($entity, 'SecretFields'),
            ...self::ConstantOf($entity, 'EncryptedFields'),
            ...(is_array($entity->hiddenFields ?? null) ? $entity->hiddenFields : []),
        ];

        $changes = [];
        foreach (array_keys($before + $after) as $field) {
            if (in_array($field, $ignored, true)) {
                continue;
            }
            $old = self::Comparable($before[$field] ?? null);
            $new = self::Comparable($after[$field] ?? null);
            if ($old === $new) {
                continue;
            }
            $changes[$field] = in_array($field, $secret, true)
                ? [self::RedactedOrNull($old), self::RedactedOrNull($new)]
                : [self::Shortened($old), self::Shortened($new)];
        }

        return $changes;
    }

    private static function Comparable(mixed $value): ?string {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => json_encode($value),
        };
    }

    private static function RedactedOrNull(?string $value): ?string {
        return $value === null || $value === '' ? $value : self::Redacted;
    }

    private static function Shortened(?string $value): ?string {
        if ($value === null || mb_strlen($value) <= self::LongestValue) {
            return $value;
        }
        return mb_substr($value, 0, self::LongestValue) . '… (' . mb_strlen($value) . ' characters)';
    }

    /**
     * @return list<string>
     */
    private static function ConstantOf(Entity $entity, string $name): array {
        $constant = $entity::class . '::' . $name;
        return defined($constant) ? array_values(constant($constant)) : [];
    }

    public static function TypeOf(Entity $entity): string {
        return (new \ReflectionClass($entity))->getShortName();
    }

    private static function NameOf(Entity $entity): ?string {
        foreach (['name_readable', 'name', 'username'] as $field) {
            $value = $entity->toRawArray()[$field] ?? null;
            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, 255);
            }
        }
        return null;
    }

}
