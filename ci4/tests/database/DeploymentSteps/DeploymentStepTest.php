<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use App\ManifestTestCase;

/**
 * The Kubernetes Deployment every workspace runs on.
 *
 * This is the manifest that carries the most: the image and its tag, the environment the
 * application reads, resource limits, the security context, volumes and the pull secret.
 * A mistake here reaches every workspace at once, and until now nothing checked it short
 * of deploying and looking.
 */
class DeploymentStepTest extends ManifestTestCase {

    public function testManifestIsNamedAndNamespacedAfterTheDeployment(): void {
        $deployment = Fixtures::deployableDeployment();

        $manifest = $this->build($deployment);

        $this->assertSame($deployment->name, $manifest['metadata']['name']);
        $this->assertSame($deployment->namespace, $manifest['metadata']['namespace']);
        $this->assertSame('kso', $manifest['metadata']['annotations']['app.kubernetes.io/managed-by']);
    }

    public function testImageIsTheSpecificationsImageAtTheDeploymentsVersion(): void {
        $deployment = Fixtures::deployableDeployment(
            ['version' => '4.5.6'],
            [],
            ['url' => 'registry.example.org/team/backend']
        );

        $this->assertSame(
            'registry.example.org/team/backend:4.5.6',
            $this->container($deployment)['image']
        );
    }

    public function testImagePullPolicyComesFromTheDeployment(): void {
        $deployment = Fixtures::deployableDeployment(['image_pull_policy' => \ImagePullPolicies::Always]);

        $this->assertSame(\ImagePullPolicies::Always, $this->container($deployment)['imagePullPolicy']);
    }

    /**
     * The one port the container declares, and the one every Service built by kso points
     * its `targetPort` at. They are set in two different places and nothing lines them up,
     * so a container that declared anything else would be a Service with no endpoints -
     * which looks, from the outside, exactly like an application that is down.
     */
    public function testTheContainerDeclaresThePortServicesAreTargetedAt(): void {
        $deployment = Fixtures::deployableDeployment();

        $this->assertSame(
            [['name' => null, 'protocol' => 'TCP', 'containerPort' => 80]],
            $this->container($deployment)['ports']
        );
    }

    /**
     * Two variables the application can always count on, whatever else is configured.
     */
    public function testEnvironmentAndBaseUrlAreAlwaysPassedToTheContainer(): void {
        $deployment = Fixtures::deployableDeployment(['environment' => 'staging']);

        $env = $this->environment($deployment);

        $this->assertSame('staging', $env['ENVIRONMENT']);
        $this->assertArrayHasKey('BASE_URL', $env);
        $this->assertStringContainsString('tenant.test.example.org', $env['BASE_URL']);
    }

