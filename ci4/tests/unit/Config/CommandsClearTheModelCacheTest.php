<?php namespace App\Tests\Unit\Config;

use App\RestoresTheExceptionHandler;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use OrmExtension\DataMapper\ModelDefinitionCache;

/**
 * Why a spark command throws the model cache away when it finishes.
 *
 * The ORM caches each entity's column list on disk, keyed by the entity name alone - with
 * no database group in the key. A migration therefore leaves the cache describing the
 * schema as it was *before* it ran, and the next request builds its queries from that: it
 * asks for columns that no longer exist, or leaves out ones that now do.
 *
 * The old fix was a line in the `spark` file, which had to be merged by hand on every
 * framework upgrade. It is an event now. This holds the wiring - that the listener
 * is on `post_command` and that it actually clears - because nothing else would notice if
 * a later upgrade dropped `app/Config/Events.php` on the floor.
 */
class CommandsClearTheModelCacheTest extends CIUnitTestCase {

    use RestoresTheExceptionHandler;

    protected function setUp(): void {
        parent::setUp();

        $this->rememberTheExceptionHandler();
    }

    public function testFinishingACommandClearsTheCache(): void {
        ModelDefinitionCache::setFields('SomeEntity', ['id', 'name']);
        $this->assertSame(['id', 'name'], $this->cached('SomeEntity_fields'));

        Events::trigger('post_command');

        $this->assertNull(
            $this->cached('SomeEntity_fields'),
            'a migration that changed the schema must not leave the old columns behind'
        );
    }

    /**
     * `pre_command` is where the extensions hook in, and it must not clear anything - a
     * command that reads the cache before it writes would rebuild it for nothing.
     */
    public function testStartingACommandLeavesTheCacheAlone(): void {
        ModelDefinitionCache::setFields('SomeEntity', ['id']);

        Events::trigger('pre_command');

        $this->assertSame(['id'], $this->cached('SomeEntity_fields'));
    }

    /**
     * Read the store rather than the accessor: `getFields()` treats a miss as "ask the
     * database", so it would answer with real columns instead of telling us the cache was
     * emptied - and it would need a database to do it.
     */
    private function cached(string $key): mixed {
        return ModelDefinitionCache::getInstance()->cache->get($key);
    }

    protected function tearDown(): void {
        ModelDefinitionCache::getInstance()->clearCache();

        parent::tearDown();

        $this->restoreTheExceptionHandler();
    }

}
