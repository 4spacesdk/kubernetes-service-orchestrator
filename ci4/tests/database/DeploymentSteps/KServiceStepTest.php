<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use App\Libraries\DeploymentSteps\KServiceStep;
use App\ManifestTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The KNative Service, the alternative to a plain Deployment plus Service.
 *
 * It carries everything the Deployment manifest does - image, environment, limits,
 * security context, volumes - plus the autoscaling annotations that decide whether the
 * workload scales to zero. Those annotations are strings in a map, so a wrong key is
 * accepted by the cluster and then ignored, which is the failure mode worth guarding.
 */
class KServiceStepTest extends ManifestTestCase {

    /**
     * The eight triggers are what re-apply a revision without a full deploy, and they are
     * the whole list of things that change a running Knative workload. One missing means an
     * edit that is saved, shown as saved, and never reaches the cluster.
     */
    public function testTheStepIsWiredInAtTheDeploymentLevel(): void {
        $step = new KServiceStep();
        $deployment = $this->knativeDeployment();

        $this->assertSame(DeploymentSteps::KService, $step->getIdentifier());
        $this->assertSame(DeploymentStepLevels::Deployment, $step->getLevel());
        $this->assertSame('KService', $step->getName());
        $this->assertSame(DeploymentStepHelper::KService_Found, $step->getSuccessStatus($deployment));

        $this->assertSame([
            DeploymentStepTriggers::Deployment_Volume_Updated,
            DeploymentStepTriggers::Deployment_Environment_Updated,
            DeploymentStepTriggers::Deployment_EnvironmentVariable_Updated,
            DeploymentStepTriggers::Deployment_ResourceManagement_Updated,
            DeploymentStepTriggers::Deployment_UpdateManagement_Updated,
            DeploymentStepTriggers::Deployment_Version_Updated,
            DeploymentStepTriggers::Deployment_KNativeMinScale_Updated,
            DeploymentStepTriggers::Deployment_ImagePullPolicy_Updated,
        ], $step->getTriggers());
    }

    /**
     * Unlike the plain Service, this step does read the cluster back: a Knative revision
     * that fails to come up says why in its events and its status, and both are what the
     * UI puts in front of an operator.
     */
    public function testTheStepOffersEveryCommandIncludingTheClusterReads(): void {
        $step = new KServiceStep();

        $this->assertTrue($step->hasPreviewCommand());
        $this->assertTrue($step->hasStatusCommand());
        $this->assertTrue($step->hasDeployCommand());
        $this->assertTrue($step->hasTerminateCommand());
        $this->assertTrue($step->hasKubernetesEvents());
        $this->assertTrue($step->hasKubernetesStatus());
    }

    /**
     * The four checks that come before the step asks the cluster anything. Each names the
     * column that is empty, because the deploy stops here and the message is all the user
     * is shown.
     */
    #[DataProvider('theColumnsADeployCannotDoWithout')]
    public function testDeployIsRefusedWhenAColumnIsEmpty(string $column, mixed $empty, string $expected): void {
        $deployment = $this->knativeDeployment(array_merge(
            ['image' => 'registry.example.org/test/app'],
            [$column => $empty]
        ));

        $this->assertSame($expected, (new KServiceStep())->validateDeployCommand($deployment));
    }

    /**
     * @return array<string, array{0: string, 1: mixed, 2: string}>
     */
    public static function theColumnsADeployCannotDoWithout(): array {
        return [
            'name' => ['name', '', 'Missing name'],
            'namespace' => ['namespace', '', 'Missing namespace'],
            'image' => ['image', '', 'Missing image'],
            // Not empty but unknown: the column is free text, and only the list in
            // `Environments` decides what a revision may be told it is running as.
            'environment' => ['environment', 'somewhere-else', 'Missing environment'],
        ];
    }

    public function testServiceIsNamedAndNamespacedAfterTheDeployment(): void {
        $deployment = $this->knativeDeployment();

        $manifest = $this->build($deployment);

        $this->assertSame($deployment->name, $manifest['metadata']['name']);
        $this->assertSame($deployment->namespace, $manifest['metadata']['namespace']);
        $this->assertSame('4spaces.kso', $manifest['metadata']['annotations']['app.kubernetes.io/managed-by']);
    }

    public function testImageIsTheSpecificationsImageAtTheDeployedVersion(): void {
        $deployment = $this->knativeDeployment(
            ['version' => '4.5.6'],
            [],
            ['url' => 'registry.example.org/team/backend']
        );

        $this->assertSame('registry.example.org/team/backend:4.5.6', $this->container($deployment)['image']);
    }