    /**
     * A variable set on the deployment itself wins over the one inherited from the
     * specification. That is the whole point of being able to set one per deployment.
     */
    public function testDeploymentEnvironmentVariableOverridesTheSpecification(): void {
        $deployment = Fixtures::deployableDeployment();

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
        $deployment = Fixtures::deployableDeployment([
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

    public function testNoResourceLimitsMeansKubernetesDecides(): void {
        $deployment = Fixtures::deployableDeployment([
            'cpu_request' => null,
            'cpu_limit' => null,
            'memory_request' => null,
            'memory_limit' => null,
        ]);

        $container = $this->container($deployment);

        $this->assertTrue(
            empty($container['resources']),
            'a deployment without limits should not pin any'
        );
    }

    public function testSecurityContextComesFromTheContainerImage(): void {
        $deployment = Fixtures::deployableDeployment([], [], [
            'security_context_run_as_user' => '1000',
            'security_context_run_as_group' => '2000',
            'security_context_allow_privilege_escalation' => false,
            'security_context_read_only_root_filesystem' => true,
        ]);

        $security = $this->container($deployment)['securityContext'];

        $this->assertSame(1000, $security['runAsUser']);
        $this->assertSame(2000, $security['runAsGroup']);
        $this->assertFalse($security['allowPrivilegeEscalation']);
        $this->assertTrue($security['readOnlyRootFilesystem']);
    }

    /**
     * An unset user means "whatever the image says", so no runAsUser is written at all.
     * Writing 0 instead would silently run the container as root.
     */
    public function testUnsetRunAsUserIsLeftOutRatherThanSentAsZero(): void {
        $deployment = Fixtures::deployableDeployment([], [], [
            'security_context_run_as_user' => '',
            'security_context_run_as_group' => '',
        ]);

        $security = $this->container($deployment)['securityContext'];

        $this->assertArrayNotHasKey('runAsUser', $security);
        $this->assertArrayNotHasKey('runAsGroup', $security);
    }

    public function testPullSecretIsReferencedWhenTheImageHasOne(): void {
        $deployment = Fixtures::deployableDeployment([], [], ['pull_secret' => 'registry-credentials']);

        $this->assertSame(
            [['name' => 'registry-credentials']],
            $this->podSpec($deployment)['imagePullSecrets']
        );
    }

    public function testNoPullSecretMeansNoneIsReferenced(): void {
        $deployment = Fixtures::deployableDeployment([], [], ['pull_secret' => '']);

        $this->assertArrayNotHasKey('imagePullSecrets', $this->podSpec($deployment));
    }

    /**
     * The selector has to match the pod labels, or the Deployment adopts no pods at all.
     */
    public function testSelectorMatchesThePodLabels(): void {
        $deployment = Fixtures::deployableDeployment();

        $manifest = $this->build($deployment);

        $this->assertSame(
            $manifest['spec']['selector']['matchLabels'],
            $manifest['spec']['template']['metadata']['labels']
        );
        $this->assertSame(
            ['app' => $deployment->name, 'role' => 'app'],
            $manifest['spec']['selector']['matchLabels']
        );
    }

    public function testReplicasComeFromTheDeployment(): void {
        $deployment = Fixtures::deployableDeployment(['replicas' => 3]);

        $this->assertSame(3, $this->build($deployment)['spec']['replicas']);
    }

    /**
     * A volume attached to this one deployment is mounted from the claim named after the
     * deployment - the one `PersistentVolumeClaimStep` creates.
     */
    public function testADeploymentVolumeIsMountedFromTheDeploymentsOwnClaim(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::deploymentVolume([
            'deployment_id' => $deployment->id,
            'mount_path' => '/var/www/storage',
            'sub_path' => 'files',
        ]);

        $podSpec = $this->podSpec($deployment);

        $this->assertSame(
            ['claimName' => $deployment->name],
            $podSpec['volumes'][0]['persistentVolumeClaim']
        );
        $this->assertSame(
            ['name' => $deployment->name, 'mountPath' => '/var/www/storage', 'subPath' => 'files'],
            $this->container($deployment)['volumeMounts'][0]
        );
    }

    /**
     * A volume set on the specification is shared by every deployment of it, so its sub
     * path is where they are kept apart: the placeholders are filled in per deployment.
     */
    public function testASpecificationVolumeIsMountedUnderACompiledSubPath(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'mount_path' => '/shared',
            'sub_path' => '${deployment.name}',
        ]);

        $mount = $this->container($deployment)['volumeMounts'][0];

        $this->assertSame('/shared', $mount['mountPath']);
        $this->assertSame($deployment->name, $mount['subPath'], 'the placeholder should be filled in');
    }

    public function testNoVolumesMeansNoneAreDeclared(): void {
        $deployment = Fixtures::deployableDeployment();

        $this->assertArrayNotHasKey('volumes', $this->podSpec($deployment));
    }

    /**
     * Init containers run to completion before the application's own container starts, so
     * the order they are declared in is the order they run in - `position` decides it.
     */
    public function testInitContainersAreDeclaredInPositionOrder(): void {
        $deployment = Fixtures::deployableDeployment();
        $image = Fixtures::containerImage(['url' => 'registry.example.org/init']);

        foreach ([['warm-cache', 1], ['wait-for-db', 0]] as [$name, $position]) {
            $initContainer = Fixtures::initContainer(['name' => $name, 'container_image_id' => $image->id]);
            Fixtures::specificationInitContainer([
                'deployment_specification_id' => $deployment->deployment_specification_id,
                'init_container_id' => $initContainer->id,
                'position' => $position,
            ]);
        }

        $this->assertSame(
            ['wait-for-db', 'warm-cache'],
            array_column($this->podSpec($deployment)['initContainers'], 'name')
        );
    }

    public function testNoInitContainersMeansNoneAreDeclared(): void {
        $deployment = Fixtures::deployableDeployment();

        $this->assertArrayNotHasKey('initContainers', $this->podSpec($deployment));
    }

    /**
     * An annotation is written at the level it was set for, and nowhere else. The two are
     * read by different things - a Deployment annotation by whatever watches Deployments,
     * a pod one by the sidecar injectors and scrapers that only ever see pods - so putting
     * one at the wrong level is the same as not setting it.
     */
    public function testAnnotationsAreWrittenAtTheLevelTheyWereSetFor(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::deploymentAnnotation([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'level' => \DeploymentAnnotationLevels::Deployment,
            'name' => 'example.org/on-the-deployment',
            'value' => 'deployment-value',
        ]);
        Fixtures::deploymentAnnotation([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'level' => \DeploymentAnnotationLevels::Pod,
            'name' => 'example.org/on-the-pod',
            'value' => 'pod-value',
        ]);

        $manifest = $this->build($deployment);
        $onTheDeployment = $manifest['metadata']['annotations'];
        $onThePod = $manifest['spec']['template']['metadata']['annotations'];

        $this->assertSame('deployment-value', $onTheDeployment['example.org/on-the-deployment']);
        $this->assertArrayNotHasKey('example.org/on-the-pod', $onTheDeployment);

        $this->assertSame('pod-value', $onThePod['example.org/on-the-pod']);
        $this->assertArrayNotHasKey('example.org/on-the-deployment', $onThePod);

        $this->assertSame('kso', $onThePod['app.kubernetes.io/managed-by'], 'both levels keep the marker');
    }

    /**
     * The group mounted volumes are owned by. It sits on the pod rather than the container,
     * because it is what makes a volume writable for every container in the pod.
     */
    public function testFsGroupComesFromTheContainerImage(): void {
        $deployment = Fixtures::deployableDeployment([], [], ['security_context_fs_group' => '3000']);

        $this->assertSame(3000, $this->podSpec($deployment)['securityContext']['fsGroup']);
    }

    public function testNoFsGroupIsLeftOutRatherThanSentAsZero(): void {
        $deployment = Fixtures::deployableDeployment([], [], ['security_context_fs_group' => '']);

        $this->assertArrayNotHasKey('securityContext', $this->podSpec($deployment));
    }

    /**
     * The pods run under the deployment's own service account when the specification turns
     * RBAC on - that account is what the Role and RoleBinding steps grant permissions to.
     * Without the name here the pods run as `default` and the grant reaches nothing.
     */
    public function testServiceAccountIsUsedWhenTheSpecificationEnablesRbac(): void {
        $withRbac = Fixtures::deployableDeployment([], ['enable_rbac' => true]);
        $without = Fixtures::deployableDeployment([], ['enable_rbac' => false]);

        $this->assertSame($withRbac->name, $this->podSpec($withRbac)['serviceAccountName']);
        $this->assertArrayNotHasKey('serviceAccountName', $this->podSpec($without));
    }

    /**
     * What the step answers when the UI asks what it is and what it can do, and which
     * changes to a deployment it wants to be redeployed for.
     */
    public function testTheStepDescribesItselfAndWhatItReactsTo(): void {
        $step = new DeploymentStep();

        $this->assertSame([
            'identifier' => DeploymentSteps::Deployment,
            'level' => DeploymentStepLevels::Deployment,
            'name' => 'Deployment',
            'hasPreviewCommand' => true,
            'hasStatusCommand' => true,
            'hasDeployCommand' => true,
            'hasKubernetesEvents' => true,
            'hasKubernetesStatus' => true,
            'hasTerminateCommand' => true,
        ], $step->toArray());

        $this->assertSame(
            DeploymentStepHelper::Deployment_Found,
            $step->getSuccessStatus(Fixtures::deployableDeployment())
        );

        // Everything that changes what the pods run or what they run with. A trigger that
        // goes missing here is a change that is saved and never reaches the cluster.
        $this->assertSame([
            DeploymentStepTriggers::Deployment_Volume_Updated,
            DeploymentStepTriggers::Deployment_Environment_Updated,
            DeploymentStepTriggers::Deployment_EnvironmentVariable_Updated,
            DeploymentStepTriggers::Deployment_ResourceManagement_Updated,
            DeploymentStepTriggers::Deployment_UpdateManagement_Updated,
            DeploymentStepTriggers::Deployment_Version_Updated,
            DeploymentStepTriggers::Deployment_ImagePullPolicy_Updated,
        ], $step->getTriggers());
    }

    /**
     * The four checks that need nothing but the row itself. The ones after them ask the
     * cluster whether the namespace is there, so they are in the cluster suite.
     */
    public function testADeploymentMissingItsBasicsIsRefusedBeforeAnythingIsSent(): void {
        $step = new DeploymentStep();

        $this->assertSame('Missing name', $step->validateDeployCommand(
            Fixtures::deployableDeployment(['name' => ''])
        ));
        $this->assertSame('Missing namespace', $step->validateDeployCommand(
            Fixtures::deployableDeployment(['namespace' => ''])
        ));
        $this->assertSame('Missing image', $step->validateDeployCommand(
            Fixtures::deployableDeployment(['image' => ''])
        ));
        $this->assertSame('Missing version', $step->validateDeployCommand(
            Fixtures::deployableDeployment(['image' => 'nginx', 'version' => ''])
        ));
    }

    /**
     * Zero replicas is how a workspace is paused, and it is set elsewhere - through the
     * workspace, which scales the Deployment down without going through this step. Asking
     * the step to deploy none is a mistake, and applying it would take the workspace down.
     */
    public function testADeploymentWithoutReplicasIsRefused(): void {
        $deployment = Fixtures::deployableDeployment(['image' => 'nginx', 'replicas' => 0]);

        $this->assertSame('Replicas must be > 0', (new DeploymentStep())->validateDeployCommand($deployment));
    }

    // <editor-fold desc="Reading the manifest">

    /**
     * @return array<string, mixed>
     */
    private function build(Deployment $deployment): array {
        return $this->manifest(DeploymentStep::class, $deployment);
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
     * The container's environment as a name to value map, which is easier to assert on
     * than Kubernetes' list of name/value pairs.
     *
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
