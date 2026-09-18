<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\GcpBackendPolicyStep;
use App\Libraries\DeploymentSteps\HealthCheckPolicyStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\ServiceStep;

/**
 * The two policies that tell a GKE gateway how to treat a workspace's backend.
 *
 * **Where this stops.** Google publishes the definitions; the controller that acts on them
 * is GKE itself, and there is none here. What the policies are *for* - a backend service
 * timeout, a health check against a serving port - happens on Google's side and nothing in
 * this suite can see it. What can be checked is everything up to that point: that the field
 * names are right, that the enums are ones the schema allows, that the target reference
 * points at the Service, and that the step creates, replaces and removes the policy when it
 * should.
 *
 * That last part is worth the most. Both steps carry a four-way status - expected or not,
 * present or not - and the interesting corner is a policy left on the cluster after the
 * setting that produced it was removed. Only an api server can put us in that state.
 */
class GkePolicyStepsTest extends ClusterTestCase {

    // <editor-fold desc="Backend policy">

    public function testTheBackendPolicyCarriesTheTimeoutAndPointsAtTheService(): void {
        $deployment = $this->gkeDeployment(['gateway_backend_timeout' => 120]);
        $step = new GcpBackendPolicyStep();

        $this->assertSame(DeploymentStepHelper::GcpBackendPolicy_NotFound, $step->getStatus($deployment));

        $step->startDeployCommand($deployment);

        $spec = $this->policy('gcpbackendpolicies', $deployment->name)['spec'];
        $this->assertSame(120, $spec['default']['timeoutSec']);
        $this->assertSame(['group' => '', 'kind' => 'Service', 'name' => $deployment->name], $spec['targetRef']);
        $this->assertSame(DeploymentStepHelper::GcpBackendPolicy_Found, $step->getStatus($deployment));
    }

    /**
     * Taking the timeout away is not the same as leaving it alone: the policy has to go, or
     * the backend service keeps the old timeout forever with nothing in kso saying so. The
     * step deletes rather than applying an empty policy.
     */
    public function testRemovingTheTimeoutRemovesThePolicy(): void {
        $deployment = $this->gkeDeployment(['gateway_backend_timeout' => 120]);
        $step = new GcpBackendPolicyStep();
        $step->startDeployCommand($deployment);

        $deployment = $this->setTimeout($deployment, 0);
        $step->startDeployCommand($deployment);

        $this->eventually(fn () => $this->policies('gcpbackendpolicies') === []);
    }

    /**
     * The corner a manifest test cannot reach: the policy is out there and the setting that
     * made it is gone. Reporting success here would mean a timeout still in force on
     * Google's side that kso no longer believes in.
     */
    public function testAPolicyLeftBehindAfterTheTimeoutIsRemovedIsReportedNotExpected(): void {
        $deployment = $this->gkeDeployment(['gateway_backend_timeout' => 120]);
        $step = new GcpBackendPolicyStep();
        $step->startDeployCommand($deployment);

        $deployment = $this->setTimeout($deployment, 0);

        $this->assertSame(DeploymentStepHelper::GcpBackendPolicy_FoundNotExpected, $step->getStatus($deployment));
        $this->assertSame($step->getSuccessStatus($deployment), DeploymentStepHelper::GcpBackendPolicy_NotFoundNotExpected);
    }

    /**
     * A workspace not behind a GKE gateway gets no policy at all, and the step has to say
     * so rather than report a missing one. Most installations are in this state.
     */
    public function testAWorkspaceNotBehindAGkeGatewayGetsNoPolicy(): void {
        $deployment = $this->gkeDeployment(['gateway_backend_timeout' => 120], ['gateway_class_name' => 'istio']);
        $step = new GcpBackendPolicyStep();

        $step->startDeployCommand($deployment);

        $this->assertSame([], $this->policies('gcpbackendpolicies'));
        $this->assertSame(DeploymentStepHelper::GcpBackendPolicy_NotFoundNotExpected, $step->getStatus($deployment));
    }

