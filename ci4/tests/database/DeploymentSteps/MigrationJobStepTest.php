<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\MigrationJob;
use App\Fixtures;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\Libraries\Kubernetes\KubeHelper;
use App\ManifestTestCase;
use App\Models\MigrationJobModel;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sJob;

/**
 * The Job that migrates a deployment's database before the new version serves traffic.
 *
 * The riskiest step in the chain. It runs once per release, against customer data, and
 * reports its own progress back to kso over http - so the manifest carries not just an
 * image and a command but the whole callback arrangement that decides whether a release
 * is seen to finish at all.
 */
class MigrationJobStepTest extends ManifestTestCase {

    public function testJobIsNamedAndNamespacedAfterTheDeployment(): void {
        $deployment = $this->migratableDeployment();

        $manifest = $this->build($deployment);

        $this->assertSame($deployment->name, $manifest['metadata']['name']);
        $this->assertSame($deployment->namespace, $manifest['metadata']['namespace']);
    }

    /**
     * The pod is labelled `role: migration`, which is what tells it apart from the app's
     * own pods - they carry `role: app` from the Deployment step.
     */
    public function testPodIsLabelledAsAMigration(): void {
        $deployment = $this->migratableDeployment();

        $this->assertSame(
            ['app' => $deployment->name, 'role' => 'migration'],
            $this->build($deployment)['spec']['template']['metadata']['labels']
        );
    }

    /**
     * Without a migration image of its own, the job runs the application's image at the
     * version being deployed - the version whose migrations are supposed to run.
     */
    public function testImageDefaultsToTheApplicationImageAtTheDeployedVersion(): void {
        $deployment = $this->migratableDeployment(
            ['version' => '4.5.6'],
            [],
            ['url' => 'registry.example.org/app']
        );

        $this->assertSame('registry.example.org/app:4.5.6', $this->container($deployment)['image']);
    }

    public function testCustomMigrationImageTracksTheDeployedVersion(): void {
        $migrationImage = Fixtures::containerImage(['url' => 'registry.example.org/migrator']);
        $deployment = $this->migratableDeployment(['version' => '4.5.6'], [
            'database_migration_container_image_id' => $migrationImage->id,
            'database_migration_container_image_tag_policy' => \ContainerImageTagPolicies::MatchDeployment,
        ]);

        $this->assertSame('registry.example.org/migrator:4.5.6', $this->container($deployment)['image']);
    }

    public function testCustomMigrationImageCanBePinnedToOneTag(): void {
        $migrationImage = Fixtures::containerImage(['url' => 'registry.example.org/migrator']);
        $deployment = $this->migratableDeployment([], [
            'database_migration_container_image_id' => $migrationImage->id,
            'database_migration_container_image_tag_policy' => \ContainerImageTagPolicies::Static,
            'database_migration_container_image_tag_value' => 'v2',
        ]);

        $this->assertSame('registry.example.org/migrator:v2', $this->container($deployment)['image']);
    }

    /**
     * The third of the three tag policies the dialog offers. It used to reach a `match` with
     * no arm for it, so picking it threw while the manifest was built and the whole deploy
     * failed. Cron jobs and init containers have always taken the image's own default tag.
     */
    public function testDefaultTagPolicyOnTheMigrationImageUsesTheImagesDefaultTag(): void {
        $migrationImage = Fixtures::containerImage([
            'url' => 'registry.example.org/migrator',
            'default_tag' => 'stable',
        ]);
        $deployment = $this->migratableDeployment([], [
            'database_migration_container_image_id' => $migrationImage->id,
            'database_migration_container_image_tag_policy' => \ContainerImageTagPolicies::Default,
        ]);

        $this->assertSame('registry.example.org/migrator:stable', $this->container($deployment)['image']);
    }

    /**
     * The container is a shell running three things in order: tell kso it started, run
     * the migration, pipe its output to kso as it ends.
     */
    public function testCommandRunsTheMigrationBetweenTwoCallbacks(): void {
        $deployment = $this->migratableDeployment([], ['database_migration_command' => 'php spark migrate']);

        $container = $this->container($deployment);

        $this->assertSame(['/bin/sh'], $container['command']);
        $this->assertSame('-c', $container['args'][0]);

        $script = $container['args'][1];
        $this->assertStringContainsString('/api/migration-jobs/$(MIGRATION_JOB_ID)/started', $script);
        $this->assertStringContainsString('php spark migrate', $script);
        $this->assertStringContainsString('/api/migration-jobs/$(MIGRATION_JOB_ID)/ended', $script);
    }

