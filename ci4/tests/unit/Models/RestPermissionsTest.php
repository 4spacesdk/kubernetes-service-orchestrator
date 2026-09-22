<?php namespace App\Tests\Unit\Models;

use CodeIgniter\Test\CIUnitTestCase;
use ReflectionClass;
use RestExtension\ResourceModelInterface;

/**
 * Who may create, change and delete each resource through the API.
 *
 * Every model exposed over REST answers three questions - `isRestCreationAllowed`,
 * `isRestUpdateAllowed`, `isRestDeleteAllowed` - and each answer is a one line `return`.
 * Together they are kso's permission table, and nothing else writes it down: it is spread
 * across fifty files, three lines at a time, where a flipped boolean looks like a typo
 * rather than a policy change.
 *
 * So it is written down here. A model that changes its mind fails this test and has to say
 * so in the diff.
 */
class RestPermissionsTest extends CIUnitTestCase {

    /**
     * Everything not named here allows all three. The list is short on purpose: it is the
     * exceptions that carry the decisions.
     *
     * @var array<string, string[]>
     */
    private const Refuses = [
        // Created by the auto-update machinery when a registry reports a new tag, and
        // approved or rejected through their own endpoints - never posted by a client.
        'AutoUpdateModel' => ['create', 'update'],

        // A deployment is created and changed through its own endpoints, which run the
        // deployment steps. A plain REST write would change the row and leave the cluster
        // as it was.
        'DeploymentModel' => ['create', 'update'],
        'WorkspaceModel' => ['create', 'update'],

        // Written by the image scanner; read through the API. A scan is asked for through
        // `PUT container-images/{id}/scan`, not by posting a row.
        'ContainerImageScanModel' => ['create', 'update', 'delete'],
        'ContainerImageScanRecordModel' => ['create', 'update', 'delete'],

        // A record of something that happened. Rewriting history is not a feature.
        'MigrationJobModel' => ['create', 'update', 'delete'],

        // The permission model itself. Roles and their permissions are seeded by
        // migrations; letting the API edit them would let a client widen its own access.
        'RbacPermissionModel' => ['create', 'update', 'delete'],
        'RbacRoleModel' => ['create', 'update', 'delete'],

        // Exactly one row exists, and it is created once by a migration.
        'SystemModel' => ['create', 'delete'],
    ];

    /**
     * Three models are not exposed at all. Named here so that exposing one is a deliberate
     * act rather than something that happens by adding an interface.
     */
    private const NotExposed = ['CronJobModel', 'DeletionModel'];

    public function testThePermissionTableIsWhatWeThinkItIs(): void {
        $refused = [];

        foreach ($this->restModels() as $name => $model) {
            $no = [];
            if (!$model->isRestCreationAllowed(null)) {
                $no[] = 'create';
            }
            if (!$model->isRestUpdateAllowed(null)) {
                $no[] = 'update';
            }
            if (!$model->isRestDeleteAllowed(null)) {
                $no[] = 'delete';
            }
            if ($no !== []) {
                $refused[$name] = $no;
            }
        }

        ksort($refused);
        $expected = self::Refuses;
        ksort($expected);

        $this->assertSame($expected, $refused);
    }

    /**
     * A model that inherits the hooks instead of defining them takes whatever the extension
     * defaults to - which is how a new resource ends up writable by anyone without a line
     * anywhere saying so.
     */
    public function testEveryExposedModelAnswersForItself(): void {
        $inheriting = [];

        foreach ($this->restModels() as $name => $model) {
            $class = new ReflectionClass($model);
            foreach (['isRestCreationAllowed', 'isRestUpdateAllowed', 'isRestDeleteAllowed'] as $method) {
                if ($class->getMethod($method)->getDeclaringClass()->getName() !== $class->getName()) {
                    $inheriting[] = "$name::$method";
                }
            }
        }

        $this->assertSame([], $inheriting);
    }

    public function testTheModelsOutsideTheApiAreTheOnesWeExpect(): void {
        $outside = [];

        foreach ($this->modelClasses() as $name => $class) {
            if (!(new ReflectionClass($class))->implementsInterface(ResourceModelInterface::class)) {
                $outside[] = $name;
            }
        }

        sort($outside);
        $expected = self::NotExposed;
        sort($expected);

        $this->assertSame($expected, $outside);
    }

    /**
     * There are fifty of them, so a test that quietly found none would pass on everything.
     */
    public function testTheModelsWereActuallyFound(): void {
        $this->assertGreaterThan(40, count($this->restModels()));
    }

    /**
     * Built without the constructor: nothing here asks a question that needs a database,
     * and a connection per model would turn this into the slowest test in the suite.
     *
     * @return array<string, ResourceModelInterface>
     */
    private function restModels(): array {
        $models = [];

        foreach ($this->modelClasses() as $name => $class) {
            $reflection = new ReflectionClass($class);
            if ($reflection->implementsInterface(ResourceModelInterface::class)) {
                $models[$name] = $reflection->newInstanceWithoutConstructor();
            }
        }

        return $models;
    }

    /**
     * @return array<string, class-string>
     */
    private function modelClasses(): array {
        $classes = [];

        foreach (glob(APPPATH . 'Models/*Model.php') as $file) {
            $name = basename($file, '.php');
            $class = 'App\\Models\\' . $name;
            if (class_exists($class) && !(new ReflectionClass($class))->isAbstract()) {
                $classes[$name] = $class;
            }
        }

        return $classes;
    }

}
