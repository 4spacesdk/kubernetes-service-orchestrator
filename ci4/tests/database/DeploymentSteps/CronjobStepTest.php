<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\CronjobStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\ManifestTestCase;

/**
 * The CronJobs a deployment runs on a schedule.
 *
 * Cron jobs are the steps nobody watches. A deployment goes up, looks healthy, and a job
 * that never fires - or fires with the wrong image - is only noticed when the work it was
 * supposed to do turns out not to have happened.
 *
 * A definition is shared: it hangs off a specification, off a single deployment, or both,
 * and only a junction row says which.
 */
class CronjobStepTest extends ManifestTestCase {

    /**
     * No triggers: a changed schedule reaches the cluster on the next deploy and not
     * before. The step does read events and status back, which is the only way anyone sees
     * that a job has been failing every night.
     */
    public function testTheStepIsWiredInAtTheDeploymentLevel(): void {
        $step = new CronjobStep();
        $deployment = $this->deploymentWithCronJob();

        $this->assertSame(DeploymentSteps::Cronjob, $step->getIdentifier());
        $this->assertSame(DeploymentStepLevels::Deployment, $step->getLevel());
        $this->assertSame('Cron Job', $step->getName());
        $this->assertSame([], $step->getTriggers());
        $this->assertSame(DeploymentStepHelper::Cronjob_Found, $step->getSuccessStatus($deployment));

        $this->assertTrue($step->hasPreviewCommand());
        $this->assertTrue($step->hasStatusCommand());
        $this->assertTrue($step->hasDeployCommand());
        $this->assertTrue($step->hasTerminateCommand());
        $this->assertTrue($step->hasKubernetesEvents());
        $this->assertTrue($step->hasKubernetesStatus());
    }

    /**
     * The two checks that come before the step looks at the cluster. A cron job is named
     * after the deployment, so an empty name would build a resource named after nothing.
     */
    public function testDeployIsRefusedWithoutANameOrANamespace(): void {
        $step = new CronjobStep();

        $this->assertSame(
            'Missing name',
            $step->validateDeployCommand($this->deploymentWithCronJob([], [], ['name' => '']))
        );
        $this->assertSame(
            'Missing namespace',
            $step->validateDeployCommand($this->deploymentWithCronJob([], [], ['namespace' => '']))
        );
    }

    public function testCronJobIsNamedAfterBothTheDeploymentAndItself(): void {
        $deployment = $this->deploymentWithCronJob(['name' => 'cleanup']);

        $manifest = $this->build($deployment)[0];

        $this->assertSame('test-deployment-cleanup', $manifest['metadata']['name']);
        $this->assertSame($deployment->namespace, $manifest['metadata']['namespace']);
    }

    /**
     * A definition without a name of its own takes the deployment's, which is also what
     * the Deployment and Service are called. Only one cron job can get away with that.
     */
    public function testUnnamedCronJobTakesTheDeploymentsNameAlone(): void {
        $deployment = $this->deploymentWithCronJob(['name' => '']);

        $this->assertSame($deployment->name, $this->build($deployment)[0]['metadata']['name']);
    }

    public function testScheduleIsCarriedOver(): void {
        $deployment = $this->deploymentWithCronJob(['schedule' => '*/15 * * * *']);

        $this->assertSame('*/15 * * * *', $this->build($deployment)[0]['spec']['schedule']);
    }

    /**
     * The schedule goes through a CronExpression on the way out, so a malformed one is
     * caught here rather than by the cluster. It throws, which is the loud failure - but
     * it happens while building the manifest, so it takes the whole deploy with it.
     */
    public function testMalformedScheduleIsRejectedWhileBuilding(): void {
        $deployment = $this->deploymentWithCronJob(['schedule' => 'every other tuesday']);

        $this->expectException(\InvalidArgumentException::class);

        $this->build($deployment);
    }

