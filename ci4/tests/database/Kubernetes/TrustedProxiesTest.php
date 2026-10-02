<?php namespace App\Tests\Database\Kubernetes;

use App\Libraries\DeploymentSteps\DeploymentStep;
use App\ManifestTestCase;
use App\Entities\Deployment;
use App\Entities\System;
use App\Fixtures;
use App\Libraries\Kubernetes\ContainerEnvironment;
use App\Libraries\Kubernetes\TrustedProxies;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * `${network.trustedProxies}`: the proxies in front of a deployment, worked out from its way in -
 * and a deploy refused, with what is missing, when kso cannot tell. The cluster is the test's own:
 * its nodes and its Gateways' status.
 */
class TrustedProxiesTest extends ManifestTestCase {

    public function setUp(): void {
        parent::setUp();
        TrustedProxies::$nodes = fn() => [
            ['metadata' => ['name' => 'node-a'], 'spec' => ['podCIDRs' => ['10.244.0.0/24', 'fd00:10:244::/64']]],
            ['metadata' => ['name' => 'node-b'], 'spec' => ['podCIDRs' => ['10.244.1.0/24']]],
        ];
        TrustedProxies::$gateway = fn() => ['status' => ['addresses' => [['type' => 'IPAddress', 'value' => '34.120.1.2']]]];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function proxiesInTheCluster(): array {
        return [
            'nginx' => [\NetworkTypes::NginxIngress],
            'istio' => [\NetworkTypes::Istio],
            'contour' => [\NetworkTypes::Contour],
        ];
    }

    #[DataProvider('proxiesInTheCluster')]
    public function testAProxyInTheClusterIsThePodNetworkFromTheNodes(string $networkType): void {
        $deployment = $this->aDeployment(['network_type' => $networkType]);

        $this->assertSame('10.244.0.0/24,fd00:10:244::/64,10.244.1.0/24', TrustedProxies::For($deployment));
    }

    public function testThePodNetworkSetOnTheSystemWins(): void {
        $this->aPodNetwork('10.0.0.0/14, 10.4.0.0/14');

        $this->assertSame('10.0.0.0/14,10.4.0.0/14', TrustedProxies::For($this->aDeployment()));
    }

    public function testAGatewayThatRunsInTheClusterIsThePodNetworkToo(): void {
        $deployment = $this->aDeploymentBehind(['gateway_class_name' => 'envoy']);

        $this->assertSame('10.244.0.0/24,fd00:10:244::/64,10.244.1.0/24', TrustedProxies::For($deployment));
    }

    /**
     * Google's front ends send to the pod, and the load balancer writes its own address after the
     * client's - so both are trusted.
     */
    public function testAGlobalGkeGatewayIsGooglesFrontEndsAndItsOwnAddress(): void {
        $deployment = $this->aDeploymentBehind(['gateway_class_name' => 'gke-l7-global-external-managed']);

        $this->assertSame('130.211.0.0/22,35.191.0.0/16,2600:2d00:1:1::/64,34.120.1.2', TrustedProxies::For($deployment));
    }

    public function testARegionalGkeGatewayIsItsProxyOnlySubnetAndItsOwnAddress(): void {
        $deployment = $this->aDeploymentBehind(['gateway_class_name' => 'gke-l7-rilb', 'proxy_source_ranges' => '10.129.0.0/23']);

        $this->assertSame('10.129.0.0/23,34.120.1.2', TrustedProxies::For($deployment));
    }

    /**
     * The proxy-only subnet cannot be read from Kubernetes, and a guess is worse than nothing.
     */
    public function testARegionalGkeGatewayWithoutItsRangesIsRefusedWithWhatToDo(): void {
        $deployment = $this->aDeploymentBehind(['gateway_class_name' => 'gke-l7-regional-external-managed']);

        $this->expectExceptionMessage('Set Proxy source ranges on the Gateway');

        TrustedProxies::For($deployment);
    }

    public function testAGkeGatewayWithoutAnAddressYetIsRefused(): void {
        TrustedProxies::$gateway = fn() => ['status' => []];
        $deployment = $this->aDeploymentBehind(['gateway_class_name' => 'gke-l7-global-external-managed']);

        $this->expectExceptionMessage('has no address yet');

        TrustedProxies::For($deployment);
    }

    public function testANodeWithoutItsPodNetworkAndNoSettingIsRefusedWithWhatToDo(): void {
        TrustedProxies::$nodes = fn() => [['metadata' => ['name' => 'node-c'], 'spec' => []]];

        $this->expectExceptionMessage('The node node-c says nothing of its pod network (spec.podCIDRs) - its network plugin keeps it elsewhere. Set Pod network under System');

        TrustedProxies::For($this->aDeployment());
    }

    /**
     * The queue-proxy in the pod forwards to the app on localhost.
     */
    public function testKnativeAddsTheQueueProxyOnLocalhost(): void {
        $deployment = $this->aDeployment(['workload_type' => \WorkloadTypes::KNativeService]);

        $this->assertSame('10.244.0.0/24,fd00:10:244::/64,10.244.1.0/24,127.0.0.1,::1', TrustedProxies::For($deployment));
    }

    /**
     * Filled in where it is written, and only there - a deployment that does not ask is not read
     * from the cluster at all.
     */
    public function testThePlaceholderIsFilledInWhereItIsWrittenAndOnlyThenIsTheClusterAsked(): void {
        $deployment = $this->aDeployment();
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $deployment->deployment_specification_id, 'name' => 'TRUSTED_PROXIES', 'value' => '${network.trustedProxies}']);
        $other = $this->aDeployment();
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $other->deployment_specification_id, 'name' => 'PLAIN', 'value' => 'x']);

        $this->assertSame('10.244.0.0/24,fd00:10:244::/64,10.244.1.0/24', ContainerEnvironment::ofDeployment($deployment)->toArray()['TRUSTED_PROXIES']);

        TrustedProxies::$nodes = fn() => throw new \RuntimeException('asked');
        TrustedProxies::ForgetWorkedOut();
        $this->assertSame('x', ContainerEnvironment::ofDeployment($other)->toArray()['PLAIN']);
    }

    /**
     * Not a secret: in the manifest as it is, so a new node pool - a new range - is a change the
     * preview shows, and the next deploy takes along.
     */
    public function testANewRangeIsAChangeInTheManifest(): void {
        $deployment = Fixtures::deployableDeployment([], ['network_type' => \NetworkTypes::Contour]);
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $deployment->deployment_specification_id, 'name' => 'TRUSTED_PROXIES', 'value' => '${network.trustedProxies}']);
        $value = fn() => array_column($this->manifest(DeploymentStep::class, $deployment)['spec']['template']['spec']['containers'][0]['env'], 'value', 'name')['TRUSTED_PROXIES'];

        $before = $value();
        TrustedProxies::$nodes = fn() => [['metadata' => ['name' => 'node-a'], 'spec' => ['podCIDRs' => ['10.244.0.0/24']]], ['metadata' => ['name' => 'pool-2'], 'spec' => ['podCIDRs' => ['10.245.0.0/24']]]];
        TrustedProxies::ForgetWorkedOut();

        $this->assertSame('10.244.0.0/24,fd00:10:244::/64,10.244.1.0/24', $before);
        $this->assertSame('10.244.0.0/24,10.245.0.0/24', $value());
    }

    // <editor-fold desc="Fixtures">

    /**
     * @param array<string, mixed> $specification
     */
    private function aDeployment(array $specification = []): Deployment {
        $spec = Fixtures::deploymentSpecification(array_merge(['network_type' => \NetworkTypes::Contour], $specification));

        return Fixtures::deployment(['deployment_specification_id' => $spec->id, 'version' => '1.0.0']);
    }

    /**
     * @param array<string, mixed> $gateway
     */
    private function aDeploymentBehind(array $gateway): Deployment {
        $spec = Fixtures::deploymentSpecification(['network_type' => \NetworkTypes::GatewayApi]);
        $domain = Fixtures::domain(['gateway_id' => Fixtures::gateway($gateway)->id]);
        $workspace = Fixtures::workspace(['domain_id' => $domain->id]);

        return Fixtures::deployment(['deployment_specification_id' => $spec->id, 'workspace_id' => $workspace->id, 'version' => '1.0.0']);
    }

    private function aPodNetwork(string $ranges): void {
        $system = System::Get();
        $system->pod_network = $ranges;
        $system->save();
    }

    // </editor-fold>

}
