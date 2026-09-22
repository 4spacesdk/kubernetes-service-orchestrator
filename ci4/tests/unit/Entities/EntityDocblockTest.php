<?php namespace App\Tests\Unit\Entities;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The entity docblocks, held to what the model generator can read.
 *
 * `dev:sync_models_and_api` writes the web app's TypeScript models straight from these lines, and
 * it does no checking: whatever stands after `@property` becomes a TypeScript type, and the
 * generator appends `id`, `created` and the other bookkeeping fields to every entity on top of
 * what it found. Two ways that ends in a file that will not compile, both of which it has:
 *
 * * `@property string|null $x` became `import {string|null} from '../string|null'`: a type the
 *   generator does not know as a simple one is taken to be another model, and imported by name.
 * * A docblock repeating `created` became the property twice in the same class.
 *
 * Neither fails the generator, and neither fails anything in this suite either - the first sign is
 * a frontend build that stops. So the docblocks are checked here instead, where it costs nothing.
 */
class EntityDocblockTest extends CIUnitTestCase {

    /**
     * What `ModelItem::create()` adds to every entity by itself - see the ORM extension. A
     * docblock naming one of them gets it twice.
     */
    private const array AddedByTheGenerator = [
        'id', 'created', 'updated',
        'created_by_id', 'created_by', 'updated_by_id', 'updated_by',
        'deletion_id', 'deletion',
    ];

    public function testNoDocblockRepeatsAFieldTheGeneratorAddsItself(): void {
        $repeated = [];
        foreach ($this->properties() as $entity => $properties) {
            foreach ($properties as [$type, $name]) {
                if (in_array($name, self::AddedByTheGenerator, true)) {
                    $repeated[] = "{$entity}::\${$name}";
                }
            }
        }

        $this->assertSame([], $repeated, 'these are added by the generator and must not be in the docblock');
    }

    /**
     * What `PropertyItem::setType()` writes as a TypeScript type by itself. Anything else is taken
     * to be another model and imported under that name - right for `Workspace`, and a file that
     * will not compile for `string|null`.
     */
    private const array SimpleTypes = ['int', 'double', 'boolean', 'bool', 'string', 'string|double', 'int[]'];

    public function testEveryPropertyTypeIsOneTheGeneratorCanWrite(): void {
        $unwritable = [];
        foreach ($this->properties() as $entity => $properties) {
            foreach ($properties as [$type, $name]) {
                if (in_array($type, self::SimpleTypes, true)) {
                    continue;
                }

                // Not a simple type, so it has to name a model the generator can import.
                $class = rtrim($type, '[]');
                if (class_exists("App\\Entities\\{$class}") || interface_exists("App\\Interfaces\\{$class}")) {
                    continue;
                }

                $unwritable[] = "{$entity}::\${$name} is {$type}";
            }
        }

        $this->assertSame([], $unwritable, 'neither a type the generator knows nor a model it can import');
    }

    /**
     * Every entity's `@property` lines, as [type, name].
     *
     * @return array<string, list<array{0: string, 1: string}>>
     */
    private function properties(): array {
        $found = [];
        foreach (glob(APPPATH . 'Entities/*.php') as $file) {
            $entity = basename($file, '.php');
            preg_match_all('/@property\s+(\S+)\s+\$(\w+)/', (string) file_get_contents($file), $matches, PREG_SET_ORDER);
            $found[$entity] = array_map(fn(array $match) => [$match[1], $match[2]], $matches);
        }

        return $found;
    }

}