    /**
     * A kso that cannot be reached must not stop the migration (#42). The first callback
     * used to be joined to it with `&&`, so the job failed on the curl and never migrated.
     *
     * Run for real: the script goes through `/bin/sh` with a `curl` on the path that fails
     * every call to `started` and records what `ended` is sent.
     */
    public function testTheMigrationRunsEvenWhenKsoCannotBeReachedAtTheStart(): void {
        $deployment = $this->migratableDeployment([], ['database_migration_command' => 'echo migrated']);
        $dir = sys_get_temp_dir() . '/kso-migration-' . uniqid();
        mkdir($dir);
        file_put_contents("{$dir}/curl", implode("\n", [
            '#!/bin/sh',
            'case "$*" in',
            "  */started*) echo started >> {$dir}/calls; exit 7 ;;",
            "  */ended*) echo ended >> {$dir}/calls; cat > {$dir}/ended-body ;;",
            'esac',
        ]));
        chmod("{$dir}/curl", 0755);

        try {
            exec('PATH=' . escapeshellarg("{$dir}:" . getenv('PATH')) . ' /bin/sh -c ' . escapeshellarg($this->container($deployment)['args'][1]) . ' 2>/dev/null');

            $this->assertSame("started\nended\n", file_get_contents("{$dir}/calls"));
            $this->assertSame("migrated\n", file_get_contents("{$dir}/ended-body"));
        } finally {
            array_map('unlink', glob("{$dir}/*"));
            rmdir($dir);
        }
    }

    /**
     * The first callback gives up within a minute: it is only a status now, and waiting
     * longer would hold up the release it reports on.
     */
    public function testTheStartedCallbackRetriesBriefly(): void {
        $deployment = $this->migratableDeployment();

        $this->assertMatchesRegularExpression(
            '#curl --connect-timeout 5 --max-time 30 --retry 5 --retry-delay 5 --retry-max-time 60 -i -v -X PUT \S+/started#',
            $this->container($deployment)['args'][1]
        );
    }

    /**
     * The migration's own output is piped into the second callback and posted as its body,
     * which is what the migration log page shows. Joined with `;` instead of `|` the
     * migration would still run and still be reported finished, and the log would be empty
     * every time - a failure nobody would see until they went looking for the reason a
     * release did not take.
     */
    public function testTheMigrationsOutputIsPipedToTheEndedCallback(): void {
        $deployment = $this->migratableDeployment([], ['database_migration_command' => 'php spark migrate']);

        $script = $this->container($deployment)['args'][1];

        $this->assertMatchesRegularExpression('#php spark migrate\s+\|\s+curl#', $script);
        $this->assertStringContainsString('--data-binary @-', $script);
    }

    /**
     * The ended callback is the only thing that moves a migration off "running", so it is
     * the one call in the job that must not be given up on. kso is frequently restarting
     * when a migration ends - that is what a release is - and without the retries a job
     * that migrated perfectly well is left showing as still going, forever.
     */
    public function testTheEndedCallbackKeepsRetryingWhileKsoIsUnreachable(): void {
        $deployment = $this->migratableDeployment();

        $this->assertStringContainsString(
            '--connect-timeout 5 --max-time 300 --retry 10 --retry-delay 5 --retry-max-time 300',
            $this->container($deployment)['args'][1]
        );
    }

    /**
     * The policy the deployment was configured with, not one of this step's choosing. A
     * migration pinned to `IfNotPresent` would re-run the image already on the node, which
     * on a re-pushed tag is the previous release's migrations.
     */
    public function testImagePullPolicyComesFromTheDeployment(): void {
        $deployment = $this->migratableDeployment(['image_pull_policy' => \ImagePullPolicies::Always]);

        $this->assertSame(\ImagePullPolicies::Always, $this->container($deployment)['imagePullPolicy']);
    }

    /**
     * The id the callbacks report under is not known until the job is created, so the
     * built manifest carries only the shell variable. `startDeployCommand` writes the row
     * and injects the value - which is why this step cannot be deployed from the manifest
     * alone.
     */
    public function testMigrationJobIdIsNotInTheManifestYet(): void {
        $deployment = $this->migratableDeployment();

        $this->assertArrayNotHasKey('MIGRATION_JOB_ID', $this->environment($deployment));
    }

