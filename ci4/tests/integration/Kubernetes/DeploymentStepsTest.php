<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Fixtures;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\ServiceAccountStep;
use App\Libraries\DeploymentSteps\ServiceStep;

/**
 * The half of a deployment step that a manifest test cannot reach.
 *
 * Every step is covered for what it builds. What none of those tests can say is whether
 * the api server accepts it, whether the resource appears, whether `getStatus()` then
 * finds it, and whether terminating takes it away again. Those four methods are most of
 * each step's code and all of its contact with the outside.
 *
 * Runs against a throwaway cluster only - see `ClusterTestCase`. Each test gets a
 * namespace of its own and removes it afterwards.
 */
class DeploymentStepsTest extends ClusterTestCase {

    public function testANamespaceIsCreatedAndReportedAsFound(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        $step = new NamespaceStep();

        $this->assertSame(
            DeploymentStepHelper::Namespace_NotFound,
            $step->getStatus($deployment),
            'nothing should exist yet'
        );

        $step->startDeployCommand($deployment);

        $this->assertSame(DeploymentStepHelper::Namespace_Found, $step->getStatus($deployment));
        $this->assertTrue($this->cluster()->namespace()->whereName($this->testNamespace)->exists());
    }

    /**
     * The namespace step refuses to terminate on purpose: namespaces are deleted by hand,
     * because everything a customer has lives inside one.
     */
    public function testANamespaceIsNotTerminatedByTheStep(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        $step = new NamespaceStep();
        $step->startDeployCommand($deployment);

        $error = $step->tryExecuteTerminateCommand($deployment);

        $this->assertNotNull($error, 'terminating a namespace should be refused');
        $this->assertTrue($this->cluster()->namespace()->whereName($this->testNamespace)->exists());
    }

    public function testADeploymentIsCreatedWithTheImageAndReplicasWeAskedFor(): void {
        $deployment = $this->deploymentInTheTestNamespace(['replicas' => 2, 'version' => '1.29-alpine']);
        (new NamespaceStep())->startDeployCommand($deployment);
        $step = new DeploymentStep();

        $step->startDeployCommand($deployment);

        $applied = $this->cluster()->getDeploymentByName($deployment->name, $this->testNamespace);
        $this->assertSame(2, $applied->getReplicas());
        $this->assertStringContainsString(
            '1.29-alpine',
            $applied->getAttribute('spec.template.spec.containers')[0]['image']
        );
    }

    /**
     * Deploying twice is the ordinary case - it is what a version change does - and it has
     * to update rather than fail on a name that is already taken.
     */
    public function testDeployingTwiceUpdatesRatherThanFails(): void {
        $deployment = $this->deploymentInTheTestNamespace(['replicas' => 1]);
        (new NamespaceStep())->startDeployCommand($deployment);
        $step = new DeploymentStep();
        $step->startDeployCommand($deployment);

        $deployment->replicas = 3;
        $deployment->save();
        $step->startDeployCommand($deployment);

        $this->assertSame(3, $this->cluster()->getDeploymentByName($deployment->name, $this->testNamespace)->getReplicas());
    }

