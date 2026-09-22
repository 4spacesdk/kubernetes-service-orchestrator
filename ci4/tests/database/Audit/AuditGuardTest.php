<?php namespace App\Tests\Database\Audit;

use App\DatabaseTestCase;
use App\Libraries\Audit\Audit;
use OrmExtension\Extensions\Entity;

/**
 * What keeps the audit trail whole, besides the entities recording themselves.
 *
 * * Writes that go around the entities - the query builder, raw SQL - are held to a list,
 *   with how many each file has. Each is bookkeeping nobody did; a new one fails here until
 *   somebody has decided it is not a change the trail should show.
 * * Nothing but the trail itself and its cleanup writes to `audit_events`.
 * * No entity's secret reaches it, whichever way the entity marks the field.
 */
class AuditGuardTest extends DatabaseTestCase {

    /**
     * File => how many statements in it write around the entities, and why that is fine.
     */
    private const array WritesAroundTheEntities = [
        'app/Libraries/Audit/Audit.php' => [1, 'the trail itself'],
        'app/Commands/CleanupAuditEvents.php' => [1, 'the trail kept to 90 days'],
        'app/Libraries/LoginThrottle.php' => [1, 'sign-in attempts, their own record'],
        'app/Commands/CleanupSignInAttempts.php' => [1, 'sign-in attempts kept to 90 days'],
        'app/Libraries/ImageScanning/ImageScanner.php' => [2, 'scan results, their own record'],
        'app/Libraries/Reencryption.php' => [1, 'the same values under a new key'],
        'app/Commands/RerunLastMigration.php' => [1, 'a development command'],
        'app/Entities/User.php' => [1, 'sessions ended with a changed password, which is recorded'],
        'app/Entities/System.php' => [1, "kso's own settings row, made on a fresh installation"],
    ];

    public function testWritesAroundTheEntitiesAreTheOnesOnTheList(): void {
        $expected = array_map(fn($entry) => $entry[0], self::WritesAroundTheEntities);
        ksort($expected);

        $this->assertSame($expected, $this->writesAroundTheEntities());
    }

    public function testOnlyTheTrailAndItsCleanupWriteToIt(): void {
        $writers = [];
        foreach ($this->statements() as $file => $statements) {
            foreach ($statements as $statement) {
                if (str_contains($statement, 'audit_events') && preg_match(self::WriteVerbs, $statement)) {
                    $writers[] = $file;
                }
            }
        }

        $writers = array_values(array_unique($writers));
        sort($writers);

        $this->assertSame(['app/Commands/CleanupAuditEvents.php', 'app/Libraries/Audit/Audit.php'], $writers);
    }

    /**
     * A secret is `[redacted]` in the trail whether the entity names it in `SecretFields`,
     * `EncryptedFields` or `$hiddenFields`.
     */
    public function testNoEntitysSecretReachesTheTrail(): void {
        $leaked = [];
        foreach ($this->entities() as $entity) {
            foreach ($this->secretFieldsOf($entity) as $field) {
                $changes = Audit::Changes($entity, [$field => 'the-old-secret'], [$field => 'the-new-secret']);
                if (($changes[$field] ?? null) !== [Audit::Redacted, Audit::Redacted]) {
                    $leaked[] = $entity::class . "::{$field}";
                }
            }
        }

        $this->assertSame([], $leaked);
    }

    // <editor-fold desc="Helpers">

    private const string WriteVerbs = '/->(insert|insertBatch|update|updateBatch|delete|replace|truncate|emptyTable)\s*\(/';
    private const string RawWrite = '/query\s*\(\s*["\'][^"\']*\b(INSERT|UPDATE|DELETE|REPLACE|TRUNCATE|DROP|ALTER)\b/i';

    /**
     * @return array<string, int>
     */
    private function writesAroundTheEntities(): array {
        $found = [];
        foreach ($this->statements() as $file => $statements) {
            $count = 0;
            foreach ($statements as $statement) {
                $builder = str_contains($statement, '->table(') || preg_match('/\$builder\s*->/', $statement);
                if (($builder && preg_match(self::WriteVerbs, $statement)) || preg_match(self::RawWrite, $statement)) {
                    $count++;
                }
            }
            if ($count > 0) {
                $found[$file] = $count;
            }
        }
        ksort($found);
        return $found;
    }

    /**
     * @return array<string, list<string>> every file in `app/` but the migrations, cut at `;`
     */
    private function statements(): array {
        $statements = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APPPATH));
        foreach ($files as $file) {
            $path = (string) $file;
            if (!str_ends_with($path, '.php') || str_contains($path, '/Database/Migrations/')) {
                continue;
            }
            $statements['app/' . substr($path, strlen(APPPATH))] = explode(';', file_get_contents($path));
        }
        return $statements;
    }

    /**
     * @return list<Entity>
     */
    private function entities(): array {
        $entities = [];
        foreach (glob(APPPATH . 'Entities/*.php') as $file) {
            $class = 'App\\Entities\\' . basename($file, '.php');
            if (class_exists($class) && is_subclass_of($class, Entity::class)) {
                $entities[] = new $class();
            }
        }
        return $entities;
    }

    /**
     * @return list<string>
     */
    private function secretFieldsOf(Entity $entity): array {
        $fields = $entity->hiddenFields ?? [];
        foreach (['SecretFields', 'EncryptedFields'] as $constant) {
            if (defined($entity::class . "::{$constant}")) {
                $fields = [...$fields, ...constant($entity::class . "::{$constant}")];
            }
        }
        return array_values(array_unique($fields));
    }

    // </editor-fold>

}