    /**
     * Hardcoded, whatever the deployment runs as. A migration reaches kso over plain http
     * inside the cluster, and `production` would have the framework insist on https.
     */
    public function testEnvironmentIsAlwaysDevelopment(): void {
        $deployment = $this->migratableDeployment(['environment' => \Environments::Production]);

        $this->assertSame(\Environments::Development, $this->environment($deployment)['ENVIRONMENT']);
    }

    public function testBaseUrlPointsAtTheWorkspace(): void {
        $deployment = $this->migratableDeployment();

        $this->assertStringContainsString('tenant.test.example.org', $this->environment($deployment)['BASE_URL']);
    }

    public function testASecretVariableIsReadFromTheJobsSecret(): void {
        $deployment = $this->migratableDeployment();
        Fixtures::specificationEnvironmentVariable([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'API_TOKEN',
            'value' => 'token-value',
            'is_secret' => true,
        ]);

        $manifest = $this->build($deployment);

        $env = array_column($this->container($deployment)['env'], null, 'name');
        $this->assertSame("{$deployment->name}-job-env", $env['API_TOKEN']['valueFrom']['secretKeyRef']['name']);
        $this->assertStringNotContainsString('token-value', json_encode($manifest));
    }

    public function testEnvironmentIsInheritedWithTheDeploymentsValuesWinning(): void {
        $deployment = $this->migratableDeployment();

        Fixtures::specificationEnvironmentVariable([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'SHARED',
            'value' => 'from-specification',
        ]);
        Fixtures::deploymentEnvironmentVariable([
            'deployment_id' => $deployment->id,
            'name' => 'SHARED',
            'value' => 'from-deployment',
        ]);

        $this->assertSame('from-deployment', $this->environment($deployment)['SHARED']);
    }

    /**
     * A migration that dies must not be restarted in place on top of a half-migrated
     * database.
     */
    public function testPodNeverRestarts(): void {
        $deployment = $this->migratableDeployment();

        $this->assertSame('Never', $this->podSpec($deployment)['restartPolicy']);
    }

    /**
     * Six hours, hardcoded. It is the only thing that ends a migration that hangs, and
     * until it fires the release sits in Running. The same limit is configurable in the
     * helm chart for kso's own migration job; here it is not.
     */
    public function testJobGivesUpAfterSixHours(): void {
        $deployment = $this->migratableDeployment();

        $this->assertSame(21600, $this->build($deployment)['spec']['activeDeadlineSeconds']);
    }

    /**
     * The migration mounts the same claim the application does, so a migration that
     * writes files puts them where the app will find them.
     */
    public function testVolumesAreMountedFromTheDeploymentsClaim(): void {
        $deployment = $this->migratableDeployment();
        Fixtures::deploymentVolume([
            'deployment_id' => $deployment->id,
            'mount_path' => '/var/www/storage',
            'sub_path' => 'data',
        ]);

        $podSpec = $this->podSpec($deployment);

        $this->assertSame(
            ['claimName' => $deployment->name],
            $podSpec['volumes'][0]['persistentVolumeClaim']
        );

        $mount = $this->container($deployment)['volumeMounts'][0];
        $this->assertSame('/var/www/storage', $mount['mountPath']);
        $this->assertSame('data', $mount['subPath']);
    }

    /**
     * Init containers are shared with the Deployment, and most of them have no business
     * running before a migration. Only the ones marked for it are carried over.
     */
    public function testOnlyInitContainersMarkedForMigrationAreIncluded(): void {
        $deployment = $this->migratableDeployment();
        $image = Fixtures::containerImage(['url' => 'registry.example.org/init']);

        foreach ([['wait-for-db', true], ['warm-cache', false]] as [$name, $include]) {
            $initContainer = Fixtures::initContainer(['name' => $name, 'container_image_id' => $image->id]);
            Fixtures::specificationInitContainer([
                'deployment_specification_id' => $deployment->deployment_specification_id,
                'init_container_id' => $initContainer->id,
                'include_in_migration_job' => $include,
            ]);
        }

        $initContainers = $this->podSpec($deployment)['initContainers'];

        $this->assertCount(1, $initContainers);
        $this->assertSame('wait-for-db', $initContainers[0]['name']);
    }

