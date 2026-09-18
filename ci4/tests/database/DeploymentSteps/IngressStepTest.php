<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\IngressStep;
use App\ManifestTestCase;

/**
 * The nginx Ingress, used by workspaces on the nginx network type rather than Gateway API.
 *
 * Most of this manifest is nginx annotations, and an annotation with the wrong value is
 * accepted by Kubernetes and then quietly ignored - upload limits and timeouts are exactly
 * the settings nobody notices are wrong until a large request fails in production.
 */
class IngressStepTest extends ManifestTestCase {

    public function testIngressIsNamedAndNamespacedAfterTheDeployment(): void {
        $deployment = $this->deploymentWithIngress();

        $manifest = $this->build($deployment)[0];

        $this->assertSame($deployment->name, $manifest['metadata']['name']);
        $this->assertSame($deployment->namespace, $manifest['metadata']['namespace']);
    }

    public function testNoIngressOnTheSpecificationGeneratesNothing(): void {
        $deployment = Fixtures::deployableDeployment([], ['network_type' => \NetworkTypes::NginxIngress]);

        $this->assertCount(0, $this->build($deployment));
    }

    /**
     * nginx wants the body size with a unit. A bare number is read as bytes, so 8 would
     * become eight bytes rather than eight megabytes.
     */
    public function testProxyBodySizeIsWrittenInMegabytes(): void {
        $deployment = $this->deploymentWithIngress(['proxy_body_size' => 64]);

        $this->assertSame('64m', $this->annotations($deployment)['nginx.ingress.kubernetes.io/proxy-body-size']);
    }

    public function testTimeoutsAreCarriedOverAsStrings(): void {
        $deployment = $this->deploymentWithIngress([
            'proxy_connect_timeout' => 30,
            'proxy_read_timeout' => 300,
            'proxy_send_timeout' => 120,
        ]);

        $annotations = $this->annotations($deployment);

        $this->assertSame('30', $annotations['nginx.ingress.kubernetes.io/proxy-connect-timeout']);
        $this->assertSame('300', $annotations['nginx.ingress.kubernetes.io/proxy-read-timeout']);
        $this->assertSame('120', $annotations['nginx.ingress.kubernetes.io/proxy-send-timeout']);
    }

    /**
     * The annotation is a string, and nginx treats anything that is not "true" as false.
     * A boolean false would serialise to an empty string and silently disable the redirect.
     */
    public function testSslRedirectIsTheStringTrueOrFalse(): void {
        $on = $this->deploymentWithIngress(['ssl_redirect' => true]);
        $off = $this->deploymentWithIngress(['ssl_redirect' => false]);

        $this->assertSame('true', $this->annotations($on)['nginx.ingress.kubernetes.io/ssl-redirect']);
        $this->assertSame('false', $this->annotations($off)['nginx.ingress.kubernetes.io/ssl-redirect']);
    }

    public function testIngressClassComesFromTheSpecification(): void {
        $deployment = $this->deploymentWithIngress(['ingress_class' => 'nginx-internal']);

        $this->assertSame('nginx-internal', $this->build($deployment)[0]['spec']['ingressClassName']);
    }

    public function testRuleRoutesTheWorkspaceHostnameToTheDeploymentsService(): void {
        $deployment = Fixtures::deployableDeployment([], ['network_type' => \NetworkTypes::NginxIngress]);
        $ingress = Fixtures::ingress(['deployment_specification_id' => $deployment->deployment_specification_id]);
        Fixtures::ingressRulePath([
            'deployment_specification_ingress_id' => $ingress->id,
            'path' => '/api',
            'path_type' => 'Prefix',
            'backend_service_port_name' => 'http',
        ]);

        $rule = $this->build($deployment)[0]['spec']['rules'][0];

        $this->assertStringContainsString('tenant.test.example.org', $rule['host']);

        $path = $rule['http']['paths'][0];
        $this->assertSame('/api', $path['path']);
        $this->assertSame('Prefix', $path['pathType']);
        $this->assertSame($deployment->name, $path['backend']['service']['name']);
        $this->assertSame('http', $path['backend']['service']['port']['name']);
    }

    public function testTlsUsesTheDomainsCertificate(): void {
        $deployment = $this->deploymentWithIngress(['enable_tls' => true]);

        $tls = $this->build($deployment)[0]['spec']['tls'][0];

        $this->assertSame('test-cert', $tls['secretName']);
        $this->assertStringContainsString('tenant.test.example.org', $tls['hosts'][0]);
    }

    public function testTlsIsLeftOutWhenNotEnabled(): void {
        $deployment = $this->deploymentWithIngress(['enable_tls' => false]);

        $this->assertArrayNotHasKey('tls', $this->build($deployment)[0]['spec']);
    }

    /**
     * A specification can declare more than one ingress. Only the first keeps the plain
     * deployment name, since two objects cannot share it.
     */
    public function testSecondIngressGetsASuffixedName(): void {
        $deployment = $this->deploymentWithIngress();
        Fixtures::ingress(['deployment_specification_id' => $deployment->deployment_specification_id]);

        $manifests = $this->build($deployment);

        $this->assertCount(2, $manifests);
        $this->assertSame($deployment->name, $manifests[0]['metadata']['name']);
        $this->assertSame($deployment->name . '-1', $manifests[1]['metadata']['name']);
    }