    public function testPodIsLabelledAsTheApp(): void {
        $deployment = $this->knativeDeployment();

        $this->assertSame(
            ['app' => $deployment->name, 'role' => 'app'],
            $this->template($deployment)['metadata']['labels']
        );
    }

    /**
     * The ports come from the http proxy routes rather than from service ports, because a
     * KNative Service has no Service of its own to take them from.
     */
    public function testContainerPortsComeFromTheHttpProxyRoutes(): void {
        $deployment = $this->knativeDeployment();
        Fixtures::httpProxyRoute([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'port' => 8080,
        ]);

        $ports = $this->container($deployment)['ports'];

        $this->assertSame([['containerPort' => 8080]], $ports);
        $this->assertIsInt($ports[0]['containerPort']);
    }

    public function testEnvironmentAndBaseUrlAreAlwaysPassedToTheContainer(): void {
        $deployment = $this->knativeDeployment(['environment' => 'staging']);

        $environment = $this->environment($deployment);

        $this->assertSame('staging', $environment['ENVIRONMENT']);
        $this->assertStringContainsString('tenant.test.example.org', $environment['BASE_URL']);
    }

    public function testDeploymentEnvironmentVariableOverridesTheSpecification(): void {
        $deployment = $this->knativeDeployment();

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
        $deployment = $this->knativeDeployment([
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

    public function testSecurityContextPullSecretAndFsGroupComeFromTheContainerImage(): void {
        $deployment = $this->knativeDeployment([], [], [
            'security_context_run_as_user' => '1000',
            'security_context_run_as_group' => '2000',
            'security_context_fs_group' => '3000',
            'security_context_read_only_root_filesystem' => true,
            'pull_secret' => 'registry-credentials',
        ]);

        $security = $this->container($deployment)['securityContext'];
        $podSpec = $this->template($deployment)['spec'];

        $this->assertSame(1000, $security['runAsUser']);
        $this->assertSame(2000, $security['runAsGroup']);
        $this->assertTrue($security['readOnlyRootFilesystem']);
        $this->assertSame(3000, $podSpec['securityContext']['fsGroup']);
        $this->assertSame([['name' => 'registry-credentials']], $podSpec['imagePullSecrets']);
    }

    /**
     * Scale to zero is the point of running on KNative, so an unscheduled deployment says
     * so explicitly rather than leaving KNative to its own default.
     */
    public function testMinScaleIsZeroWithoutASchedule(): void {
        $deployment = $this->knativeDeployment(['knative_scheduled_minscale_is_enabled' => false]);

        $this->assertSame('0', $this->podAnnotations($deployment)['autoscaling.knative.dev/minScale']);
    }

    /**
     * The soft limit is a scaling target - more requests are still accepted. The hard one
     * is a ceiling KNative enforces, and it lives in the spec rather than an annotation.
     *
     * The types differ on purpose: an annotation value has to be a string, a spec field
     * has to be a number, and Kubernetes rejects either one written as the other.
     */
    public function testConcurrencyLimitsLandInTheirOwnPlacesWithTheRightTypes(): void {
        $soft = $this->knativeDeployment(['knative_concurrency_limit_soft' => 50]);
        $hard = $this->knativeDeployment(['knative_concurrency_limit_hard' => 10]);

        $this->assertSame('50', $this->podAnnotations($soft)['autoscaling.knative.dev/target']);
        $this->assertSame(10, $this->template($hard)['spec']['containerConcurrency']);
    }

    public function testNoConcurrencyLimitsMeansKnativeDecides(): void {
        $deployment = $this->knativeDeployment([
            'knative_concurrency_limit_soft' => 0,
            'knative_concurrency_limit_hard' => 0,
        ]);

        $this->assertArrayNotHasKey('autoscaling.knative.dev/target', $this->podAnnotations($deployment));
        $this->assertArrayNotHasKey('containerConcurrency', $this->template($deployment)['spec']);
    }

    /**
     * Annotations are levelled: one set lands on the Service, another on the revision's
     * pods. Getting them the wrong way round is silent - both are accepted, and only one
     * of them does anything.
     */
    public function testAnnotationsLandOnTheLevelTheyAreDeclaredFor(): void {
        $deployment = $this->knativeDeployment();
        Fixtures::deploymentAnnotation([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'level' => \DeploymentAnnotationLevels::Deployment,
            'name' => 'example.org/on-service',
            'value' => 'yes',
        ]);
        Fixtures::deploymentAnnotation([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'level' => \DeploymentAnnotationLevels::Pod,
            'name' => 'example.org/on-pod',
            'value' => 'yes',
        ]);

        $manifest = $this->build($deployment);

        $this->assertSame('yes', $manifest['metadata']['annotations']['example.org/on-service']);
        $this->assertArrayNotHasKey('example.org/on-pod', $manifest['metadata']['annotations']);

        $this->assertSame('yes', $this->podAnnotations($deployment)['example.org/on-pod']);
        $this->assertArrayNotHasKey('example.org/on-service', $this->podAnnotations($deployment));
    }

    public function testVolumesAreOnlyMountedWhenTheSpecificationEnablesThem(): void {
        $deployment = $this->knativeDeployment([], ['enable_volumes' => true]);
        Fixtures::deploymentVolume([
            'deployment_id' => $deployment->id,
            'mount_path' => '/var/www/storage',
            'sub_path' => 'data',
        ]);

        $mount = $this->container($deployment)['volumeMounts'][0];
        $this->assertSame('/var/www/storage', $mount['mountPath']);
        $this->assertSame('data', $mount['subPath']);
        $this->assertFalse($mount['readOnly']);

        $this->assertSame(
            ['claimName' => $deployment->name],
            $this->template($deployment)['spec']['volumes'][0]['persistentVolumeClaim']
        );
    }

    public function testVolumesAreLeftOutWhenTheSpecificationDoesNotEnableThem(): void {
        $deployment = $this->knativeDeployment([], ['enable_volumes' => false]);
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id]);

        $this->assertArrayNotHasKey('volumeMounts', $this->container($deployment));
        $this->assertArrayNotHasKey('volumes', $this->template($deployment)['spec']);
    }

    /**
     * Unlike the migration job, which only takes the ones marked for it, a KNative
     * revision runs every init container the specification declares.
     */
    public function testEveryInitContainerIsIncluded(): void {
        $deployment = $this->knativeDeployment();
        $image = Fixtures::containerImage(['url' => 'registry.example.org/init']);

        foreach ([['wait-for-db', true], ['warm-cache', false]] as [$name, $forMigration]) {
            $initContainer = Fixtures::initContainer(['name' => $name, 'container_image_id' => $image->id]);
            Fixtures::specificationInitContainer([
                'deployment_specification_id' => $deployment->deployment_specification_id,
                'init_container_id' => $initContainer->id,
                'include_in_migration_job' => $forMigration,
            ]);
        }

        $initContainers = $this->template($deployment)['spec']['initContainers'];

        $this->assertCount(2, $initContainers);
        $this->assertSame(['wait-for-db', 'warm-cache'], array_column($initContainers, 'name'));
    }

    public function testServiceAccountIsUsedWhenTheSpecificationEnablesRbac(): void {
        $withRbac = $this->knativeDeployment([], ['enable_rbac' => true]);
        $without = $this->knativeDeployment([], ['enable_rbac' => false]);

        $this->assertSame($withRbac->name, $this->template($withRbac)['spec']['serviceAccountName']);
        $this->assertArrayNotHasKey('serviceAccountName', $this->template($without)['spec']);
    }

    // <editor-fold desc="Fixtures and reading">

    /**
     * @param array<string, mixed> $deployment
     * @param array<string, mixed> $specification
     * @param array<string, mixed> $image
     */
    private function knativeDeployment(
        array $deployment = [],
        array $specification = [],
        array $image = []
    ): Deployment {
        return Fixtures::deployableDeployment(
            $deployment,
            array_merge(['workload_type' => \WorkloadTypes::KNativeService], $specification),
            $image
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Deployment $deployment): array {
        return $this->manifest(KServiceStep::class, $deployment);
    }

    /**
     * The revision template, which is where everything about the running workload lives.
     *
     * @return array<string, mixed>
     */
    private function template(Deployment $deployment): array {
        return $this->build($deployment)['spec']['template'];
    }

    /**
     * @return array<string, mixed>
     */
    private function container(Deployment $deployment): array {
        return $this->template($deployment)['spec']['containers'][0];
    }

    /**
     * @return array<string, mixed>
     */
    private function podAnnotations(Deployment $deployment): array {
        return $this->template($deployment)['metadata']['annotations'];
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