    /**
     * Init containers run to completion one after another before the migration's own
     * container starts, so the order they are declared in is the order they run in, and
     * `position` is what decides it. The one that opens the tunnel to the database has to
     * come before the one that waits for the database to answer through it.
     */
    public function testInitContainersAreDeclaredInPositionOrder(): void {
        $deployment = $this->migratableDeployment();
        $image = Fixtures::containerImage(['url' => 'registry.example.org/init']);

        foreach ([['wait-for-db', 1], ['open-the-tunnel', 0]] as [$name, $position]) {
            $initContainer = Fixtures::initContainer(['name' => $name, 'container_image_id' => $image->id]);
            Fixtures::specificationInitContainer([
                'deployment_specification_id' => $deployment->deployment_specification_id,
                'init_container_id' => $initContainer->id,
                'include_in_migration_job' => true,
                'position' => $position,
            ]);
        }

        $this->assertSame(
            ['open-the-tunnel', 'wait-for-db'],
            array_column($this->podSpec($deployment)['initContainers'], 'name')
        );
    }

    public function testNoInitContainersMeansNoneAreDeclared(): void {
        $deployment = $this->migratableDeployment();

        $this->assertArrayNotHasKey('initContainers', $this->podSpec($deployment));
    }

    /**
     * The migration runs under the deployment's service account when the specification
     * has RBAC turned on, so it gets the same cluster permissions the app does.
     */
    public function testServiceAccountIsUsedWhenTheSpecificationEnablesRbac(): void {
        $withRbac = $this->migratableDeployment([], ['enable_rbac' => true]);
        $without = $this->migratableDeployment([], ['enable_rbac' => false]);

        $this->assertSame($withRbac->name, $this->podSpec($withRbac)['serviceAccountName']);
        $this->assertArrayNotHasKey('serviceAccountName', $this->podSpec($without));
    }