    // <editor-fold desc="What the step says it is">

    /**
     * A specification stores the identifier, and `GetStep()` is what turns it back into a
     * step. A step whose identifier does not round trip is unreachable from a saved
     * specification - it would simply never run, and nothing would say so.
     */
    public function testTheStepIsReachableUnderItsOwnIdentifier(): void {
        $step = new IngressStep();

        $this->assertSame(DeploymentSteps::Ingress, $step->getIdentifier());
        $this->assertInstanceOf(IngressStep::class, DeploymentStepHelper::GetStep($step->getIdentifier()));
    }

    /**
     * Deployment level: an Ingress is named after the deployment and its rules point at
     * that deployment's Service, so there is one run per deployment.
     */
    public function testTheStepRunsAtDeploymentLevel(): void {
        $this->assertSame(DeploymentStepLevels::Deployment, (new IngressStep())->getLevel());
    }

    /**
     * No triggers, so nothing that changes a deployment re-applies the ingress on its own -
     * `EmitTrigger()` walks the steps and matches on this list.
     */
    public function testNoTriggerReAppliesTheIngress(): void {
        $this->assertSame([], (new IngressStep())->getTriggers());
    }

    /**
     * `toArray()` is the whole contract the UI has with a step: which buttons it draws and
     * which it leaves disabled. Asserting the array rather than each flag is deliberate -
     * a flag that silently flips to false takes a button away, and nothing else notices.
     */
    public function testTheUiIsOfferedEveryCommand(): void {
        $this->assertSame([
            'identifier' => DeploymentSteps::Ingress,
            'level' => DeploymentStepLevels::Deployment,
            'name' => 'Ingress',
            'hasPreviewCommand' => true,
            'hasStatusCommand' => true,
            'hasDeployCommand' => true,
            'hasKubernetesEvents' => true,
            'hasKubernetesStatus' => true,
            'hasTerminateCommand' => true,
        ], (new IngressStep())->toArray());
    }

    public function testASuccessfulDeployMeansTheIngressIsFound(): void {
        $this->assertSame(
            DeploymentStepHelper::Ingress_Found,
            (new IngressStep())->getSuccessStatus(new Deployment())
        );
    }

    // </editor-fold>

    // <editor-fold desc="Refusing to deploy">

    /**
     * The Ingress is named after the deployment, so an unnamed one has nothing to be
     * called.
     */
    public function testADeploymentWithoutANameIsNotDeployable(): void {
        $deployment = Fixtures::deployment(['name' => '']);

        $this->assertSame('Missing name', $this->validate($deployment));
    }

    public function testADeploymentWithoutANamespaceIsNotDeployable(): void {
        $deployment = Fixtures::deployment(['namespace' => '']);

        $this->assertSame('Missing namespace', $this->validate($deployment));
    }

    public function testADeploymentWithoutAWorkspaceIsNotDeployable(): void {
        $deployment = Fixtures::deployment(['workspace_id' => null]);

        $this->assertSame('Missing workspace', $this->validate($deployment));
    }

    public function testAWorkspaceWithoutANamespaceIsNotDeployable(): void {
        $workspace = Fixtures::workspaceOnGateway([], ['namespace' => '']);
        $deployment = Fixtures::deployment(['workspace_id' => $workspace->id]);

        $this->assertSame('Missing workspace namespace', $this->validate($deployment));
    }

    public function testAWorkspaceWithoutADomainIsNotDeployable(): void {
        $deployment = Fixtures::deployment(['workspace_id' => Fixtures::workspace()->id]);

        $this->assertSame('Missing workspace domain', $this->validate($deployment));
    }

    /**
     * The domain row can go while the workspace still points at it - the workspace keeps
     * the id, and the step reads it back for the TLS secret name.
     */
    public function testADomainThatHasBeenDeletedIsReported(): void {
        $domain = Fixtures::domain();
        $deployment = Fixtures::deployment(['workspace_id' => Fixtures::workspace(['domain_id' => $domain->id])->id]);
        $domain->delete();

        $this->assertSame('domain no longer exists', $this->validate($deployment));
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures and reading">

    /**
     * @param array<string, mixed> $ingress
     */
    private function deploymentWithIngress(array $ingress = []): Deployment {
        $deployment = Fixtures::deployableDeployment([], ['network_type' => \NetworkTypes::NginxIngress]);
        Fixtures::ingress(array_merge(
            ['deployment_specification_id' => $deployment->deployment_specification_id],
            $ingress
        ));

        return $deployment;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function build(Deployment $deployment): array {
        return $this->manifests(IngressStep::class, $deployment);
    }

    /**
     * Every answer asserted here is reached before the step looks at the Namespace, so no
     * cluster is involved - which is why these live in the database suite.
     */
    private function validate(Deployment $deployment): ?string {
        return (new IngressStep())->validateDeployCommand($deployment);
    }

    /**
     * @return array<string, string>
     */
    private function annotations(Deployment $deployment): array {
        return $this->build($deployment)[0]['metadata']['annotations'];
    }

    // </editor-fold>

}
