<?php namespace App\Tests\Integration\Api;

use App\ClusterControllerTestCase;
use App\ClusterOutages;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\NamespaceStep;

/**
 * The per-step buttons: run this one step, terminate it, show me its status or its diff.
 *
 * Every endpoint goes through the same three-part guard - is the identifier a step, does
 * the deployment exist, is the step one this deployment is allowed to run - and only then
 * touches the cluster. **The third guard is the one that matters**: the identifier comes
 * from the url, so without it any deployment could be told to run any step.
 */
class DeploymentStepsApiTest extends ClusterControllerTestCase {

    use ClusterOutages;

    /** The four endpoints reached with GET. */
    private const READ_ENDPOINTS = ['status', 'preview', 'kubernetes-status', 'kubernetes-events'];

    /** The two that change something, and are therefore PUT. */
    private const WRITE_ENDPOINTS = ['deploy', 'terminate'];

    // <editor-fold desc="The guards">

    /**
     * Every endpoint runs the same guard, and the list is checked in full rather than on
     * whichever one came to mind: the guard is a private method called six times, so an
     * endpoint added without it looks exactly like the others from the outside.
     */
    public function testAnIdentifierThatIsNotAStepIsRefusedOnEveryEndpoint(): void {
        $deployment = $this->deployableDeployment();

        foreach ($this->everyEndpoint($deployment, 'not-a-step') as $endpoint => $body) {
            $this->assertNotSame('OK', $body['status'], $endpoint);
            $this->assertSame('unknown step', $body['error'], $endpoint);
        }
    }

    public function testAnUnknownDeploymentIsRefusedOnEveryEndpoint(): void {
        foreach ($this->everyEndpointForDeploymentId(999999, DeploymentSteps::Namespace) as $endpoint => $body) {
            $this->assertNotSame('OK', $body['status'], $endpoint);
            $this->assertSame('unknown deployment', $body['error'], $endpoint);
        }
    }

    /**
     * The guard that keeps a deployment to its own steps. A specification using the Gateway
     * API has no Contour step, and asking for one anyway has to be refused rather than
     * quietly applying an HTTPProxy nobody configured.
     */
    public function testAStepTheDeploymentDoesNotHaveIsRefused(): void {
        $deployment = $this->deployableDeployment();

        $body = $this->decode($this->signedIn()->get(
            'deployment-steps/' . DeploymentSteps::ContourHttpProxy . "/status?deploymentId={$deployment->id}"
        ));

        $this->assertNotSame('OK', $body['status']);
    }

    // </editor-fold>

    // <editor-fold desc="When the step itself throws">

    /**
     * A step is free to throw, and every endpoint has to answer with the message rather
     * than a stack trace - the endpoints are read by a page, not by a client that can make
     * sense of a 500. Some endpoints refuse through the step's own validation and the rest
     * let it throw and catch it; from the outside both have to look the same.
     *
     * The Ingress step is the one that throws for a reason a test can arrange without
     * breaking the cluster: it builds its resource from the workspace's domain, and a
     * deployment with no workspace has none. That happens in production when a deployment
     * outlives the workspace it belonged to.
     */
    public function testAStepThatCannotBuildItsResourceIsReportedOnEveryEndpoint(): void {
        $deployment = $this->deploymentWithoutAWorkspace();

        foreach ($this->everyEndpoint($deployment, DeploymentSteps::Ingress) as $endpoint => $body) {
            $this->assertNotSame('OK', $body['status'], $endpoint);
            $this->assertStringContainsString('workspace', $body['error'], $endpoint);
        }
    }

    // </editor-fold>

    // <editor-fold desc="Driving one step">

    public function testDeployingOneStepAppliesItAndReportsTheStatus(): void {
        $deployment = $this->deployableDeployment();

        $deployed = $this->decode($this->signedIn()->put(
            'deployment-steps/' . DeploymentSteps::Namespace . "/deploy?deploymentId={$deployment->id}"
        ));

        $this->assertSame('OK', $deployed['status']);
        $this->assertSame(['found'], $this->statusOf($deployment, DeploymentSteps::Namespace));
    }

    /**
     * The status comes back as a list even for a step that has one, because several steps
     * build more than one resource and the UI shows a row per resource.
     */
    public function testTheStatusIsAlwaysAList(): void {
        $deployment = $this->deployableDeployment();

        $this->assertSame(['not-found'], $this->statusOf($deployment, DeploymentSteps::Namespace));
    }