    /**
     * A volume set on the specification is shared by every deployment of it, so the sub
     * path is compiled per deployment - the same arrangement the Deployment step uses, so
     * that a migration writes where the application will later read.
     */
    public function testASpecificationVolumeIsMountedUnderACompiledSubPath(): void {
        $deployment = $this->migratableDeployment();
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'mount_path' => '/shared',
            'sub_path' => '${deployment.name}',
        ]);

        $mount = $this->container($deployment)['volumeMounts'][0];

        $this->assertSame('/shared', $mount['mountPath']);
        $this->assertSame($deployment->name, $mount['subPath'], 'the placeholder should be filled in');
        $this->assertSame(
            ['claimName' => $deployment->name],
            $this->podSpec($deployment)['volumes'][0]['persistentVolumeClaim']
        );
    }

    /**
     * The security context comes from the image the migration runs, which is not always the
     * application's: a separate migration image has a user of its own, and it is that one
     * the job has to run as if it is to write to the volume at all.
     */
    public function testSecurityContextComesFromTheMigrationsOwnImage(): void {
        $migrationImage = Fixtures::containerImage([
            'url' => 'registry.example.org/migrator',
            'security_context_run_as_user' => '1500',
            'security_context_run_as_group' => '2500',
            'security_context_fs_group' => '3500',
            'security_context_allow_privilege_escalation' => false,
            'security_context_read_only_root_filesystem' => true,
        ]);
        $deployment = $this->migratableDeployment([], [
            'database_migration_container_image_id' => $migrationImage->id,
            'database_migration_container_image_tag_policy' => \ContainerImageTagPolicies::Static,
            'database_migration_container_image_tag_value' => 'v2',
        ], ['security_context_run_as_user' => '1000', 'security_context_fs_group' => '9999']);

        $security = $this->container($deployment)['securityContext'];

        $this->assertSame(1500, $security['runAsUser'], 'not the application image\'s 1000');
        $this->assertSame(2500, $security['runAsGroup']);
        $this->assertFalse($security['allowPrivilegeEscalation']);
        $this->assertTrue($security['readOnlyRootFilesystem']);
        $this->assertSame(3500, $this->podSpec($deployment)['securityContext']['fsGroup']);
    }

    public function testAnUnsetSecurityContextIsLeftOutRatherThanSentAsZero(): void {
        $deployment = $this->migratableDeployment([], [], [
            'security_context_run_as_user' => '',
            'security_context_run_as_group' => '',
            'security_context_fs_group' => '',
        ]);

        $security = $this->container($deployment)['securityContext'];

        $this->assertArrayNotHasKey('runAsUser', $security);
        $this->assertArrayNotHasKey('runAsGroup', $security);
        $this->assertArrayNotHasKey('securityContext', $this->podSpec($deployment));
    }

    /**
     * The migration pulls from the same private registry the application does. Without the
     * secret the job cannot pull at all, and the release stops before a single migration
     * has run.
     */
    public function testPullSecretIsReferencedWhenTheMigrationImageHasOne(): void {
        $deployment = $this->migratableDeployment([], [], ['pull_secret' => 'registry-credentials']);

        $this->assertSame(
            [['name' => 'registry-credentials']],
            $this->podSpec($deployment)['imagePullSecrets']
        );
    }

    public function testNoPullSecretMeansNoneIsReferenced(): void {
        $deployment = $this->migratableDeployment([], [], ['pull_secret' => '']);

        $this->assertArrayNotHasKey('imagePullSecrets', $this->podSpec($deployment));
    }

    /**
     * Inside the cluster kso is reached under its own service name, so a migration pod
     * calls back over the pod network and never leaves it. `DEV_REMOTE_BASE_URL` replaces
     * that with a tunnel while developing, and it is set in this container - so the
     * in-cluster form, which is the one production uses, only shows itself without it.
     */
    public function testTheCallbacksPointAtKsoInsideTheClusterWhenNoTunnelIsConfigured(): void {
        $deployment = $this->migratableDeployment();

        $script = $this->withoutTheDevelopmentTunnel(
            fn () => $this->container($deployment)['args'][1]
        );

        $host = KubeHelper::GetMyHostname() . '.' . KubeHelper::GetMyNamespace();
        $this->assertStringContainsString("$host/api/migration-jobs/\$(MIGRATION_JOB_ID)/started", $script);
        $this->assertStringContainsString("$host/api/migration-jobs/\$(MIGRATION_JOB_ID)/ended", $script);
    }

    /**
     * What the step answers when the UI asks what it is and what it can do.
     *
     * It reports no Kubernetes events of its own - the migration's progress is reported by
     * the job itself over http - and it redeploys for a version change, which is the one
     * thing that means there are new migrations to run.
     */
    public function testTheStepDescribesItselfAndWhatItReactsTo(): void {
        $step = new MigrationJobStep();

        $this->assertSame([
            'identifier' => DeploymentSteps::Migration,
            'level' => DeploymentStepLevels::Deployment,
            'name' => 'Migration Job',
            'hasPreviewCommand' => true,
            'hasStatusCommand' => true,
            'hasDeployCommand' => true,
            'hasKubernetesEvents' => false,
            'hasKubernetesStatus' => true,
            'hasTerminateCommand' => true,
        ], $step->toArray());

        $this->assertSame(
            DeploymentStepHelper::MigrationJob_Completed,
            $step->getSuccessStatus($this->migratableDeployment())
        );

        $this->assertSame([
            DeploymentStepTriggers::Deployment_Version_Updated,
            DeploymentStepTriggers::Deployment_ImagePullPolicy_Updated,
        ], $step->getTriggers());
    }

    /**
     * The checks that need nothing but the row itself. The namespace check after them asks
     * the cluster, so it is in the cluster suite.
     */
    public function testAMigrationMissingItsBasicsIsRefusedBeforeAnythingIsSent(): void {
        $step = new MigrationJobStep();

        $this->assertSame('Missing name', $step->validateDeployCommand(
            $this->migratableDeployment(['name' => ''])
        ));
        $this->assertSame('Missing namespace', $step->validateDeployCommand(
            $this->migratableDeployment(['namespace' => ''])
        ));
        $this->assertSame('Missing image', $step->validateDeployCommand(
            $this->migratableDeployment(['image' => ''])
        ));
        $this->assertSame('Missing version', $step->validateDeployCommand(
            $this->migratableDeployment(['image' => 'nginx', 'version' => ''])
        ));
    }

    /**
     * A database service that was deleted after the deployment was pointed at it. The id is
     * still on the row, so the only way to notice is to go and look.
     */
    public function testAMigrationPointedAtADatabaseServiceThatIsGoneIsRefused(): void {
        $deployment = $this->migratableDeployment([
            'image' => 'nginx',
            'database_service_id' => 99999,
        ]);

        $this->assertSame(
            'Database service no longer exists',
            (new MigrationJobStep())->validateDeployCommand($deployment)
        );
    }

    /**
     * Deploying writes the row the migration reports back against, and puts its id into the
     * container's environment - which is what turns `$(MIGRATION_JOB_ID)` in the command
     * into a number. Without the row there is nothing for the two callbacks to address.
     */
    public function testDeployingWritesTheRowTheCallbacksReportAgainst(): void {
        $deployment = $this->migratableDeployment([], ['database_migration_command' => 'php spark migrate']);
        $resource = $this->jobThatIsNotThereYet($deployment);

        $this->stepWhoseResourceIs($resource)->startDeployCommand($deployment);

        $row = $this->lastMigrationJob($deployment);
        $this->assertSame(\MigrationJobStatusTypes::Deploying, $row->status);
        $this->assertSame('php spark migrate', $row->command);
        $this->assertStringContainsString(':1.2.3', $row->image, 'the row records what was actually run');

        $this->assertSame(1, $resource->created, 'the job is created, not updated');
        $this->assertContains(
            ['name' => 'MIGRATION_JOB_ID', 'value' => (string) $row->id],
            $resource->getTemplate()->getContainers()[0]->getAttribute('env')
        );
    }

    /**
     * Replacing a migration means deleting the old job and waiting for it to go, and the
     * job disappears underneath that wait - that is the point of it. The read that finds it
     * gone throws, and the step has to carry on and create the new one anyway; giving up
     * here would leave a release with no migration and no error.
     */
    public function testAJobThatVanishesWhileBeingReplacedDoesNotStopTheNewOne(): void {
        $deployment = $this->migratableDeployment();
        $resource = $this->jobThatVanishesWhenRead($deployment);

        $this->stepWhoseResourceIs($resource)->startDeployCommand($deployment);

        $this->assertSame(1, $resource->created);
        $this->assertSame(
            \MigrationJobStatusTypes::Deploying,
            $this->lastMigrationJob($deployment)->status
        );
    }

    /**
     * A Job's spec is immutable, so replacing a migration means deleting the old one and
     * *waiting for it to actually go*. The api server accepts the delete immediately and
     * removes the job some time after, so a create issued straight away is refused with a
     * 409 on a name that is still taken - and the release then has no migration at all.
     *
     * The wait is a poll, so what this asserts is that the step polls until the job is
     * gone rather than reading once and pressing on. Deleting and creating are both real
     * here; only the reads are answered from memory, because the window cannot be held
     * open against a real cluster.
     */
    public function testReplacingAMigrationWaitsForTheOldJobToActuallyGo(): void {
        $deployment = $this->migratableDeployment();
        $resource = $this->jobThatTakesTwoReadsToDisappear($deployment);

        $this->stepWhoseResourceIs($resource)->startDeployCommand($deployment);

        $this->assertSame(1, $resource->deleted, 'the old job is deleted first');
        $this->assertSame(
            0,
            $resource->stillThereFor,
            'the step created the new job while the old one was still there'
        );
        $this->assertSame(1, $resource->created);
    }

    // <editor-fold desc="Fixtures and reading">

    /**
     * @param array<string, mixed> $deployment
     * @param array<string, mixed> $specification
     * @param array<string, mixed> $image
     */
    private function migratableDeployment(
        array $deployment = [],
        array $specification = [],
        array $image = []
    ): Deployment {
        return Fixtures::deployableDeployment(
            $deployment,
            array_merge([
                'enable_database' => true,
                'database_migration_command' => 'php spark migrate',
            ], $specification),
            $image
        );
    }

    /**
     * A step whose resource is the double rather than the real one, so `startDeployCommand`
     * can be run without a cluster. Everything it does to the resource - the delete, the
     * environment variable, the create - happens for real; only the four calls that would
     * leave the process are answered from memory.
     */
    private function stepWhoseResourceIs(K8sJob $resource): MigrationJobStep {
        return new class ($resource) extends MigrationJobStep {
            public function __construct(private readonly K8sJob $resource) {
            }

            protected function getResource(Deployment $deployment, bool $auth = false): K8sJob {
                // As the real one leaves it for a deployment without secret variables.
                $this->workloadSecret = \App\Libraries\Kubernetes\WorkloadSecret::For($deployment->name, 'job');

                return $this->resource;
            }
        };
    }

    /**
     * The ordinary first deploy: nothing of this name in the cluster yet. The manifest is
     * the real one the step builds, so the template the step reaches into is real too.
     */
    private function jobThatIsNotThereYet(Deployment $deployment): K8sJob {
        return new class (null, $this->realManifest($deployment)) extends K8sJob {
            public int $created = 0;

            public function exists(array $query = ['pretty' => 1]): bool {
                return false;
            }

            public function create(array $query = ['pretty' => 1]) {
                $this->created++;

                return $this;
            }
        };
    }

    /**
     * A job that is there when asked, and gone by the time it is read - the window every
     * replace goes through, because the delete before it is what closes it.
     */
    private function jobThatVanishesWhenRead(Deployment $deployment): K8sJob {
        return new class (null, $this->realManifest($deployment)) extends K8sJob {
            public int $created = 0;

            public function exists(array $query = ['pretty' => 1]): bool {
                return true;
            }

            public function get(array $query = ['pretty' => 1]) {
                throw new KubernetesAPIException('the job is gone', KubeHelper::NotFoundCode);
            }

            public function create(array $query = ['pretty' => 1]) {
                $this->created++;

                return $this;
            }
        };
    }

    /**
     * A job that is still there for the first two reads after the delete and gone on the
     * third - the ordinary case, because deleting is asynchronous. It counts down the reads
     * it still answers "there" for, so a step that stops waiting early leaves that count
     * above zero. Two rather than one, because one would be consumed by the first pass
     * through the loop whether or not the step is willing to go round again.
     */
    private function jobThatTakesTwoReadsToDisappear(Deployment $deployment): K8sJob {
        return new class (null, $this->realManifest($deployment)) extends K8sJob {
            public int $created = 0;
            public int $deleted = 0;
            public int $stillThereFor = 2;  // reads remaining before it reports itself gone

            public function exists(array $query = ['pretty' => 1]): bool {
                return $this->deleted === 0 || $this->stillThereFor > 0;
            }

            public function get(array $query = ['pretty' => 1]) {
                if ($this->deleted > 0 && $this->stillThereFor > 0) {
                    $this->stillThereFor--;
                }

                return $this;
            }

            public function delete(array $query = ['pretty' => 1], $gracePeriod = null, string $propagationPolicy = 'Foreground'): bool {
                $this->deleted++;

                return true;
            }

            public function create(array $query = ['pretty' => 1]) {
                $this->created++;

                return $this;
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function realManifest(Deployment $deployment): array {
        return $this->build($deployment);
    }

    private function lastMigrationJob(Deployment $deployment): MigrationJob {
        /** @var MigrationJob $jobs */
        $jobs = (new MigrationJobModel())
            ->where('deployment_id', $deployment->id)
            ->orderBy('id', 'desc')
            ->find();

        $this->assertTrue($jobs->exists(), 'no migration job row was written');

        return $jobs;
    }

    /**
     * Run the body with `DEV_REMOTE_BASE_URL` out of the way, and put it back afterwards -
     * including when the body throws. Anything a test writes to the process environment is
     * written for every test after it in the same process.
     *
     * @template T
     * @param callable(): T $body
     * @return T
     */
    private function withoutTheDevelopmentTunnel(callable $body): mixed {
        $original = getenv('DEV_REMOTE_BASE_URL');
        putenv('DEV_REMOTE_BASE_URL=');

        try {
            return $body();
        } finally {
            putenv($original === false ? 'DEV_REMOTE_BASE_URL' : "DEV_REMOTE_BASE_URL=$original");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Deployment $deployment): array {
        return $this->manifest(MigrationJobStep::class, $deployment);
    }

    /**
     * @return array<string, mixed>
     */
    private function podSpec(Deployment $deployment): array {
        return $this->build($deployment)['spec']['template']['spec'];
    }

    /**
     * @return array<string, mixed>
     */
    private function container(Deployment $deployment): array {
        return $this->podSpec($deployment)['containers'][0];
    }

    /**
     * @return array<string, string>
     */
    private function environment(Deployment $deployment): array {
        $environment = [];
        foreach ($this->container($deployment)['env'] ?? [] as $entry) {
            $environment[$entry['name']] = $entry['value'] ?? null;
        }

        return $environment;
    }

    // </editor-fold>

}
