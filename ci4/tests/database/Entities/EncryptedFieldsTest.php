<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Libraries\Crypt;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The credentials kso holds for other systems, held against what encrypting them is for.
 *
 * Two things have to be true of each, and they are different assertions: the column does not
 * contain the credential, and reading the entity does. A test that only read the entity back
 * would pass with no encryption at all.
 *
 * The list comes from `EncryptedFields` on the entities rather than from here, so a field
 * added to one of them is covered without anyone remembering this file - and an entity that
 * stops encrypting a field fails `testEveryEntityThatHoldsACredentialEncryptsIt()` instead
 * of quietly dropping out of the sweep.
 */
class EncryptedFieldsTest extends DatabaseTestCase {

    /**
     * Every entity that keeps another system's credential.
     *
     * Written here on purpose, unlike the field names: this is the list somebody has to
     * think about when they add a table with a password in it, and deriving it from the
     * entities that already encrypt would make it agree with itself.
     *
     * @return list<class-string>
     */
    private static function entitiesThatHoldCredentials(): array {
        return [
            \App\Entities\ContainerRegistry::class,
            \App\Entities\DatabaseService::class,
            \App\Entities\Deployment::class,
            \App\Entities\EmailService::class,
            \App\Entities\GithubIntegration::class,
            \App\Entities\PodioIntegration::class,
            \App\Entities\User::class,
            \App\Entities\Webhook::class,
            \App\Entities\WebhookDelivery::class,
        ];
    }

    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function everyEncryptedField(): array {
        $cases = [];

        foreach (self::entitiesThatHoldCredentials() as $entity) {
            foreach ($entity::EncryptedFields as $field) {
                $cases[substr(strrchr($entity, '\\'), 1) . '.' . $field] = [$entity, $field];
            }
        }

        return $cases;
    }

    public function testEveryEntityThatHoldsACredentialEncryptsIt(): void {
        $without = [];

        foreach (self::entitiesThatHoldCredentials() as $entity) {
            if (!defined("{$entity}::EncryptedFields") || $entity::EncryptedFields === []) {
                $without[] = $entity;
            }
        }

        $this->assertSame([], $without);
    }

    /**
     * What ends up in the column is not the credential.
     *
     * Written through the entity and read off the table, which is the pair that matters:
     * the entity is what the application uses, and the table is what a backup, a replica or
     * a read-only SQL account sees.
     */
    #[DataProvider('everyEncryptedField')]
    public function testTheColumnDoesNotHoldTheCredential(string $entity, string $field): void {
        $item = $this->saved($entity, $field, 'a-real-credential');

        $raw = (string) $this->db->table($item->_getModel()->getTableName())
            ->where('id', $item->id)
            ->get()
            ->getRowArray()[$field];

        $this->assertStringNotContainsString('a-real-credential', $raw);
        $this->assertTrue(Crypt::IsEncrypted($raw), 'stored without the marker, so nothing encrypted it');
    }

    /**
     * And the application still gets the credential back, from a row it did not write itself.
     */
    #[DataProvider('everyEncryptedField')]
    public function testTheEntityReadsTheCredentialBack(string $entity, string $field): void {
        $written = $this->saved($entity, $field, 'a-real-credential');

        /** @var \OrmExtension\Extensions\Entity $reloaded */
        $reloaded = new $entity();
        $reloaded->find($written->id);

        $this->assertSame('a-real-credential', $reloaded->{$field});
    }

    /**
     * A row an older pod wrote during a rolling upgrade, or one the migration has not
     * reached yet, is handed back as it is rather than as failure.
     *
     * This is what makes the upgrade survivable, and it is the one place where a value that
     * is not encrypted is accepted on purpose.
     */
    #[DataProvider('everyEncryptedField')]
    public function testAValueStoredBeforeTheMarkerIsStillReadable(string $entity, string $field): void {
        $item = $this->saved($entity, $field, 'a-real-credential');

        $this->db->table($item->_getModel()->getTableName())
            ->where('id', $item->id)
            ->update([$field => 'written-by-an-older-pod']);

        /** @var \OrmExtension\Extensions\Entity $reloaded */
        $reloaded = new $entity();
        $reloaded->find($item->id);

        $this->assertSame('written-by-an-older-pod', $reloaded->{$field});
    }

    /**
     * An entity with the one field set and nothing else, saved.
     *
     * Deliberately not through `Fixtures`: what is under test is the entity's own write
     * path, and every one of these tables takes a row with only this column filled.
     */
    private function saved(string $entity, string $field, string $value): \OrmExtension\Extensions\Entity {
        /** @var \OrmExtension\Extensions\Entity $item */
        $item = new $entity();
        $item->{$field} = $value;
        $item->save();

        return $item;
    }

}
