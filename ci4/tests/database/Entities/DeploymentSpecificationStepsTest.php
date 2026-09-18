<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\Deployment;
use App\Entities\DeploymentSpecification;
use App\Fixtures;
use App\Libraries\DeploymentSteps\BaseDeploymentStep;

/**
 * Which steps a specification runs, and in what order.
 *
 * Every manifest test starts from the assumption that its step is called at all. This is
 * what decides that: six flags, two enums and a lookup against the system's hosting
 * provider, resolved into a list. A step missing from the list is not a failed deploy - it
 * is a resource that is quietly never created, and the deployment goes green without it.
 *
 * The order matters just as much. Steps run in sequence, so a claim built after the pod
 * that mounts it is a pod that will not start.
 */
class DeploymentSpecificationStepsTest extends DatabaseTestCase {

    // <editor-fold desc="Which steps">

    /**
     * Nothing can be created before the namespace it lives in, so it leads every list
     * whatever else is turned on.
     */
    public function testNamespaceIsAlwaysFirstAndAlwaysPresent(): void {
        $bare = $this->steps(Fixtures::deploymentSpecification());
        $everything = $this->steps($this->specificationWithEverythingEnabled());

        $this->assertSame('NamespaceStep', $bare[0]);
        $this->assertSame('NamespaceStep', $everything[0]);
    }

    public function testWorkloadTypeDecidesTheWorkloadStep(): void {
        $this->assertContains('DeploymentStep', $this->stepsForWorkload(\WorkloadTypes::Deployment));
        $this->assertContains('KServiceStep', $this->stepsForWorkload(\WorkloadTypes::KNativeService));
        $this->assertContains('CustomResourceStep', $this->stepsForWorkload(\WorkloadTypes::CustomResource));
    }

    /**
     * DaemonSet is in the enum and selectable, but no step handles it: the specification
     * resolves to a namespace and nothing else. The deployment has nothing to run and
     * nothing says so.
     */
    public function testDaemonSetResolvesToNoWorkloadAtAll(): void {
        $steps = $this->stepsForWorkload(\WorkloadTypes::DaemonSet);

        $this->assertSame(['NamespaceStep'], $steps);
    }

    public function testDatabaseBringsItsMigrationStepWithIt(): void {
        $steps = $this->steps(Fixtures::deploymentSpecification(['enable_database' => true]));

        $this->assertContains('DatabaseStep', $steps);
        $this->assertContains('MigrationJobStep', $steps);
    }

    public function testRbacAddsAllFiveSteps(): void {
        $steps = $this->steps(Fixtures::deploymentSpecification(['enable_rbac' => true]));

        foreach (['ServiceAccountStep', 'ClusterRoleStep', 'RoleStep', 'ClusterRoleBindingStep', 'RoleBindingStep'] as $step) {
            $this->assertContains($step, $steps);
        }
    }

    /**
     * One network type, one step. Picking the wrong one is not a visible error - the
     * workload simply answers on nothing.
     */
    public function testNetworkTypeDecidesTheExternalAccessStep(): void {
        $expected = [
            \NetworkTypes::NginxIngress => 'IngressStep',
            \NetworkTypes::Istio => 'IstioVirtualServiceStep',
            \NetworkTypes::Contour => 'ContourHttpProxyStep',
            \NetworkTypes::GatewayApi => 'GatewayHttpRouteStep',
        ];

        foreach ($expected as $networkType => $step) {
            $steps = $this->steps(Fixtures::deploymentSpecification([
                'enable_external_access' => true,
                'network_type' => $networkType,
            ]));

            $this->assertContains($step, $steps, "network type {$networkType}");
            foreach (array_diff($expected, [$step]) as $other) {
                $this->assertNotContains($other, $steps, "network type {$networkType}");
            }
        }
    }

    public function testNoExternalAccessMeansNoNetworkStep(): void {
        $steps = $this->steps(Fixtures::deploymentSpecification([
            'enable_external_access' => false,
            'network_type' => \NetworkTypes::GatewayApi,
        ]));

        $this->assertNotContains('GatewayHttpRouteStep', $steps);
    }

    public function testInternalAccessAddsAServiceForAPlainDeployment(): void {
        $steps = $this->steps(Fixtures::deploymentSpecification([
            'workload_type' => \WorkloadTypes::Deployment,
            'enable_internal_access' => true,
        ]));

        $this->assertContains('ServiceStep', $steps);
    }