    public function testTerminatingRemovesTheDeployment(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        (new NamespaceStep())->startDeployCommand($deployment);
        $step = new DeploymentStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => !$this->cluster()->deployment()->whereName($deployment->name)->whereNamespace($this->testNamespace)->exists()
        );
    }

    /**
     * A Service is what puts the pods on the network, and the selector is the part that
     * cannot be checked without applying it: the api server accepts any selector, matching
     * or not.
     */
    public function testAServiceIsCreatedWithASelectorThatMatchesTheDeployment(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        (new NamespaceStep())->startDeployCommand($deployment);
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'http',
            'port' => 80,
            'target_port' => 80,
        ]);

        (new ServiceStep())->startDeployCommand($deployment);

        $service = $this->cluster()->getServiceByName($deployment->name, $this->testNamespace);
        $this->assertSame(['app' => $deployment->name], $service->getSelectors());
    }

    /**
     * The status the UI shows. Before anything is applied it has to say so, or a workspace
     * looks deployed when it is not.
     */
    public function testStatusIsNotFoundBeforeAnythingIsApplied(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        (new NamespaceStep())->startDeployCommand($deployment);

        $this->assertNotSame(
            (new DeploymentStep())->getSuccessStatus($deployment),
            (new DeploymentStep())->getStatus($deployment)
        );
    }

    /**
     * `validateDeployCommand()` is what stops a step from being run at all. The deployment
     * step wants a namespace to exist first - applying into one that is not there fails
     * with an error nobody reads.
     */
    public function testTheDeploymentStepRefusesWithoutANamespace(): void {
        $deployment = $this->deploymentInTheTestNamespace();

        $this->assertNotNull((new DeploymentStep())->validateDeployCommand($deployment));
    }

    /**
     * A deploy carries a reason, and it is written to the Deployment as the change cause -
     * the annotation `kubectl rollout history` reads. It is the only place the *why* of a
     * rollout survives; without it every revision looks the same from inside the cluster.
     */
    public function testAReasonIsRecordedOnTheDeploymentAsTheChangeCause(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        (new NamespaceStep())->startDeployCommand($deployment);

        (new DeploymentStep())->startDeployCommand($deployment, 'version updated to 1.29-alpine');

        $annotations = $this->cluster()
            ->getDeploymentByName($deployment->name, $this->testNamespace)
            ->getAnnotations();
        $this->assertSame('version updated to 1.29-alpine', $annotations['kubernetes.io/change-cause']);
    }

    /**
     * Deploying without a reason leaves the annotation off rather than writing an empty
     * one, and the pod template still gets a fresh update time - which is what makes
     * Kubernetes roll the pods even when nothing else in the template changed.
     */
    public function testDeployingWithoutAReasonStillRollsThePods(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        (new NamespaceStep())->startDeployCommand($deployment);

        (new DeploymentStep())->startDeployCommand($deployment);

        $applied = $this->cluster()->getDeploymentByName($deployment->name, $this->testNamespace);
        $this->assertArrayNotHasKey('kubernetes.io/change-cause', $applied->getAnnotations());
        $this->assertArrayHasKey(
            '4spaces.kso/update-time',
            $applied->getAttribute('spec.template.metadata.annotations')
        );
    }

    /**
     * The checks that need the cluster, in the order the step makes them. Each one is a
     * step earlier in the chain that has not finished, and applying the workload anyway
     * would produce pods that cannot reach their database or have no permissions.
     */
    public function testTheStepRefusesUntilTheStepsItDependsOnAreDone(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        $specification = $deployment->findDeploymentSpecification();
        $specification->enable_database = true;
        $specification->enable_rbac = true;
        $specification->save();
        $step = new DeploymentStep();

        $this->assertSame('Missing Namespace', $step->validateDeployCommand($deployment));

        (new NamespaceStep())->startDeployCommand($deployment);
        $this->assertSame('Missing database service', $step->validateDeployCommand($deployment));

        // A database service that was removed after this deployment was pointed at it. The
        // id stays on the row, so the only way to find out is to go and look.
        $deployment->database_service_id = 99999;
        $this->assertSame('Database service no longer exists', $step->validateDeployCommand($deployment));

        $deployment->database_service_id = Fixtures::databaseService()->id;
        $this->assertSame('Missing Database', $step->validateDeployCommand($deployment));

        // What `DatabaseStep` writes back when it has created the schema.
        $deployment->database_name = 'tenant_api';
        $deployment->database_user = 'tenant';
        $deployment->database_pass = 'not-a-real-password';
        $this->assertSame('Missing Service Account', $step->validateDeployCommand($deployment));

        (new ServiceAccountStep())->startDeployCommand($deployment);
        $this->assertNull($step->validateDeployCommand($deployment));
    }

    /**
     * The three methods that look at the running pods rather than at the Deployment: which
     * pods there are, whether their containers are ready, and running a command in one.
     *
     * They are what the workspace page shows and what a post migration command runs
     * through, and none of them can be answered without a pod that has actually started -
     * so they share one, and this is the only test in the suite that waits for a container
     * to come up.
     */
    public function testThePodsAreFoundReadyAndCanBeRunCommandsIn(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        (new NamespaceStep())->startDeployCommand($deployment);
        $step = new DeploymentStep();

        $this->assertSame([], $step->getPods($deployment), 'nothing is running before the deploy');

        $step->startDeployCommand($deployment);

        // Ready is not the same as existing: a pod is there long before its container is
        // serving, and that distinction is what the workspace page reports as "deploying".
        // Both are asked for together because `hasNonReadyContainer()` answers false for a
        // deployment with no pods at all, so on its own it is not a wait.
        $this->eventuallyWithinAMinute(function () use ($step, $deployment) {
            $pods = $step->getPods($deployment);

            return count($pods) === 1
                && $pods[0]->isRunning()
                && !$step->hasNonReadyContainer($deployment);
        }, 'the container never became ready');

        $log = $step->executeCommand(
            $deployment,
            $deployment->name,
            ['/bin/sh', '-c', 'echo one; echo two'],
            false
        );

        // Kubernetes cuts the stream into frames wherever it likes - several lines in one, or
        // a line across two - and the step joins them before splitting, so what comes out is
        // one entry per line. It splits on \n alone, and the stream is CRLF, so every line
        // still carries its carriage return; that is what a caller comparing the output has
        // to know.
        $this->assertContains("one\r", $log, json_encode($log));
        $this->assertContains("two\r", $log, json_encode($log));
    }

    /**
     * `$forAll` is the difference between running a command once and running it on every
     * pod, and it only means anything when there is more than one - which is why the test
     * above, with its single pod, cannot say whether the flag is read at all.
     *
     * The two callers want opposite things. A migration must run exactly once, however
     * many pods there are, or two of them race on the same schema. Clearing a cache must
     * run everywhere, or the pods that were skipped keep serving the old one.
     *
     * `hostname` is what tells them apart: inside a pod it is the pod's own name, so the
     * number of distinct answers is the number of pods the command reached.
     */
    public function testACommandRunsInOnePodOrInEveryPodDependingOnTheFlag(): void {
        $deployment = $this->deploymentInTheTestNamespace(['replicas' => 2]);
        (new NamespaceStep())->startDeployCommand($deployment);
        $step = new DeploymentStep();

        $step->startDeployCommand($deployment);
        $this->eventuallyWithinAMinute(function () use ($step, $deployment) {
            $pods = $step->getPods($deployment);

            return count($pods) === 2
                && count(array_filter($pods, fn ($pod) => $pod->isRunning())) === 2
                && !$step->hasNonReadyContainer($deployment);
        }, 'the two containers never became ready');

        $hostnames = fn (bool $forAll) => array_values(array_unique(array_filter(array_map(
            'trim',
            $step->executeCommand($deployment, $deployment->name, ['/bin/sh', '-c', 'hostname'], $forAll)
        ), 'strlen')));

        $this->assertCount(1, $hostnames(false), 'without $forAll the command runs in one pod');
        $this->assertCount(2, $hostnames(true), 'with $forAll it runs in every pod');
    }

    /**
     * The events drawer an operator opens on a workload that is misbehaving. The step does
     * not pass the event through, it reshapes it, and every field it picks is read by name
     * in the UI - so a `type` holding what was meant to be the `reason` shows a Warning
     * labelled `FailedCreate` as a `FailedCreate` labelled Warning.
     *
     * Nothing generates an event here - the pods start cleanly - so the test writes the
     * one the replicaset controller would and checks what the step makes of it.
     */
    public function testEventsAboutTheDeploymentAreReshapedFieldByField(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        (new NamespaceStep())->startDeployCommand($deployment);
        $step = new DeploymentStep();
        $step->startDeployCommand($deployment);
        $this->recordEvent($deployment->name, [
            'count' => 7,
            'type' => 'Warning',
            'reason' => 'FailedCreate',
            'message' => 'pods "api-" is forbidden: exceeded quota',
            'lastTimestamp' => '2026-09-17T10:00:00Z',
            'source' => ['component' => 'replicaset-controller'],
        ]);

        // The deployment controller records events of its own here (a ScalingReplicaSet at
        // the very least), so the one written above is picked out by its count rather than
        // by being the only one.
        $recorded = array_values(array_filter(
            $step->getKubernetesEvents($deployment),
            fn ($event) => $event['count'] === 7
        ));

        $this->assertCount(1, $recorded, 'the recorded event should come back exactly once');
        $this->assertSame([
            'count' => 7,
            'type' => 'Warning',
            'reason' => 'FailedCreate',
            'date' => date('Y-m-d H:i:s', strtotime('2026-09-17T10:00:00Z')),
            'from' => 'replicaset-controller',
            'message' => 'pods "api-" is forbidden: exceeded quota',
        ], $recorded[0]);
    }

    /**
     * The status drawer, which shows the block the controller writes and not the one kso
     * sent. The two are easy to confuse because both carry a `replicas` - but a `replicas`
     * read out of the spec is only ever the number that was asked for, so a drawer showing
     * it would report a workload whose pods all failed to start as fully up.
     */
    public function testTheKubernetesStatusIsTheControllersBlockAndNotTheSpec(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        (new NamespaceStep())->startDeployCommand($deployment);
        $step = new DeploymentStep();
        $step->startDeployCommand($deployment);

        // Wait for `observedGeneration`, not for `conditions`. The controller's first write is
        // a partial status carrying only the `Progressing` condition; the generation it has
        // observed arrives in a later update. Waiting on `conditions` made this pass only
        // when the two happened to land together, and it failed every time once the cluster
        // was slower than that. A spec never carries `observedGeneration`, so a step that
        // returned the spec instead still fails here - on the timeout.
        $status = [];
        $this->eventually(function () use ($step, $deployment, &$status) {
            $status = $step->getKubernetesStatus($deployment);

            return isset($status['observedGeneration']);
        }, 'the controller never wrote a status');

        $this->assertArrayHasKey('observedGeneration', $status);
        $this->assertArrayNotHasKey('template', $status, 'this is the spec, not the status');
        $this->assertArrayNotHasKey('selector', $status, 'this is the spec, not the status');
    }

    /**
     * The other answer: a pod that is there but whose container is not serving. A tag that
     * does not exist is the everyday way to get one - it is what a typo in a version does -
     * and the workspace has to be reported as not ready rather than as running.
     */
    public function testAContainerThatCannotStartIsReportedAsNotReady(): void {
        $deployment = $this->deploymentInTheTestNamespace(['version' => 'no-such-tag']);
        (new NamespaceStep())->startDeployCommand($deployment);
        $step = new DeploymentStep();

        $step->startDeployCommand($deployment);

        $this->eventually(
            fn () => count($step->getPods($deployment)) === 1 && $step->hasNonReadyContainer($deployment),
            'the pod never turned up'
        );
    }

    /**
     * Terminating a step that is there answers with no error at all. Every terminate goes
     * through this, and a null is how the caller knows there is nothing to show the user.
     */
    public function testTerminatingAStepThatIsThereReportsNoError(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        (new NamespaceStep())->startDeployCommand($deployment);
        $step = new DeploymentStep();
        $step->startDeployCommand($deployment);

        $this->assertNull($step->tryExecuteTerminateCommand($deployment));

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::Deployment_NotFound
        );
    }

    /**
     * A trigger the step listens for reaches the cluster, and one it does not is ignored.
     * That is the whole of `EmitTrigger()`: it is what every save endpoint calls, and a
     * deployment that does not react to a saved change looks like it took the change.
     */
    public function testATriggerTheStepListensForDeploysIt(): void {
        $deployment = $this->deploymentForTriggers();

        $error = DeploymentStepHelper::EmitTrigger(
            DeploymentStepTriggers::Deployment_Version_Updated,
            $deployment
        );

        $this->assertNull($error);
        $this->assertSame(
            DeploymentStepHelper::Deployment_Found,
            (new DeploymentStep())->getStatus($deployment)
        );
    }

    /**
     * A trigger no step listens for reaches the cluster with nothing to apply. It is the
     * common case - most saves change something only one or two steps care about - and it
     * must not be an error.
     */
    public function testATriggerNoStepListensForChangesNothing(): void {
        $deployment = $this->deploymentForTriggers();

        $error = DeploymentStepHelper::EmitTrigger(
            DeploymentStepTriggers::Deployment_KNativeMinScale_Updated,
            $deployment
        );

        $this->assertNull($error);
        $this->assertSame(
            DeploymentStepHelper::Deployment_NotFound,
            (new DeploymentStep())->getStatus($deployment),
            'nothing should have been applied'
        );
    }

    /**
     * An apply the api server refuses comes back through `EmitTrigger()` as the error, and
     * the emit stops there rather than running the steps after it.
     *
     * kso does not check a deployment's name against Kubernetes' own rules, so a name with
     * an underscore in it passes every validation here and is refused by the api server -
     * which is a real way for this to happen and not a contrived one.
     */
    public function testATriggerCarriesBackWhatTheApiServerRefused(): void {
        $deployment = $this->deploymentForTriggers(['name' => 'api_v2']);

        $error = DeploymentStepHelper::EmitTrigger(
            DeploymentStepTriggers::Deployment_Version_Updated,
            $deployment
        );

        $this->assertNotNull($error, 'a refused apply has to be reported');
        $this->assertStringContainsString('api_v2', $error);
    }

    /**
     * `eventually()` waits five seconds, which is the right order for a resource turning up
     * in the api server. A container *starting* is a different one - it may have an image to
     * pull first - so this waits a minute. Nothing else in the suite needs it, which is why
     * it lives here rather than in the base class.
     *
     * @param callable(): bool $condition
     */
    private function eventuallyWithinAMinute(callable $condition, string $message): void {
        for ($attempt = 0; $attempt < 600; $attempt++) {
            if ($condition()) {
                $this->assertTrue(true);

                return;
            }

            usleep(100000);
        }

        $this->fail($message);
    }

    /**
     * The event a controller would record against the Deployment. `getEvents()` finds it
     * by involvedObject, so the kind and the name are what make it belong to this one.
     *
     * @param array<string, mixed> $attributes
     */
    private function recordEvent(string $resourceName, array $attributes): void {
        $event = $this->cluster()->event()
            ->setName($resourceName . '.' . bin2hex(random_bytes(4)))
            ->setNamespace($this->testNamespace)
            ->setAttribute('involvedObject', [
                'kind' => 'Deployment',
                'name' => $resourceName,
                'namespace' => $this->testNamespace,
                'apiVersion' => 'apps/v1',
            ]);

        foreach ($attributes as $key => $value) {
            $event->setAttribute($key, $value);
        }

        $event->create();
    }

    /**
     * A deployment whose only steps are the namespace and the workload, so a trigger runs
     * to the end instead of stopping at a Service that is waiting for something else.
     *
     * @param array<string, mixed> $overrides
     */
    private function deploymentForTriggers(array $overrides = []): \App\Entities\Deployment {
        $deployment = $this->deploymentInTheTestNamespace(array_merge(
            ['status' => \DeploymentStatusTypes::Active],
            $overrides
        ));

        $specification = $deployment->findDeploymentSpecification();
        $specification->enable_internal_access = false;
        $specification->save();

        (new NamespaceStep())->startDeployCommand($deployment);

        return $deployment;
    }

}