    /**
     * The preview is the diff shown before deploying. With no policy out there yet the
     * right hand side is empty, and the left is the policy that would be sent.
     */
    public function testThePreviewShowsWhatWouldBeSentAndThenWhatIsThere(): void {
        $deployment = $this->gkeDeployment(['gateway_backend_timeout' => 120]);
        $step = new GcpBackendPolicyStep();

        $before = json_decode($step->getPreview($deployment), true);
        $this->assertNull($before['remote'], 'nothing is applied yet');
        $this->assertSame(120, json_decode($before['local'], true)['spec']['default']['timeoutSec']);

        $step->startDeployCommand($deployment);

        // GKE's controller is what writes the status, and there is none here - so it is
        // written by hand, or the assertion below would hold whatever the method did.
        $this->writePolicyStatus('gcpbackendpolicies', $deployment->name, [
            'conditions' => [['type' => 'Attached', 'status' => 'True', 'reason' => 'Attached',
                'message' => 'written by the test', 'lastTransitionTime' => gmdate('Y-m-d\TH:i:s\Z')]],
        ]);

        $remote = json_decode(json_decode($step->getPreview($deployment), true)['remote'], true);
        $this->assertSame(120, $remote['spec']['default']['timeoutSec']);
        $this->assertArrayNotHasKey('status', $remote, 'what GKE wrote back is not part of the diff');

        // Everything the api server keeps for itself is stripped. Each of these changes on
        // its own, and any one left in shows as a difference in a diff that is meant to
        // show only what kso would change.
        foreach (['uid', 'resourceVersion', 'generation', 'creationTimestamp', 'managedFields'] as $field) {
            $this->assertArrayNotHasKey($field, $remote['metadata']);
        }
    }

    /**
     * A workspace that should have no policy shows nothing on the left. An empty manifest
     * there would read as "this is what will be applied", and a policy with no spec is what
     * the api server refuses - so it has to be null rather than the built object.
     */
    public function testThePreviewOffersNothingWhenNoPolicyIsWanted(): void {
        $deployment = $this->gkeDeployment();

        $this->assertNull(json_decode((new GcpBackendPolicyStep())->getPreview($deployment), true)['local']);
    }

    /**
     * The policy points at the Service by name, so deploying before the Service exists would
     * leave a policy targeting nothing. Every deployment runs this check.
     */
    public function testTheBackendPolicyIsRefusedUntilTheServiceExists(): void {
        $deployment = $this->gkeDeployment(['gateway_backend_timeout' => 120]);
        // The Service needs a port to be a valid Service at all; the policy does not care
        // which, because it targets the Service rather than one of its ports.
        Fixtures::servicePort(['deployment_specification_id' => $deployment->deployment_specification_id]);
        $step = new GcpBackendPolicyStep();

        $this->assertSame('Missing Service', $step->validateDeployCommand($deployment));

        (new ServiceStep())->startDeployCommand($deployment);

        $this->assertNull($step->validateDeployCommand($deployment));
    }