    public function testMatchDeploymentTagPolicyUsesTheDeploymentsVersion(): void {
        $deployment = $this->deploymentWithCronJob(
            ['container_image_tag_policy' => \ContainerImageTagPolicies::MatchDeployment],
            ['url' => 'registry.example.org/cron', 'default_tag' => 'stable']
        );

        $this->assertSame('registry.example.org/cron:1.2.3', $this->container($deployment)['image']);
    }

    public function testStaticTagPolicyUsesItsOwnValue(): void {
        $deployment = $this->deploymentWithCronJob(
            [
                'container_image_tag_policy' => \ContainerImageTagPolicies::Static,
                'container_image_tag_value' => 'pinned',
            ],
            ['url' => 'registry.example.org/cron', 'default_tag' => 'stable']
        );

        $this->assertSame('registry.example.org/cron:pinned', $this->container($deployment)['image']);
    }

    public function testDefaultTagPolicyUsesTheImagesDefaultTag(): void {
        $deployment = $this->deploymentWithCronJob(
            ['container_image_tag_policy' => \ContainerImageTagPolicies::Default],
            ['url' => 'registry.example.org/cron', 'default_tag' => 'stable']
        );

        $this->assertSame('registry.example.org/cron:stable', $this->container($deployment)['image']);
    }

    /**
     * `Forbid` is what keeps a long job from being started again on top of itself, and it
     * is the setting a slow nightly job depends on.
     */
    public function testConcurrencyAndRestartPolicyAreCarriedOver(): void {
        $deployment = $this->deploymentWithCronJob([
            'concurrency_policy' => 'Forbid',
            'restart_policy' => 'OnFailure',
        ]);

        $this->assertSame('Forbid', $this->build($deployment)[0]['spec']['concurrencyPolicy']);
        $this->assertSame('OnFailure', $this->podSpec($deployment)['restartPolicy']);
    }

    public function testHistoryLimitsAreCarriedOverAsNumbers(): void {
        $deployment = $this->deploymentWithCronJob([
            'successful_jobs_history_limit' => 3,
            'failed_jobs_history_limit' => 1,
        ]);

        $spec = $this->build($deployment)[0]['spec'];

        $this->assertSame(3, $spec['successfulJobsHistoryLimit']);
        $this->assertSame(1, $spec['failedJobsHistoryLimit']);
    }

    public function testCommandAndArgsHaveVariablesApplied(): void {
        $deployment = $this->deploymentWithCronJob([
            'command' => 'php spark ${namespace}',
            'args' => json_encode(['cleanup', '${deployment.name}']),
        ]);

        $container = $this->container($deployment);

        $this->assertSame(["php spark {$deployment->namespace}"], $container['command']);
        $this->assertSame(['cleanup', $deployment->name], $container['args']);
    }

    /**
     * A cron job gets the deployment's environment only if its definition says so. The
     * default is not to, so a job that needs the database connection has to ask.
     */
    public function testEnvironmentIsOnlyInheritedWhenTheDefinitionAsksForIt(): void {
        $without = $this->deploymentWithCronJob(['include_deployment_environment_variables' => false]);
        Fixtures::specificationEnvironmentVariable([
            'deployment_specification_id' => $without->deployment_specification_id,
            'name' => 'FROM_SPEC',
            'value' => 'yes',
        ]);

        $this->assertArrayNotHasKey('env', $this->container($without));

        $with = $this->deploymentWithCronJob(['include_deployment_environment_variables' => true]);
        Fixtures::specificationEnvironmentVariable([
            'deployment_specification_id' => $with->deployment_specification_id,
            'name' => 'FROM_SPEC',
            'value' => 'yes',
        ]);

        $this->assertSame('yes', $this->environment($with)['FROM_SPEC']);
    }