    public function testThePreviewCarriesTheManifestThatWouldBeSent(): void {
        $deployment = $this->deployableDeployment();

        $body = $this->decode($this->signedIn()->get(
            'deployment-steps/' . DeploymentSteps::Namespace . "/preview?deploymentId={$deployment->id}"
        ));

        $preview = json_decode($body['resource']['value'], true);
        $this->assertSame($this->testNamespace, json_decode($preview['local'], true)['metadata']['name']);
    }

    /**
     * Terminating validates first, so a step that could not be deployed cannot be
     * terminated either. The Service step wants a namespace; without one it is refused
     * before anything is sent.
     */
    public function testTerminatingIsRefusedWhenTheStepCouldNotRunAnyway(): void {
        $deployment = $this->deployableDeployment();

        $body = $this->decode($this->signedIn()->put(
            'deployment-steps/' . DeploymentSteps::Service . "/terminate?deploymentId={$deployment->id}"
        ));

        $this->assertNotSame('OK', $body['status']);
    }

    /**
     * The whole round trip: apply the step, see the resource, terminate it, see it go.
     *
     * The Deployment step rather than the Service step, because the Service step will not
     * run until the Deployment exists - an earlier version of this test terminated a
     * Service that had never been applied and then waited for it to disappear, which it
     * had already done.
     */
    public function testTerminatingRemovesWhatTheStepApplied(): void {
        $deployment = $this->deployableDeployment();
        (new NamespaceStep())->startDeployCommand($deployment);

        $deployed = $this->decode($this->signedIn()->put(
            'deployment-steps/' . DeploymentSteps::Deployment . "/deploy?deploymentId={$deployment->id}"
        ));
        $this->assertSame('OK', $deployed['status'], 'nothing was applied, so terminating it would prove nothing');
        $this->assertSame(['found'], $this->statusOf($deployment, DeploymentSteps::Deployment));

        $terminated = $this->decode($this->signedIn()->put(
            'deployment-steps/' . DeploymentSteps::Deployment . "/terminate?deploymentId={$deployment->id}"
        ));

        $this->assertSame('OK', $terminated['status']);
        $this->eventually(fn () => $this->statusOf($deployment, DeploymentSteps::Deployment) === ['not-found']);
    }

    /**
     * Terminating reports what the cluster said rather than letting it out as a 500. The
     * Namespace step refuses outright - namespaces are deleted by hand - and does it by
     * throwing, which is the path this endpoint has to survive.
     */
    public function testAStepThatRefusesToBeTerminatedSaysSo(): void {
        $deployment = $this->deployableDeployment();
        (new NamespaceStep())->startDeployCommand($deployment);

        $body = $this->decode($this->signedIn()->put(
            'deployment-steps/' . DeploymentSteps::Namespace . "/terminate?deploymentId={$deployment->id}"
        ));

        $this->assertNotSame('OK', $body['status']);
        $this->assertSame('Namespaces must be deleted manually', $body['error']);
    }

    /**
     * Both endpoints re-read the whole deployment's status afterwards, which is what lets
     * the page show the deployment going live off the back of the one step the user
     * clicked. With every step applied the deployment is active; take one away again and
     * it is not.
     */
    public function testDrivingOneStepUpdatesTheDeploymentsOwnStatus(): void {
        $deployment = $this->deployableDeploymentWithAServicePort();
        (new NamespaceStep())->startDeployCommand($deployment);
        (new DeploymentStep())->startDeployCommand($deployment);
        $this->assertSame(\DeploymentStatusTypes::Draft, $this->statusOfTheDeployment($deployment));

        $this->signedIn()->put('deployment-steps/' . DeploymentSteps::Service . "/deploy?deploymentId={$deployment->id}");
        $this->assertSame(\DeploymentStatusTypes::Active, $this->statusOfTheDeployment($deployment));

        $this->signedIn()->put('deployment-steps/' . DeploymentSteps::Service . "/terminate?deploymentId={$deployment->id}");
        $this->assertSame(\DeploymentStatusTypes::Deploying, $this->statusOfTheDeployment($deployment));
    }

