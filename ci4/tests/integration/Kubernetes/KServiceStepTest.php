<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\KServiceStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\ServiceAccountStep;

/**
 * The Knative workload: one manifest that stands for a Deployment, a Service and an
 * autoscaler at once.
 *
 * Knative's schema is the strictest kso sends anything to. It rejects things plain
 * Kubernetes accepts - a second container, a second port, an autoscaling annotation that
 * is not a string - and every one of those rules lives in the CRD rather than in kso. The
 * definitions are installed in the test cluster; nothing runs a revision, and nothing
 * needs to.
 */
class KServiceStepTest extends ClusterTestCase {

    // <editor-fold desc="Applying">

    public function testAKServiceIsCreatedWithTheImageAndVersionAskedFor(): void {
        $deployment = $this->kserviceDeployment(['version' => '1.29-alpine']);
        $step = new KServiceStep();

        $this->assertSame(DeploymentStepHelper::KService_NotFound, $step->getStatus($deployment));

        $step->startDeployCommand($deployment);

        $this->assertSame(DeploymentStepHelper::KService_Found, $step->getStatus($deployment));
        $container = $this->kservice($deployment)['spec']['template']['spec']['containers'][0];
        $this->assertSame('nginx:1.29-alpine', $container['image']);
        $this->assertSame($deployment->name, $container['name']);
    }

    /**
     * Scale to zero is the point of Knative, and `minScale` is what stops it. The value is
     * an annotation, and Kubernetes annotations are strings - a number here is refused by
     * the api server before Knative ever sees it.
     */
    public function testTheMinimumScaleIsCarriedAsAnAnnotation(): void {
        $deployment = $this->kserviceDeployment();

        (new KServiceStep())->startDeployCommand($deployment);

        $annotations = $this->kservice($deployment)['spec']['template']['metadata']['annotations'];
        $this->assertArrayHasKey('autoscaling.knative.dev/minScale', $annotations);
        $this->assertIsString($annotations['autoscaling.knative.dev/minScale']);
    }

    /**
     * The two concurrency limits are different mechanisms with similar names: the soft one
     * is an autoscaling target and the hard one a cap Knative enforces per pod. They end up
     * in different places in the manifest, and only the api server says whether either was
     * put somewhere the schema accepts.
     */
    public function testTheSoftLimitIsATargetAndTheHardLimitIsACap(): void {
        $deployment = $this->kserviceDeployment([
            'knative_concurrency_limit_soft' => 40,
            'knative_concurrency_limit_hard' => 80,
        ]);

        (new KServiceStep())->startDeployCommand($deployment);

        $template = $this->kservice($deployment)['spec']['template'];
        $this->assertSame('40', $template['metadata']['annotations']['autoscaling.knative.dev/target']);
        $this->assertSame(80, $template['spec']['containerConcurrency']);
    }

    public function testResourceRequestsAndLimitsCarryTheirUnits(): void {
        $deployment = $this->kserviceDeployment([
            'cpu_request' => 100,
            'cpu_limit' => 500,
            'memory_request' => 128,
            'memory_limit' => 512,
        ]);

        (new KServiceStep())->startDeployCommand($deployment);

        $resources = $this->kservice($deployment)['spec']['template']['spec']['containers'][0]['resources'];
        $this->assertSame(['cpu' => '100m', 'memory' => '128Mi'], $resources['requests']);
        $this->assertSame(['cpu' => '500m', 'memory' => '512Mi'], $resources['limits']);
    }

    /**
     * Deploying twice reads the applied resource back first, to keep the creator annotation
     * Knative wrote. That read is the only reason this method touches the cluster before it
     * applies, and it is the path a version change takes every time.
     */
    public function testDeployingTwiceUpdatesRatherThanFails(): void {
        $deployment = $this->kserviceDeployment(['version' => '1.29-alpine']);
        $step = new KServiceStep();
        $step->startDeployCommand($deployment);

        $deployment->version = '1.28-alpine';
        $deployment->save();
        $step->startDeployCommand($deployment);

        $this->assertSame(
            'nginx:1.28-alpine',
            $this->kservice($deployment)['spec']['template']['spec']['containers'][0]['image']
        );
    }