    /**
     * KNative brings its own networking, so it must not get a Service of its own.
     */
    public function testKnativeGetsNoServiceStep(): void {
        $steps = $this->steps(Fixtures::deploymentSpecification([
            'workload_type' => \WorkloadTypes::KNativeService,
            'enable_internal_access' => true,
        ]));

        $this->assertNotContains('ServiceStep', $steps);
    }

    /**
     * Both policies target a Service through a GKE Gateway, so all three conditions have
     * to hold at once: internal access, external access over Gateway API, and GKE.
     */
    public function testPolicyStepsNeedGkeBehindAGatewayApi(): void {
        Fixtures::system(['hosting_provider' => \HostingProviders::Gke]);

        $steps = $this->steps($this->specificationBehindAGateway());

        $this->assertContains('HealthCheckPolicyStep', $steps);
        $this->assertContains('GcpBackendPolicyStep', $steps);
    }

    public function testPolicyStepsAreLeftOutOnOtherHostingProviders(): void {
        Fixtures::system(['hosting_provider' => \HostingProviders::Openshift]);

        $steps = $this->steps($this->specificationBehindAGateway());

        $this->assertContains('ServiceStep', $steps);
        $this->assertNotContains('HealthCheckPolicyStep', $steps);
        $this->assertNotContains('GcpBackendPolicyStep', $steps);
    }

    public function testPolicyStepsAreLeftOutOnOtherNetworkTypes(): void {
        Fixtures::system(['hosting_provider' => \HostingProviders::Gke]);

        $steps = $this->steps($this->specificationBehindAGateway(['network_type' => \NetworkTypes::NginxIngress]));

        $this->assertNotContains('HealthCheckPolicyStep', $steps);
        $this->assertNotContains('GcpBackendPolicyStep', $steps);
    }

    public function testCronjobFlagAddsTheCronjobStep(): void {
        $this->assertContains('CronjobStep', $this->steps(Fixtures::deploymentSpecification(['enable_cronjob' => true])));
        $this->assertNotContains('CronjobStep', $this->steps(Fixtures::deploymentSpecification(['enable_cronjob' => false])));
    }

    // </editor-fold>

    // <editor-fold desc="Volumes, which are the odd ones out">

    /**
     * The flag alone is not enough - there has to be a volume row to build from. Unlike
     * every other step, this one looks at data rather than only at configuration.
     */
    public function testVolumeStepsNeedAnActualVolume(): void {
        $specification = Fixtures::deploymentSpecification(['enable_volumes' => true]);
        $deployment = Fixtures::deployment(['deployment_specification_id' => $specification->id]);

        $this->assertNotContains('PersistentVolumeStep', $this->steps($specification, $deployment));

        Fixtures::deploymentVolume(['deployment_id' => $deployment->id]);

        $withVolume = $this->steps($specification, $deployment);
        $this->assertContains('PersistentVolumeStep', $withVolume);
        $this->assertContains('PersistentVolumeClaimStep', $withVolume);
    }

    public function testAVolumeOnTheSpecificationCountsToo(): void {
        $specification = Fixtures::deploymentSpecification(['enable_volumes' => true]);
        $deployment = Fixtures::deployment(['deployment_specification_id' => $specification->id]);
        Fixtures::specificationVolume(['deployment_specification_id' => $specification->id]);

        $this->assertContains('PersistentVolumeStep', $this->steps($specification, $deployment));
    }

