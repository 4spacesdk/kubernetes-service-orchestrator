<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\CronjobStep;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\KServiceStep;
use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\Libraries\Kubernetes\WritablePaths;
use App\ManifestTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Where a container with a read-only root filesystem may still write: an `emptyDir` on each path
 * its image says it writes to, with a name that is the same at every deploy. See `WritablePaths`.
 */
class WritablePathsTest extends ManifestTestCase {

    private const array ReadOnly = [
        'security_context_read_only_root_filesystem' => true,
        'writable_paths' => '/tmp,/var/run/apache2',
    ];

    #[DataProvider('workloads')]
    public function testEachPathIsAnEmptyDirMountedWhereItWrites(string $workload): void {
        [$podSpec, $container] = $this->built($workload, [], self::ReadOnly);

        $mounts = array_column($container['volumeMounts'], 'name', 'mountPath');
        $this->assertSame(['/tmp', '/var/run/apache2'], array_keys($mounts));
        $this->assertSame(WritablePaths::VolumeName($container['name'], '/tmp'), $mounts['/tmp'], 'the same name at every deploy');
        $volumes = array_column($podSpec['volumes'], null, 'name');
        $this->assertSame(['sizeLimit' => '1Gi'], $volumes[$mounts['/tmp']]['emptyDir'], 'on disk, with a limit');
    }

    #[DataProvider('workloads')]
    public function testWithoutReadOnlyNothingIsMounted(string $workload): void {
        [$podSpec, $container] = $this->built($workload, [], ['writable_paths' => '/tmp']);

        $this->assertArrayNotHasKey('volumeMounts', $container);
        $this->assertArrayNotHasKey('volumes', $podSpec);
    }

    /**
     * The specification adds paths for its workload, and sizes them - the image's stay.
     */
    public function testTheSpecificationAddsPathsAndSizesThem(): void {
        [$podSpec, $container] = $this->built('deployment', ['writable_paths' => '/var/www/html/ci4/writable', 'writable_paths_size_limit' => '256Mi'], self::ReadOnly);

        $this->assertSame(['/tmp', '/var/run/apache2', '/var/www/html/ci4/writable'], array_column($container['volumeMounts'], 'mountPath'));
        $this->assertSame(['256Mi'], array_unique(array_map(fn(array $volume) => $volume['emptyDir']['sizeLimit'], $podSpec['volumes'])));
    }

    /**
     * A sidecar has its own image, and that image's paths - not the app's, nor the specification's.
     */
    public function testASidecarGetsItsOwnImagesPaths(): void {
        $deployment = $this->deployment('deployment', ['writable_paths' => '/var/cache/app'], self::ReadOnly);
        $pushImage = Fixtures::containerImage(['url' => 'centrifugo/centrifugo', 'security_context_read_only_root_filesystem' => true, 'writable_paths' => '/data']);
        $sidecar = Fixtures::initContainer(['name' => 'push', 'container_image_id' => $pushImage->id, 'is_sidecar' => true]);
        Fixtures::specificationInitContainer(['deployment_specification_id' => $deployment->deployment_specification_id, 'init_container_id' => $sidecar->id]);

        $podSpec = $this->manifest(DeploymentStep::class, $deployment)['spec']['template']['spec'];

        $this->assertSame(['/data'], array_column($podSpec['initContainers'][0]['volumeMounts'], 'mountPath'));
        $this->assertSame(['/tmp', '/var/run/apache2', '/var/cache/app'], array_column($podSpec['containers'][0]['volumeMounts'], 'mountPath'));
        $this->assertCount(4, $podSpec['volumes'], 'one each');
    }

    /**
     * Knative takes an `emptyDir` behind a feature flag - on by default. A cluster that turned it
     * off has the deploy refused before anything is sent.
     */
    public function testAKnativeWithEmptyDirTurnedOffIsRefused(): void {
        KServiceStep::$knativeFeatures = fn() => ['kubernetes.podspec-volumes-emptydir' => 'disabled'];
        $deployment = $this->deployment('knative', [], self::ReadOnly);
        $deployment->image = 'registry.example.org/test/app';

        $this->assertStringContainsString('kubernetes.podspec-volumes-emptydir', (string) (new KServiceStep())->validateDeployCommand($deployment));

        KServiceStep::$knativeFeatures = fn() => ['kubernetes.podspec-volumes-emptydir' => 'enabled'];
        $this->assertStringNotContainsString('emptydir', (string) (new KServiceStep())->validateDeployCommand($deployment));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function workloads(): array {
        return [
            'deployment' => ['deployment'],
            'knative service' => ['knative'],
            'cron job' => ['cronjob'],
            'migration job' => ['migration'],
        ];
    }

    // <editor-fold desc="Building">

    /**
     * @param array<string, mixed> $specification
     * @param array<string, mixed> $image
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} The pod spec and its container
     */
    private function built(string $workload, array $specification = [], array $image = []): array {
        $deployment = $this->deployment($workload, $specification, $image);

        $podSpec = match ($workload) {
            'deployment' => $this->manifest(DeploymentStep::class, $deployment)['spec']['template']['spec'],
            'knative' => $this->manifest(KServiceStep::class, $deployment)['spec']['template']['spec'],
            'cronjob' => $this->manifests(CronjobStep::class, $deployment)[0]['spec']['jobTemplate']['spec']['template']['spec'],
            'migration' => $this->manifest(MigrationJobStep::class, $deployment)['spec']['template']['spec'],
        };

        return [$podSpec, $podSpec['containers'][0]];
    }

    /**
     * @param array<string, mixed> $specification
     * @param array<string, mixed> $image
     */
    private function deployment(string $workload, array $specification = [], array $image = []): Deployment {
        $specification = array_merge(
            match ($workload) {
                'knative' => ['workload_type' => \WorkloadTypes::KNativeService],
                'migration' => ['enable_database' => true, 'database_migration_command' => 'php spark migrate'],
                default => [],
            },
            $specification
        );

        $deployment = Fixtures::deployableDeployment([], $specification, $image);

        if ($workload === 'cronjob') {
            $cronImage = Fixtures::containerImage(array_merge(['url' => 'registry.example.org/cron'], $image));
            Fixtures::specificationCronJob([
                'deployment_specification_id' => $deployment->deployment_specification_id,
                'k8s_cron_job_id' => Fixtures::cronJob(['container_image_id' => $cronImage->id])->id,
            ]);
        }

        return $deployment;
    }

    // </editor-fold>

}