    /**
     * Knative stamps the KService with who created it, and kso sends a whole object on
     * every update - so without reading that stamp back first, the second deploy would
     * wipe it. Nothing in this cluster writes it, since no Knative controller is running,
     * so the test writes it the way Knative would and then deploys again.
     */
    public function testTheCreatorKnativeStampedOnItSurvivesTheNextDeploy(): void {
        $deployment = $this->kserviceDeployment();
        $step = new KServiceStep();
        $step->startDeployCommand($deployment);
        $this->stampCreator($deployment, 'someone@example.org');

        $step->startDeployCommand($deployment);

        $this->assertSame(
            'someone@example.org',
            $this->kservice($deployment)['metadata']['annotations']['serving.knative.dev/creator']
        );
    }

    public function testTerminatingRemovesTheKService(): void {
        $deployment = $this->kserviceDeployment();
        $step = new KServiceStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::KService_NotFound
        );
    }

    /**
     * Where this suite's reach ends, stated as a test.
     *
     * kso adds one container port per http proxy route, and a Knative revision may have
     * only one. That rule lives in Knative's **admission webhook**, not in its CRD schema -
     * and the test cluster installs definitions, not controllers. So the api server takes
     * the manifest, and this test can say what was sent but not what a real Knative would
     * answer.
     *
     * Worth having anyway: it pins the shape kso builds, and it is where to start if a
     * two-route Knative specification ever fails in production.
     */
    public function testASecondHttpRouteAddsASecondPortTheSchemaDoesNotRefuse(): void {
        $deployment = $this->kserviceDeployment();
        Fixtures::httpProxyRoute([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'path' => '/admin',
            'port' => 9090,
        ]);

        (new KServiceStep())->startDeployCommand($deployment);

        $ports = $this->kservice($deployment)['spec']['template']['spec']['containers'][0]['ports'];
        $this->assertSame([8080, 9090], array_column($ports, 'containerPort'));
    }

    /**
     * The same collision as FEAT-13, seen from the other end: both volume sources name
     * their volume after the deployment, so a deployment with one of each builds a template
     * with two volumes of one name. A pod spec cannot have that.
     */
    public function testTwoVolumesBuildATemplateWithOneNameTwice(): void {
        $deployment = $this->kserviceDeployment();
        $specification = $deployment->findDeploymentSpecification();
        $specification->enable_volumes = true;
        $specification->save();
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id, 'mount_path' => '/data']);
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'mount_path' => '/other',
        ]);

        $this->expectException(\RenokiCo\PhpK8s\Exceptions\KubernetesAPIException::class);

        (new KServiceStep())->startDeployCommand($deployment);
    }

    /**
     * A deploy carries the reason it happened, so `kubectl rollout history` and the UI both
     * say what caused a revision. The annotation is only written when there is a reason.
     */
    public function testTheReasonForADeployIsRecordedOnTheKService(): void {
        $deployment = $this->kserviceDeployment();
        $step = new KServiceStep();

        $step->startDeployCommand($deployment, 'version bumped to 1.29-alpine');

        $this->assertSame(
            'version bumped to 1.29-alpine',
            $this->kservice($deployment)['metadata']['annotations']['kubernetes.io/change-cause']
        );
    }

    public function testADeployWithoutAReasonLeavesTheChangeCauseAlone(): void {
        $deployment = $this->kserviceDeployment();

        (new KServiceStep())->startDeployCommand($deployment);

        $this->assertArrayNotHasKey(
            'kubernetes.io/change-cause',
            $this->kservice($deployment)['metadata']['annotations']
        );
    }

    // </editor-fold>

    // <editor-fold desc="Reading back">

    /**
     * Nothing applied yet, so the preview has a local half and no remote one. The KService
     * preview strips more than most: Knative writes two annotations of its own on the way
     * in, and both would show up as a change nobody made.
     */
    public function testAPreviewBeforeAnythingIsAppliedHasNoRemote(): void {
        $deployment = $this->kserviceDeployment();

        $preview = $this->preview($deployment);

        $this->assertNull($preview['remote']);
        $this->assertSame($deployment->name, json_decode($preview['local'], true)['metadata']['name']);
    }

    public function testAPreviewAfterApplyingShowsTheChangeAboutToBeMade(): void {
        $deployment = $this->kserviceDeployment(['version' => '1.29-alpine']);
        $step = new KServiceStep();
        $step->startDeployCommand($deployment);

        $deployment->version = '1.28-alpine';
        $deployment->save();
        $preview = $this->preview($deployment);

        $remote = json_decode($preview['remote'], true);
        $local = json_decode($preview['local'], true);
        $this->assertSame('nginx:1.29-alpine', $remote['spec']['template']['spec']['containers'][0]['image']);
        $this->assertSame('nginx:1.28-alpine', $local['spec']['template']['spec']['containers'][0]['image']);

        foreach (['uid', 'resourceVersion', 'creationTimestamp', 'generation'] as $noise) {
            $this->assertArrayNotHasKey($noise, $remote['metadata'], "metadata.$noise should not be in a preview");
        }
        $this->assertArrayNotHasKey('status', $remote);
    }

    /**
     * `getKubernetesStatus()` hands the UI whatever Knative wrote. No Knative controller
     * runs in this cluster, so the status is written here the way one would - through the
     * status subresource, which is the only way it can be set from outside.
     */
    public function testTheStatusKnativeWroteIsWhatTheStepReportsBack(): void {
        $deployment = $this->kserviceDeployment();
        $step = new KServiceStep();
        $step->startDeployCommand($deployment);
        $this->stampStatus($deployment, [
            'observedGeneration' => 1,
            'conditions' => [
                ['type' => 'Ready', 'status' => 'True'],
            ],
        ]);

        $status = $step->getKubernetesStatus($deployment);

        $this->assertSame(1, $status['observedGeneration']);
        $this->assertSame('Ready', $status['conditions'][0]['type']);
    }

    /**
     * **A bug, pinned rather than fixed.** Between a KService being created and its
     * controller writing a status there is no `status` at all, and the method is declared
     * to return an array - so asking for the status of a revision that has only just been
     * applied throws a TypeError rather than answering "nothing yet". The UI asks for the
     * status of exactly the workload that was just deployed, which is when this window is
     * open. See the report that goes with this test.
     */
    public function testAskingForTheStatusBeforeThereIsOneThrows(): void {
        $deployment = $this->kserviceDeployment();
        $step = new KServiceStep();
        $step->startDeployCommand($deployment);

        $this->expectException(\TypeError::class);

        $step->getKubernetesStatus($deployment);
    }

    /**
     * Events are how a revision that will not start explains itself, and they are the one
     * thing in the UI that comes straight from the cluster rather than from kso. Nothing
     * generates one here - no controller runs - so the test writes the event a controller
     * would and checks that the step finds it and reshapes it.
     */
    public function testEventsAboutTheKServiceAreReportedWithTheirSourceAndTime(): void {
        $deployment = $this->kserviceDeployment();
        $step = new KServiceStep();
        $step->startDeployCommand($deployment);
        $this->recordEvent($deployment->name, [
            'count' => 3,
            'type' => 'Warning',
            'reason' => 'RevisionFailed',
            'message' => 'Revision did not become ready',
            'lastTimestamp' => '2026-09-17T10:00:00Z',
            'source' => ['component' => 'knative-serving'],
        ]);

        $events = $step->getKubernetesEvents($deployment);

        $this->assertCount(1, $events);
        $this->assertSame([
            'count' => 3,
            'type' => 'Warning',
            'reason' => 'RevisionFailed',
            'date' => date('Y-m-d H:i:s', strtotime('2026-09-17T10:00:00Z')),
            'from' => 'knative-serving',
            'message' => 'Revision did not become ready',
        ], $events[0]);
    }

    public function testAKServiceNothingHasHappenedToHasNoEvents(): void {
        $deployment = $this->kserviceDeployment();
        $step = new KServiceStep();
        $step->startDeployCommand($deployment);

        $this->assertSame([], $step->getKubernetesEvents($deployment));
    }

    // </editor-fold>

    // <editor-fold desc="Refusals">

    public function testTheStepRefusesWithoutANamespace(): void {
        $deployment = $this->kserviceDeployment(applyNamespace: false);

        $this->assertSame('Missing Namespace', (new KServiceStep())->validateDeployCommand($deployment));
    }

    public function testTheStepRefusesWithoutAVersion(): void {
        $deployment = $this->kserviceDeployment(['version' => '']);

        $this->assertSame('Missing version', (new KServiceStep())->validateDeployCommand($deployment));
    }

    /**
     * Everything the step asks for, present. The namespace check needs a cluster, so this
     * is the only place the rest of `validateDeployCommand()` can be reached at all.
     */
    public function testTheStepIsHappyWhenEverythingItAsksForIsThere(): void {
        $deployment = $this->kserviceDeployment();

        $this->assertNull((new KServiceStep())->validateDeployCommand($deployment));
    }

    /**
     * A specification that is reachable from outside needs a domain to be reachable *at*,
     * and the domain hangs off the workspace rather than the deployment. The url is baked
     * into the revision as BASE_URL, so a missing domain is not something to find out about
     * afterwards.
     */
    public function testExternalAccessNeedsAWorkspaceDomain(): void {
        $deployment = $this->kserviceDeployment(externalAccess: true);
        $workspace = $deployment->workspace;
        $workspace->domain_id = 0;
        $workspace->save();

        $this->assertSame('Missing workspace domain', (new KServiceStep())->validateDeployCommand($deployment));
    }

    /**
     * The id can outlive the row, and the two messages are different on purpose: one means
     * nobody chose a domain, the other means the one that was chosen has been deleted.
     */
    public function testExternalAccessNeedsTheDomainToStillExist(): void {
        $deployment = $this->kserviceDeployment(externalAccess: true);
        $workspace = $deployment->workspace;
        $workspace->domain_id = 999999;
        $workspace->save();

        $this->assertSame('domain no longer exists', (new KServiceStep())->validateDeployCommand($deployment));
    }

    public function testExternalAccessIsAllowedWithADomain(): void {
        $deployment = $this->kserviceDeployment(externalAccess: true);

        $this->assertNull((new KServiceStep())->validateDeployCommand($deployment));
    }

    /**
     * A specification with a database expects the DatabaseStep to have run first. Starting
     * the revision without it gives a container whose database credentials are empty
     * strings, and it fails on its first query rather than at deploy time.
     */
    public function testASpecificationWithADatabaseNeedsOneToHaveBeenCreated(): void {
        $step = new KServiceStep();

        $deployment = $this->kserviceDeployment(withDatabase: true);
        $deployment->database_service_id = 0;
        $deployment->save();
        $this->assertSame('Missing database service', $step->validateDeployCommand($deployment));

        $deployment->database_service_id = 999999;
        $deployment->save();
        $this->assertSame('Database service no longer exists', $step->validateDeployCommand($deployment));

        $deployment->database_service_id = Fixtures::databaseService()->id;
        $deployment->save();
        $this->assertSame('Missing Database', $step->validateDeployCommand($deployment));

        $deployment->database_name = 'customer_api';
        $deployment->database_user = 'customer_api';
        $deployment->database_pass = 'a-password';
        $deployment->save();
        $this->assertNull($step->validateDeployCommand($deployment));
    }

    /**
     * With RBAC on, the revision is told to run as a service account, and a pod naming one
     * that does not exist is rejected by the api server when it is scheduled - long after
     * the deploy reported success.
     */
    public function testASpecificationWithRbacNeedsItsServiceAccountFirst(): void {
        $deployment = $this->kserviceDeployment(withRbac: true);
        $step = new KServiceStep();

        $this->assertSame('Missing Service Account', $step->validateDeployCommand($deployment));

        (new ServiceAccountStep())->startDeployCommand($deployment);

        $this->assertNull($step->validateDeployCommand($deployment));
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * @param array<string, mixed> $overrides
     */
    private function kserviceDeployment(
        array $overrides = [],
        bool $applyNamespace = true,
        bool $externalAccess = false,
        bool $withDatabase = false,
        bool $withRbac = false
    ): Deployment {
        $gateway = Fixtures::gateway();
        $domain = Fixtures::domain(['gateway_id' => $gateway->id]);

        $deployment = $this->deploymentInTheTestNamespace(
            array_merge(['environment' => \Environments::Production], $overrides),
            ['domain_id' => $domain->id]
        );

        $specification = $deployment->findDeploymentSpecification();
        $specification->workload_type = \WorkloadTypes::KNativeService;
        $specification->enable_external_access = $externalAccess;
        $specification->enable_database = $withDatabase;
        $specification->enable_rbac = $withRbac;
        $specification->save();

        Fixtures::httpProxyRoute([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'path' => '/',
            'port' => 8080,
        ]);

        if ($applyNamespace) {
            (new NamespaceStep())->startDeployCommand($deployment);
        }

        return $deployment;
    }

    /**
     * What a running Knative does to a KService the moment it is created.
     */
    private function stampCreator(Deployment $deployment, string $who): void {
        // Read, change, write the whole object back. A merge patch would be shorter, but
        // php-k8s's `call()` sends no content type and the api server refuses a PATCH
        // without one.
        $resource = $this->kservice($deployment);
        $resource['metadata']['annotations']['serving.knative.dev/creator'] = $who;

        $this->cluster()->call(
            'PUT',
            "/apis/serving.knative.dev/v1/namespaces/{$this->testNamespace}/services/{$deployment->name}",
            json_encode($resource)
        );
    }

    /**
     * The status a running Knative would write. It goes through the status subresource,
     * which is the only door into `status` from outside a controller - a PUT to the
     * resource itself is accepted and the status silently dropped.
     *
     * @param array<string, mixed> $status
     */
    private function stampStatus(Deployment $deployment, array $status): void {
        $resource = $this->kservice($deployment);
        $resource['status'] = $status;

        $this->cluster()->call(
            'PUT',
            "/apis/serving.knative.dev/v1/namespaces/{$this->testNamespace}/services/{$deployment->name}/status",
            json_encode($resource)
        );
    }

    /**
     * The event a controller would record against the KService. `getEvents()` finds it by
     * involvedObject, so the kind and the name are what make it belong to this resource.
     *
     * @param array<string, mixed> $attributes
     */
    private function recordEvent(string $resourceName, array $attributes): void {
        $event = $this->cluster()->event()
            ->setName($resourceName . '.' . bin2hex(random_bytes(4)))
            ->setNamespace($this->testNamespace)
            ->setAttribute('involvedObject', [
                'kind' => 'Service',
                'name' => $resourceName,
                'namespace' => $this->testNamespace,
                'apiVersion' => 'serving.knative.dev/v1',
            ]);

        foreach ($attributes as $key => $value) {
            $event->setAttribute($key, $value);
        }

        $event->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function preview(Deployment $deployment): array {
        return json_decode((new KServiceStep())->getPreview($deployment), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function kservice(Deployment $deployment): array {
        return json_decode($this->cluster()->call(
            'GET',
            "/apis/serving.knative.dev/v1/namespaces/{$this->testNamespace}/services/{$deployment->name}"
        )->getBody()->getContents(), true);
    }

    // </editor-fold>

}