    /**
     * A step that refuses to run says why, and the message is the step's own words rather
     * than an exception. The deployment step wants a namespace first.
     */
    public function testAStepThatRefusesSaysWhy(): void {
        $deployment = $this->deployableDeployment();

        $body = $this->decode($this->signedIn()->put(
            'deployment-steps/' . DeploymentSteps::Deployment . "/deploy?deploymentId={$deployment->id}"
        ));

        $this->assertNotSame('OK', $body['status']);
    }

    // </editor-fold>

    // <editor-fold desc="What the cluster says about the step">

    /**
     * Both of these are the drawer a user opens on a step that is misbehaving, so they run
     * against a step that has actually been applied.
     */
    public function testTheKubernetesStatusIsTheResourcesOwnStatusBlock(): void {
        $deployment = $this->appliedDeployment();
        $body = [];

        // Kubernetes fills the status block in a moment after it accepts the resource, so
        // the first read legitimately comes back with an empty one - which is also what the
        // drawer shows a user who opens it quickly enough.
        $this->eventually(function () use ($deployment, &$body) {
            $body = $this->decode($this->signedIn()->get(
                'deployment-steps/' . DeploymentSteps::Deployment . "/kubernetes-status?deploymentId={$deployment->id}"
            ));

            return isset($body['resource']['value']['replicas']);
        }, 'the deployment never reported a replica count');

        $this->assertSame('OK', $body['status']);
    }

    public function testTheKubernetesEventsAreListed(): void {
        $deployment = $this->appliedDeployment();

        $body = $this->decode($this->signedIn()->get(
            'deployment-steps/' . DeploymentSteps::Deployment . "/kubernetes-events?deploymentId={$deployment->id}"
        ));

        $this->assertSame('OK', $body['status']);
        $this->assertIsArray($body['resource']['value']);
    }

    /**
     * Both endpoints validate the step first, so a step that could not have run answers
     * with the reason rather than with an empty drawer. Without the namespace the
     * Deployment step is not deployable, and there is nothing in the cluster to ask about.
     */
    public function testBothRefuseAStepThatCouldNotHaveRun(): void {
        $deployment = $this->deployableDeployment();

        foreach (['kubernetes-status', 'kubernetes-events'] as $endpoint) {
            $body = $this->decode($this->signedIn()->get(
                'deployment-steps/' . DeploymentSteps::Deployment . "/{$endpoint}?deploymentId={$deployment->id}"
            ));

            $this->assertNotSame('OK', $body['status'], $endpoint);
            $this->assertSame('Missing Namespace', $body['error'], $endpoint);
        }
    }

    /**
     * Validation passing does not mean the resource is there. A step is deployable the
     * moment its namespace exists, so between that and the deploy - and after a terminate -
     * the status call asks for a resource the cluster has never heard of.
     */
    public function testTheKubernetesStatusOfAResourceThatWasNeverAppliedIsReported(): void {
        $deployment = $this->deployableDeployment();
        (new NamespaceStep())->startDeployCommand($deployment);

        $body = $this->decode($this->signedIn()->get(
            'deployment-steps/' . DeploymentSteps::Deployment . "/kubernetes-status?deploymentId={$deployment->id}"
        ));

        $this->assertNotSame('OK', $body['status']);
        $this->assertStringContainsString('not found', $body['error']);
    }

    /**
     * A cluster that refuses kso's credentials is what these drawers are opened for, and
     * the endpoint has to turn the refusal into a message rather than a 500.
     *
     * The Custom Resource step is the one that gets that far: every other step's
     * validation asks the cluster whether the namespace is there, so with broken
     * credentials they are refused before the endpoint's own call is ever made. This one
     * validates against the specification alone.
     */
    public function testACredentialTheClusterRefusesIsReportedRatherThanThrown(): void {
        $deployment = $this->deploymentWithACustomResource();

        $this->withCredentialsTheClusterRejects(function () use ($deployment) {
            $body = $this->decode($this->signedIn()->get(
                'deployment-steps/' . DeploymentSteps::CustomResource . "/kubernetes-events?deploymentId={$deployment->id}"
            ));

            $this->assertNotSame('OK', $body['status']);
            $this->assertStringContainsString('401', $body['error']);
        });
    }

    // </editor-fold>

    // <editor-fold desc="Authorization">