    /**
     * Same precedence as on the Deployment: what is set on this one deployment wins over
     * what the specification hands down.
     */
    public function testDeploymentEnvironmentVariableOverridesTheSpecification(): void {
        $deployment = $this->deploymentWithCronJob(['include_deployment_environment_variables' => true]);

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

    public function testResourceLimitsAreCarriedOverWithTheirUnits(): void {
        $deployment = $this->deploymentWithCronJob([
            'cpu_request' => 100,
            'cpu_limit' => 500,
            'memory_request' => 128,
            'memory_limit' => 512,
        ]);

        $resources = $this->container($deployment)['resources'];

        $this->assertSame('100m', $resources['requests']['cpu']);
        $this->assertSame('500m', $resources['limits']['cpu']);
        $this->assertSame('128Mi', $resources['requests']['memory']);
        $this->assertSame('512Mi', $resources['limits']['memory']);
    }

    public function testSecurityContextAndPullSecretComeFromTheContainerImage(): void {
        $deployment = $this->deploymentWithCronJob([], [
            'security_context_run_as_user' => '1000',
            'security_context_run_as_group' => '2000',
            'security_context_fs_group' => '3000',
            'security_context_read_only_root_filesystem' => true,
            'pull_secret' => 'registry-credentials',
        ]);

        $security = $this->container($deployment)['securityContext'];
        $podSpec = $this->podSpec($deployment);

        $this->assertSame(1000, $security['runAsUser']);
        $this->assertSame(2000, $security['runAsGroup']);
        $this->assertTrue($security['readOnlyRootFilesystem']);
        $this->assertSame(3000, $podSpec['securityContext']['fsGroup']);
        $this->assertSame([['name' => 'registry-credentials']], $podSpec['imagePullSecrets']);
    }

    /**
     * The specification's cron jobs come first, then the deployment's own. Order is what
     * the position columns are for, and it decides which name collides with which.
     */
    public function testSpecificationAndDeploymentCronJobsAreBothGenerated(): void {
        $deployment = $this->deploymentWithCronJob(['name' => 'from-specification']);
        $image = Fixtures::containerImage(['url' => 'registry.example.org/cron']);
        $own = Fixtures::cronJob(['name' => 'from-deployment', 'container_image_id' => $image->id]);
        Fixtures::deploymentCronJob([
            'deployment_id' => $deployment->id,
            'k8s_cron_job_id' => $own->id,
        ]);

        $names = array_map(
            static fn (array $manifest) => $manifest['metadata']['name'],
            $this->build($deployment)
        );

        $this->assertSame(
            ['test-deployment-from-specification', 'test-deployment-from-deployment'],
            $names
        );
    }

    public function testNoCronJobsGeneratesNothing(): void {
        $deployment = Fixtures::deployableDeployment();

        $this->assertCount(0, $this->build($deployment));
    }

    // <editor-fold desc="Fixtures and reading">

    /**
     * A deployment with one cron job hanging off its specification.
     *
     * @param array<string, mixed> $cronJob overrides for the cron job definition
     * @param array<string, mixed> $image overrides for the image the cron job runs
     * @param array<string, mixed> $deploymentOverrides overrides for the deployment itself
     */
    private function deploymentWithCronJob(array $cronJob = [], array $image = [], array $deploymentOverrides = []): Deployment {
        $deployment = Fixtures::deployableDeployment($deploymentOverrides);
        $containerImage = Fixtures::containerImage(array_merge(
            ['url' => 'registry.example.org/cron', 'default_tag' => 'stable'],
            $image
        ));
        $definition = Fixtures::cronJob(array_merge(
            ['container_image_id' => $containerImage->id],
            $cronJob
        ));
        Fixtures::specificationCronJob([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'k8s_cron_job_id' => $definition->id,
        ]);

        return $deployment;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function build(Deployment $deployment): array {
        return $this->manifests(CronjobStep::class, $deployment);
    }

    /**
     * @return array<string, mixed>
     */
    private function podSpec(Deployment $deployment): array {
        return $this->build($deployment)[0]['spec']['jobTemplate']['spec']['template']['spec'];
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