    public function testTerminatingRemovesTheBackendPolicy(): void {
        $deployment = $this->gkeDeployment(['gateway_backend_timeout' => 30]);
        $step = new GcpBackendPolicyStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::GcpBackendPolicy_NotFound
        );
    }

    // </editor-fold>

    // <editor-fold desc="Health check policy">

    public function testAnHttpHealthCheckAsksForThePathOnTheServingPort(): void {
        $deployment = $this->gkeDeployment();
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'health_check_type' => \HealthCheckTypes::Http,
            'health_check_path' => '/healthz',
        ]);
        $step = new HealthCheckPolicyStep();

        $step->startDeployCommand($deployment);

        $policy = $this->policy('healthcheckpolicies', $deployment->name);
        $config = $policy['spec']['default']['config'];
        $this->assertSame('HTTP', $config['type']);
        $this->assertSame('/healthz', $config['httpHealthCheck']['requestPath']);
        $this->assertSame('USE_SERVING_PORT', $config['httpHealthCheck']['portSpecification']);
        $this->assertSame(DeploymentStepHelper::HealthCheckPolicy_Found, $step->getStatus($deployment));

        // kso marks what it applies. Nothing else distinguishes a policy kso owns from one
        // an operator wrote by hand into the same namespace.
        $this->assertSame(
            '4spaces.kso',
            $policy['metadata']['annotations']['app.kubernetes.io/managed-by'] ?? null
        );
    }

    public function testAnHttpCheckWithoutAPathAsksForTheRoot(): void {
        $deployment = $this->gkeDeployment();
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'health_check_type' => \HealthCheckTypes::Http,
            'health_check_path' => '',
        ]);

        (new HealthCheckPolicyStep())->startDeployCommand($deployment);

        $config = $this->policy('healthcheckpolicies', $deployment->name)['spec']['default']['config'];
        $this->assertSame('/', $config['httpHealthCheck']['requestPath']);
    }

    /**
     * A TCP check does not carry a path, and a policy that sent one under `tcpHealthCheck`
     * would be refused by the schema. One policy covers the whole service, so when the
     * ports disagree the coarser check wins.
     */
    public function testTcpWinsOverHttpWhenThePortsDisagree(): void {
        $deployment = $this->gkeDeployment();
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'web',
            'health_check_type' => \HealthCheckTypes::Http,
        ]);
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'db',
            'port' => 5432,
            'target_port' => 5432,
            'health_check_type' => \HealthCheckTypes::Tcp,
        ]);

        (new HealthCheckPolicyStep())->startDeployCommand($deployment);

        $config = $this->policy('healthcheckpolicies', $deployment->name)['spec']['default']['config'];
        $this->assertSame('TCP', $config['type']);
        $this->assertArrayNotHasKey('httpHealthCheck', $config);
    }

    public function testAServiceWithNoHealthCheckedPortGetsNoPolicy(): void {
        $deployment = $this->gkeDeployment();
        $step = new HealthCheckPolicyStep();

        $step->startDeployCommand($deployment);

        $this->assertSame([], $this->policies('healthcheckpolicies'));
        $this->assertSame(DeploymentStepHelper::HealthCheckPolicy_NotFoundNotExpected, $step->getStatus($deployment));
    }

    /**
     * The corner a manifest test cannot reach: the policy is out there and no port asks for
     * one any more. Leaving it would keep GKE health checking the service the old way, with
     * nothing in kso saying so - so the step deletes rather than applying an empty policy.
     */
    public function testTakingTheHealthCheckOffThePortsRemovesThePolicy(): void {
        $deployment = $this->gkeDeployment();
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'health_check_type' => \HealthCheckTypes::Tcp,
        ]);
        $step = new HealthCheckPolicyStep();
        $step->startDeployCommand($deployment);
        $this->assertNotSame([], $this->policies('healthcheckpolicies'));

        $deployment = $this->clearTheHealthChecks($deployment);
        $step->startDeployCommand($deployment);

        $this->eventually(fn () => $this->policies('healthcheckpolicies') === []);
        $this->assertSame(DeploymentStepHelper::HealthCheckPolicy_NotFoundNotExpected, $step->getSuccessStatus($deployment));
    }

    /**
     * The same state, read rather than written: a policy still on the cluster after the last
     * health checked port was taken away. Reporting it as found would mean kso believing in
     * a check it no longer configures.
     */
    public function testAPolicyLeftBehindAfterTheChecksAreRemovedIsReportedNotExpected(): void {
        $deployment = $this->gkeDeployment();
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'health_check_type' => \HealthCheckTypes::Tcp,
        ]);
        $step = new HealthCheckPolicyStep();
        $step->startDeployCommand($deployment);

        $deployment = $this->clearTheHealthChecks($deployment);

        $this->assertSame(DeploymentStepHelper::HealthCheckPolicy_FoundNotExpected, $step->getStatus($deployment));
    }

    public function testTheHealthCheckPreviewShowsWhatWouldBeSentAndThenWhatIsThere(): void {
        $deployment = $this->gkeDeployment();
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'health_check_type' => \HealthCheckTypes::Http,
            'health_check_path' => '/healthz',
        ]);
        $step = new HealthCheckPolicyStep();

        $before = json_decode($step->getPreview($deployment), true);
        $this->assertNull($before['remote'], 'nothing is applied yet');
        $this->assertSame(
            '/healthz',
            json_decode($before['local'], true)['spec']['default']['config']['httpHealthCheck']['requestPath']
        );

        $step->startDeployCommand($deployment);

        // As above: no controller here writes a status, so one is written by hand.
        $this->writePolicyStatus('healthcheckpolicies', $deployment->name, [
            'conditions' => [['type' => 'Attached', 'status' => 'True', 'reason' => 'Attached',
                'message' => 'written by the test', 'lastTransitionTime' => gmdate('Y-m-d\TH:i:s\Z')]],
        ]);

        $remote = json_decode(json_decode($step->getPreview($deployment), true)['remote'], true);
        $this->assertSame('HTTP', $remote['spec']['default']['config']['type']);
        $this->assertArrayNotHasKey('status', $remote, 'what GKE wrote back is not part of the diff');

        // Everything the api server keeps for itself is stripped, not just the uid: each of
        // these changes on its own and would show as a difference in a diff that is meant
        // to show what kso would change.
        foreach (['uid', 'resourceVersion', 'generation', 'creationTimestamp', 'managedFields'] as $field) {
            $this->assertArrayNotHasKey($field, $remote['metadata']);
        }
    }

    public function testTheHealthCheckPreviewOffersNothingWhenNoPolicyIsWanted(): void {
        $deployment = $this->gkeDeployment();

        $this->assertNull(json_decode((new HealthCheckPolicyStep())->getPreview($deployment), true)['local']);
    }

    public function testTheHealthCheckPolicyIsRefusedUntilTheServiceExists(): void {
        $deployment = $this->gkeDeployment();
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'health_check_type' => \HealthCheckTypes::Tcp,
        ]);
        $step = new HealthCheckPolicyStep();

        $this->assertSame('Missing Service', $step->validateDeployCommand($deployment));

        (new ServiceStep())->startDeployCommand($deployment);

        $this->assertNull($step->validateDeployCommand($deployment));
    }

    public function testTerminatingRemovesTheHealthCheckPolicy(): void {
        $deployment = $this->gkeDeployment();
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'health_check_type' => \HealthCheckTypes::Tcp,
        ]);
        $step = new HealthCheckPolicyStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::HealthCheckPolicy_NotFound
        );
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * A deployment behind a GKE gateway - which is the only arrangement either step builds
     * anything for.
     *
     * @param array<string, mixed> $specification
     * @param array<string, mixed> $gateway
     */
    private function gkeDeployment(array $specification = [], array $gateway = []): Deployment {
        $gatewayRow = Fixtures::gateway(array_merge(
            ['gateway_class_name' => 'gke-l7-regional-external-managed'],
            $gateway
        ));
        $domain = Fixtures::domain(['gateway_id' => $gatewayRow->id]);

        $deployment = $this->deploymentInTheTestNamespace([], ['domain_id' => $domain->id]);

        $spec = $deployment->findDeploymentSpecification();
        $spec->network_type = \NetworkTypes::GatewayApi;
        foreach ($specification as $field => $value) {
            $spec->$field = $value;
        }
        $spec->save();

        (new NamespaceStep())->startDeployCommand($deployment);

        return $deployment;
    }

    /**
     * Written straight to the table: the entity was read before the step ran, and saving a
     * stale one back writes nothing when the in-memory value has not changed.
     */
    private function setTimeout(Deployment $deployment, int $seconds): Deployment {
        db_connect()
            ->table('deployment_specifications')
            ->where('id', $deployment->deployment_specification_id)
            ->update(['gateway_backend_timeout' => $seconds]);

        // A fresh entity, because the deployment is holding the specification it read
        // before the update and every step asks it, not the table.
        $fresh = new Deployment();
        $fresh->find($deployment->id);

        return $fresh;
    }

    /**
     * Take the health check off every port, the way the specification's editor would.
     *
     * Written straight to the table for the same reason `setTimeout()` is: the step reads
     * the ports back through the specification, and the rows were loaded before it ran.
     */
    private function clearTheHealthChecks(Deployment $deployment): Deployment {
        db_connect()
            ->table('deployment_specification_service_ports')
            ->where('deployment_specification_id', $deployment->deployment_specification_id)
            // Empty rather than null: the column is not nullable, and empty is what the
            // migration calls "no opinion".
            ->update(['health_check_type' => '']);

        $fresh = new Deployment();
        $fresh->find($deployment->id);

        return $fresh;
    }

    /**
     * Write a policy's status the way GKE's controller would.
     *
     * Both CRDs declare a `status` subresource, so the field is dropped on an ordinary write
     * and has to go to `/status` on its own. The object is read back first because that
     * endpoint takes a whole resource and rejects one without the current
     * `metadata.resourceVersion`.
     *
     * @param array<string, mixed> $status
     */
    private function writePolicyStatus(string $plural, string $name, array $status): void {
        $path = "/apis/networking.gke.io/v1/namespaces/{$this->testNamespace}/{$plural}/{$name}";

        $policy = $this->get($path);
        $policy['status'] = $status;

        $this->cluster()->call('PUT', $path . '/status', json_encode($policy));
    }

    /**
     * @return array<string, mixed>
     */
    private function policy(string $plural, string $name): array {
        return $this->get("/apis/networking.gke.io/v1/namespaces/{$this->testNamespace}/{$plural}/{$name}");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function policies(string $plural): array {
        return $this->get("/apis/networking.gke.io/v1/namespaces/{$this->testNamespace}/{$plural}")['items'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array {
        return json_decode($this->cluster()->call('GET', $path)->getBody()->getContents(), true);
    }

    // </editor-fold>

}