    /**
     * A trap worth knowing about. The deployment argument is optional, and without it the
     * volume steps are skipped silently - so the same specification answers differently
     * depending on how it is asked. Anything that decides what to deploy has to pass the
     * deployment.
     */
    public function testAskingWithoutADeploymentSilentlyDropsTheVolumeSteps(): void {
        $specification = Fixtures::deploymentSpecification(['enable_volumes' => true]);
        $deployment = Fixtures::deployment(['deployment_specification_id' => $specification->id]);
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id]);

        $this->assertContains('PersistentVolumeStep', $this->steps($specification, $deployment));
        $this->assertNotContains('PersistentVolumeStep', $this->steps($specification, null));
    }

    // </editor-fold>

    // <editor-fold desc="Order">

    /**
     * The claim has to exist before the pod that mounts it, or the pod stays Pending.
     */
    public function testClaimIsBuiltBeforeTheWorkloadThatMountsIt(): void {
        $specification = Fixtures::deploymentSpecification([
            'enable_volumes' => true,
            'workload_type' => \WorkloadTypes::Deployment,
        ]);
        $deployment = Fixtures::deployment(['deployment_specification_id' => $specification->id]);
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id]);

        $steps = $this->steps($specification, $deployment);

        $this->assertLessThan(
            array_search('DeploymentStep', $steps, true),
            array_search('PersistentVolumeClaimStep', $steps, true)
        );
        $this->assertLessThan(
            array_search('PersistentVolumeClaimStep', $steps, true),
            array_search('PersistentVolumeStep', $steps, true)
        );
    }

    /**
     * The service account has to be there before the pod that runs under it.
     */
    public function testServiceAccountIsBuiltBeforeTheWorkload(): void {
        $steps = $this->steps($this->specificationWithEverythingEnabled());

        $this->assertLessThan(
            array_search('DeploymentStep', $steps, true),
            array_search('ServiceAccountStep', $steps, true)
        );
    }

    /**
     * The migration runs late - after the workload and its networking are in place, not
     * before. Worth knowing when reading a release that is stuck: by the time the
     * migration starts, the new version is already rolling out.
     */
    public function testMigrationRunsAfterTheWorkloadIsInPlace(): void {
        $steps = $this->steps($this->specificationWithEverythingEnabled());

        $this->assertGreaterThan(
            array_search('DeploymentStep', $steps, true),
            array_search('MigrationJobStep', $steps, true)
        );
        $this->assertGreaterThan(
            array_search('ServiceStep', $steps, true),
            array_search('MigrationJobStep', $steps, true)
        );
    }

    /**
     * The whole sequence, pinned.
     *
     * Sorting is done against a fixed list of classes, and `array_search` returns false -
     * which sorts as zero - for anything missing from it. A step left off the list
     * therefore does not fail; it silently moves to the front, ahead of the namespace it
     * needs. Only asserting the full order catches that, which is why this is spelled out
     * rather than checked pairwise.
     */
    public function testTheFullOrderIsFixed(): void {
        Fixtures::system(['hosting_provider' => \HostingProviders::Gke]);
        $specification = $this->specificationWithEverythingEnabled();
        $deployment = Fixtures::deployment(['deployment_specification_id' => $specification->id]);
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id]);

        $this->assertSame([
            'NamespaceStep',
            'GatewayHttpRouteStep',
            'DatabaseStep',
            'ServiceAccountStep',
            'ClusterRoleStep',
            'ClusterRoleBindingStep',
            'RoleStep',
            'RoleBindingStep',
            'PersistentVolumeStep',
            'PersistentVolumeClaimStep',
            'DeploymentStep',
            'ServiceStep',
            'HealthCheckPolicyStep',
            'GcpBackendPolicyStep',
            'MigrationJobStep',
            'CronjobStep',
        ], $this->steps($specification, $deployment));
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures and reading">

    /**
     * Everything a single specification can turn on at once.
     *
     * @param array<string, mixed> $overrides
     */
    private function specificationWithEverythingEnabled(array $overrides = []): DeploymentSpecification {
        return Fixtures::deploymentSpecification(array_merge([
            'workload_type' => \WorkloadTypes::Deployment,
            'network_type' => \NetworkTypes::GatewayApi,
            'enable_database' => true,
            'enable_rbac' => true,
            'enable_external_access' => true,
            'enable_internal_access' => true,
            'enable_cronjob' => true,
            'enable_volumes' => true,
        ], $overrides));
    }

    /**
     * The arrangement the two GKE policy steps look for.
     *
     * @param array<string, mixed> $overrides
     */
    private function specificationBehindAGateway(array $overrides = []): DeploymentSpecification {
        return Fixtures::deploymentSpecification(array_merge([
            'workload_type' => \WorkloadTypes::Deployment,
            'network_type' => \NetworkTypes::GatewayApi,
            'enable_external_access' => true,
            'enable_internal_access' => true,
        ], $overrides));
    }

    /**
     * @return string[]
     */
    private function stepsForWorkload(string $workloadType): array {
        return $this->steps(Fixtures::deploymentSpecification(['workload_type' => $workloadType]));
    }

    /**
     * The step list as short class names, which is what the assertions read on.
     *
     * @return string[]
     */
    private function steps(DeploymentSpecification $specification, ?Deployment $deployment = null): array {
        return array_map(
            static fn (BaseDeploymentStep $step) => (new \ReflectionClass($step))->getShortName(),
            $specification->getDeploymentSteps($deployment)
        );
    }

    // </editor-fold>

}