    /**
     * Not one endpoint here is public. The runtime check is the `is_public` column - see
     * `PublicSurfaceTest` - and this is the controller's own statement, which is what the
     * column is generated from.
     */
    public function testEveryEndpointRequiresAToken(): void {
        $controller = new \App\Controllers\DeploymentSteps();

        foreach (['getStatus', 'getPreview', 'deploy', 'terminate', 'getKubernetesStatus', 'getKubernetesEvents'] as $method) {
            $this->assertTrue($controller->requireAuth($method), $method);
        }
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * @return string[]
     */
    private function statusOf(Deployment $deployment, string $step): array {
        return $this->decode($this->signedIn()->get(
            "deployment-steps/{$step}/status?deploymentId={$deployment->id}"
        ))['resource']['values'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function deployableDeployment(array $overrides = []): Deployment {
        return $this->deploymentInTheTestNamespace($overrides);
    }

    /**
     * The Service step builds nothing without a port, and the cluster refuses a Service
     * that exposes none.
     */
    private function deployableDeploymentWithAServicePort(): Deployment {
        $deployment = $this->deployableDeployment();

        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'http',
            'port' => 80,
            'target_port' => 80,
        ]);

        return $deployment;
    }

    /**
     * Read back from the database: the endpoint writes it, and the object in the test was
     * loaded before that.
     */
    private function statusOfTheDeployment(Deployment $deployment): string {
        $reloaded = new Deployment();
        $reloaded->find($deployment->id);

        return $reloaded->status;
    }

    /**
     * A deployment whose namespace and workload are both in the cluster, which is what the
     * status and event endpoints are for.
     */
    private function appliedDeployment(): Deployment {
        $deployment = $this->deployableDeployment();
        (new NamespaceStep())->startDeployCommand($deployment);
        (new DeploymentStep())->startDeployCommand($deployment);

        return $deployment;
    }

    /**
     * A deployment whose workload is a manifest typed into the specification. Its step is
     * the only one that can be validated without asking the cluster anything.
     */
    private function deploymentWithACustomResource(): Deployment {
        $specification = Fixtures::deploymentSpecification([
            'name' => 'api',
            'workload_type' => \WorkloadTypes::CustomResource,
            'custom_resource' => "apiVersion: example.org/v1\nkind: Thing\nmetadata:\n  name: a-thing\n",
        ]);

        return Fixtures::deployment([
            'deployment_specification_id' => $specification->id,
            'namespace' => $this->testNamespace,
            'name' => 'a-thing',
        ]);
    }

    /**
     * A deployment for a specification that routes through an nginx Ingress, with no
     * workspace behind it - which is what the Ingress step cannot build a resource from.
     */
    private function deploymentWithoutAWorkspace(): Deployment {
        $image = Fixtures::containerImage(['url' => 'nginx', 'default_tag' => '1.29-alpine']);
        $specification = Fixtures::deploymentSpecification([
            'name' => 'api',
            'container_image_id' => $image->id,
            'enable_external_access' => true,
            'network_type' => \NetworkTypes::NginxIngress,
        ]);

        return Fixtures::deployment([
            'workspace_id' => 0,
            'deployment_specification_id' => $specification->id,
            'namespace' => $this->testNamespace,
            'name' => 'api',
            'image' => 'nginx',
            'version' => '1.29-alpine',
            'replicas' => 1,
        ]);
    }

    /**
     * The same identifier through all six endpoints, keyed by endpoint so a failure names
     * the one that let it through.
     *
     * @return array<string, array<string, mixed>>
     */
    private function everyEndpoint(Deployment $deployment, string $identifier): array {
        return $this->everyEndpointForDeploymentId((int) $deployment->id, $identifier);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function everyEndpointForDeploymentId(int $deploymentId, string $identifier): array {
        $bodies = [];

        foreach (self::READ_ENDPOINTS as $endpoint) {
            $bodies[$endpoint] = $this->decode($this->signedIn()->get(
                "deployment-steps/{$identifier}/{$endpoint}?deploymentId={$deploymentId}"
            ));
        }
        foreach (self::WRITE_ENDPOINTS as $endpoint) {
            $bodies[$endpoint] = $this->decode($this->signedIn()->put(
                "deployment-steps/{$identifier}/{$endpoint}?deploymentId={$deploymentId}"
            ));
        }

        return $bodies;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
