<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Entities\MigrationJob;
use App\Fixtures;
use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\MigrationJobs\MigrationJobWatcher;
use App\Models\MigrationJobModel;

/**
 * A migration run for real and followed in the cluster: the pod's container state, exit code and
 * log, read the way `KubernetesMigrationJobCluster` reads them. `MigrationJobWatcherTest` has the
 * cases; this is that the cluster hands them over in the shape the watcher expects.
 *
 * It uses the image the rest of the cluster suite already pulls, which has no curl to report
 * with - nor needs one now.
 */
class MigrationJobWatcherClusterTest extends ClusterTestCase {

    public function testAMigrationThatWorksIsReadFromTheCluster(): void {
        $job = $this->migrate('echo Migrating; echo Done.');

        $this->assertSame(\MigrationJobStatusTypes::Completed, $job->status);
        $this->assertSame(0, (int) $job->exit_code);
        $this->assertSame("Migrating\nDone.", $job->log);
        $this->assertNotEmpty($job->started);
        $this->assertNotEmpty($job->ended);
    }

    public function testAMigrationThatFailsIsReadWithItsExitCodeAndRunOnce(): void {
        $job = $this->migrate('echo Migrating; exit 3');

        $this->assertSame(\MigrationJobStatusTypes::Failed_ExitCode, $job->status);
        $this->assertSame(3, (int) $job->exit_code);
        $this->assertStringStartsWith('Migrating', $job->log);

        // `backoffLimit: 0` - one pod, not seven.
        $pods = $this->cluster()->getAllPods($this->testNamespace, ['labelSelector' => 'app=api,role=migration']);
        $this->assertCount(1, $pods);
    }

    /**
     * A sidecar in the migration job keeps running beside the migration - and the Job still
     * completes: Kubernetes stops the sidecar when the migration's container is done, which is
     * why sidecars are kept in the migration job rather than filtered out.
     */
    public function testAMigrationWithASidecarStillCompletes(): void {
        $job = $this->migrate('echo Migrating; echo Done.', withASidecar: true);

        $this->assertSame(\MigrationJobStatusTypes::Completed, $job->status);
        $this->assertSame(0, (int) $job->exit_code);
    }

    private function migrate(string $command, bool $withASidecar = false): MigrationJob {
        $deployment = $this->deploymentInTheTestNamespace();
        $specification = $deployment->findDeploymentSpecification();
        if ($withASidecar) {
            $sidecar = Fixtures::initContainer([
                'name' => 'proxy',
                'container_image_id' => $specification->container_image_id,
                'container_image_tag_policy' => \ContainerImageTagPolicies::Default,
                'command' => 'sleep',
                'args' => json_encode(['3600']),
                'is_sidecar' => true,
            ]);
            Fixtures::specificationInitContainer([
                'deployment_specification_id' => $specification->id,
                'init_container_id' => $sidecar->id,
                'include_in_migration_job' => true,
            ]);
        }
        $specification->enable_database = true;
        $specification->database_migration_command = $command;
        $specification->database_migration_verification_type = \MigrationVerificationTypes::EndsWith;
        $specification->database_migration_verification_value = 'Done.';
        $specification->save();
        $deployment->database_service_id = Fixtures::databaseService()->id;
        $deployment->save();
        (new NamespaceStep())->startDeployCommand($deployment);

        (new MigrationJobStep())->startDeployCommand($deployment);
        $id = (int) $this->lastMigrationJob($deployment)->id;

        // The watcher's own loop, with a limit a test can wait for.
        $until = time() + 60;
        while (!MigrationJobWatcher::Check($id)) {
            if (time() > $until) {
                $this->fail('The migration did not end within a minute: ' . $this->lastMigrationJob($deployment)->status);
            }
            sleep(1);
        }

        return $this->lastMigrationJob($deployment);
    }

    private function lastMigrationJob(Deployment $deployment): MigrationJob {
        /** @var MigrationJob $jobs */
        $jobs = (new MigrationJobModel())
            ->where('deployment_id', $deployment->id)
            ->orderBy('id', 'desc')
            ->find();

        return $jobs->first();
    }

}
