<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\CronjobStep;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\KServiceStep;
use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\ManifestTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The security context every workload gets: its container image's, which the specification may
 * override - see `SecurityContext`.
 *
 * An image made before kso stamped images has none of the new settings, and deploys exactly as
 * it did - so the first test is that nothing new appears. Then that the image's settings reach
 * every workload, that the specification's On and Off win over them, and that each container
 * follows its own image: a migration image or an init container that runs as root beside an app
 * that does not is no reason for all or nothing. The same four workloads every time; they used
 * to carry four copies of this code.
 */
class SecurityContextTest extends ManifestTestCase {

    private const array Image = [
        'security_context_run_as_user' => '1000',
        'security_context_run_as_group' => '2000',
        'security_context_fs_group' => '3000',
        'security_context_allow_privilege_escalation' => false,
        'security_context_read_only_root_filesystem' => true,
    ];

    #[DataProvider('workloads')]
    public function testAnImageWithoutTheNewSettingsDeploysAsBefore(string $workload): void {
        [$podSpec, $container] = $this->built($workload);

        $this->assertSame([
            'runAsUser' => 1000,
            'runAsGroup' => 2000,
            'allowPrivilegeEscalation' => false,
            'readOnlyRootFilesystem' => true,
        ], $container['securityContext']);
        $this->assertSame(3000, $podSpec['securityContext']['fsGroup']);
    }

    #[DataProvider('workloads')]
    public function testTheImagesSettingsReachTheWorkload(string $workload): void {
        [, $container] = $this->built($workload, [], [
            'security_context_run_as_non_root' => true,
            'security_context_drop_all_capabilities' => true,
            'security_context_seccomp_runtime_default' => true,
        ]);

        $this->assertTrue($container['securityContext']['runAsNonRoot']);
        $this->assertSame(['drop' => ['ALL']], $container['securityContext']['capabilities']);
        $this->assertSame(['type' => 'RuntimeDefault'], $container['securityContext']['seccompProfile']);
    }

    #[DataProvider('workloads')]
    public function testTheSpecificationTurnsOnWhatTheImageHasOff(string $workload): void {
        [, $container] = $this->built($workload, ['security_context_run_as_non_root' => \SecurityContextOverrides::On]);

        $this->assertTrue($container['securityContext']['runAsNonRoot']);
        $this->assertArrayNotHasKey('capabilities', $container['securityContext'], 'only what it was told');
        $this->assertArrayNotHasKey('seccompProfile', $container['securityContext']);
    }

    /**
     * The way out when a version of the image turns out to need root - or a name - after all.
     */
    #[DataProvider('workloads')]
    public function testTheSpecificationTurnsOffWhatTheImageHasOn(string $workload): void {
        [, $container] = $this->built($workload, [
            'security_context_run_as_non_root' => \SecurityContextOverrides::Off,
            'security_context_seccomp_runtime_default' => \SecurityContextOverrides::Off,
        ], [
            'security_context_run_as_non_root' => true,
            'security_context_seccomp_runtime_default' => true,
        ]);

        $this->assertArrayNotHasKey('runAsNonRoot', $container['securityContext']);
        $this->assertArrayNotHasKey('seccompProfile', $container['securityContext']);
    }

    /**
     * One group for the whole specification, kept when an image's uid changes - so a new version
     * can still write what an old one left on a volume.
     */
    #[DataProvider('workloads')]
    public function testTheSpecificationsFsGroupWinsOverTheImages(string $workload): void {
        [$podSpec] = $this->built($workload, ['security_context_fs_group' => '4000']);

        $this->assertSame(4000, $podSpec['securityContext']['fsGroup']);
    }

    public function testNoFsGroupAnywhereMeansNone(): void {
        [$podSpec] = $this->built('deployment', [], ['security_context_fs_group' => '']);

        $this->assertArrayNotHasKey('securityContext', $podSpec);
    }

    /**
     * The app's image runs as non-root, the init container's image has not been made so: each
     * gets its own. The specification's override, when there is one, applies to both.
     */
    public function testEachContainerFollowsItsOwnImage(): void {
        $deployment = $this->deployment('deployment', [], ['security_context_run_as_non_root' => true]);
        $initImage = Fixtures::containerImage(['url' => 'registry.example.org/init', 'security_context_run_as_user' => '1500']);
        $initContainer = Fixtures::initContainer(['name' => 'wait-for-db', 'container_image_id' => $initImage->id]);
        Fixtures::specificationInitContainer([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'init_container_id' => $initContainer->id,
        ]);

        $podSpec = $this->manifest(DeploymentStep::class, $deployment)['spec']['template']['spec'];

        $this->assertTrue($podSpec['containers'][0]['securityContext']['runAsNonRoot']);
        $this->assertArrayNotHasKey('runAsNonRoot', $podSpec['initContainers'][0]['securityContext']);
        $this->assertSame(1500, $podSpec['initContainers'][0]['securityContext']['runAsUser']);

        $spec = $deployment->findDeploymentSpecification();
        $spec->security_context_run_as_non_root = \SecurityContextOverrides::On;
        $spec->save();

        $overridden = $this->manifest(DeploymentStep::class, $this->reread($deployment))['spec']['template']['spec'];
        $this->assertTrue($overridden['initContainers'][0]['securityContext']['runAsNonRoot']);
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
        $image = array_merge(self::Image, $image);
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
            // The cron job's own image, with the same settings, so what is read is the cron job's
            // container and not the Deployment's.
            $cronImage = Fixtures::containerImage(array_merge(['url' => 'registry.example.org/cron'], $image));
            Fixtures::specificationCronJob([
                'deployment_specification_id' => $deployment->deployment_specification_id,
                'k8s_cron_job_id' => Fixtures::cronJob(['container_image_id' => $cronImage->id])->id,
            ]);
        }

        return $deployment;
    }

    private function reread(Deployment $deployment): Deployment {
        $fresh = new Deployment();
        $fresh->find($deployment->id);

        return $fresh;
    }

    // </editor-fold>

}
