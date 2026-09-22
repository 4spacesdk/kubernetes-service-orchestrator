<?php namespace App\Tests\Unit\Health;

use App\Libraries\Health\ClusterSnapshot;
use App\Libraries\Health\HealthEvaluator;
use App\Libraries\Health\HealthResult;
use App\Libraries\Health\Workload;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The rules that turn what the cluster says into a health.
 *
 * Every snapshot here is shaped like what the api server answers: the Deployment's `status`,
 * the pods' `containerStatuses` and conditions. The ImagePullBackOff case is the one the
 * development cluster had on 2026-09-22 - a rollout to a tag that did not exist, with the old
 * pod still serving, which kso's status called "Deploying" for as long as it lasted.
 */
class HealthEvaluatorTest extends CIUnitTestCase {

    private const int Now = 1_800_000_000;

    // <editor-fold desc="Deployments">

    public function testARolledOutDeploymentWithEveryReplicaAvailableIsHealthy(): void {
        $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->pod()]));

        $this->assertSame(\HealthStatusTypes::Healthy, $result->health);
        $this->assertSame('', $result->reason);
    }

    public function testADeploymentThatIsNotInTheClusterIsMissing(): void {
        $result = $this->evaluate($this->snapshot([], []));

        $this->assertSame(\HealthStatusTypes::Missing, $result->health);
    }

    /**
     * Suspended is decided before the cluster is asked - the snapshot here says Missing, and
     * that is not the answer for a deployment nobody expects to be running.
     */
    public function testASuspendedWorkloadIsSuspendedWhateverTheClusterSays(): void {
        $result = HealthEvaluator::Evaluate(
            new Workload(\WorkloadTypes::Deployment, 'ns', 'api', '1.0', 'The workspace is paused or switched off'),
            $this->snapshot([], []),
            self::Now,
        );

        $this->assertSame(\HealthStatusTypes::Suspended, $result->health);
        $this->assertSame('The workspace is paused or switched off', $result->reason);
    }

    public function testARolloutPausedInTheClusterIsSuspended(): void {
        $result = $this->evaluate($this->snapshot([$this->deployment(spec: ['paused' => true])], [$this->pod()]));

        $this->assertSame(\HealthStatusTypes::Suspended, $result->health);
    }

    /**
     * The controller has not looked at the new spec yet - the first moment after a deploy.
     */
    public function testANewGenerationTheControllerHasNotSeenIsProgressing(): void {
        $result = $this->evaluate($this->snapshot([$this->deployment(generation: 5, status: ['observedGeneration' => 4])], [$this->pod()]));

        $this->assertSame(\HealthStatusTypes::Progressing, $result->health);
    }

    public function testOldReplicasStillRunningBesideTheNewOnesIsProgressing(): void {
        $result = $this->evaluate($this->snapshot(
            [$this->deployment(status: ['replicas' => 3, 'updatedReplicas' => 2, 'availableReplicas' => 2], spec: ['replicas' => 2])],
            [$this->pod()],
        ));

        $this->assertSame(\HealthStatusTypes::Progressing, $result->health);
        $this->assertSame('Rolling out: 2/2 updated, 2 available', $result->reason);
    }

    /**
     * The development cluster's case. The rollout is not stuck in Kubernetes' eyes yet - no
     * deadline has passed - but a container waiting for an image that does not exist will not
     * get better on its own, and it says which pod.
     */
    public function testAnImageThatCannotBePulledIsDegradedEvenWhileRollingOut(): void {
        $result = $this->evaluate($this->snapshot(
            [$this->deployment(status: ['replicas' => 2, 'updatedReplicas' => 1, 'availableReplicas' => 1])],
            [$this->pod('api-old'), $this->pod('api-new', waiting: 'ImagePullBackOff')],
        ));

        $this->assertSame(\HealthStatusTypes::Degraded, $result->health);
        $this->assertSame('ImagePullBackOff (api-new)', $result->reason);
    }

    public function testACrashLoopIsDegraded(): void {
        $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->pod(waiting: 'CrashLoopBackOff')]));

        $this->assertSame(\HealthStatusTypes::Degraded, $result->health);
        $this->assertStringContainsString('CrashLoopBackOff', $result->reason);
    }

    /**
     * The ordinary waits are not problems: every pod passes through them on its way up.
     */
    public function testAContainerThatIsBeingCreatedIsNotAProblem(): void {
        $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->pod(waiting: 'ContainerCreating')]));

        $this->assertSame(\HealthStatusTypes::Healthy, $result->health);
    }

    public function testARolloutPastItsProgressDeadlineIsDegradedAndSaysSoFirst(): void {
        $result = $this->evaluate($this->snapshot(
            [$this->deployment(status: [
                'updatedReplicas' => 0,
                'conditions' => [['type' => 'Progressing', 'status' => 'False', 'reason' => 'ProgressDeadlineExceeded']],
            ])],
            [$this->pod(waiting: 'ErrImagePull')],
        ));

        $this->assertSame(\HealthStatusTypes::Degraded, $result->health);
        $this->assertSame('The rollout has stopped making progress; ErrImagePull (api-1)', $result->reason);
    }

    /**
     * The new pods are up but not all available yet - the last minute of a rollout.
     */
    public function testUpdatedReplicasThatAreNotAvailableYetAreProgressing(): void {
        $result = $this->evaluate($this->snapshot(
            [$this->deployment(spec: ['replicas' => 3], status: [
                'replicas' => 3, 'updatedReplicas' => 3, 'availableReplicas' => 2,
                'conditions' => [['type' => 'Progressing', 'status' => 'True', 'reason' => 'ReplicaSetUpdated']],
            ])],
            [$this->pod()],
        ));

        $this->assertSame(\HealthStatusTypes::Progressing, $result->health);
    }

    /**
     * The same counts after the rollout has finished: a pod that lost its readiness later is
     * not a rollout that will complete. Kubernetes leaves `NewReplicaSetAvailable` on the
     * condition, and that is the difference - the counts are identical.
     */
    public function testAReplicaShortAfterTheRolloutFinishedIsDegraded(): void {
        $result = $this->evaluate($this->snapshot(
            [$this->deployment(spec: ['replicas' => 3], status: [
                'replicas' => 3, 'updatedReplicas' => 3, 'availableReplicas' => 2,
                'conditions' => [['type' => 'Progressing', 'status' => 'True', 'reason' => 'NewReplicaSetAvailable']],
            ])],
            [$this->pod()],
        ));

        $this->assertSame(\HealthStatusTypes::Degraded, $result->health);
        $this->assertSame('2/3 ready', $result->reason);
    }

    /**
     * Fewer updated than wanted is a rollout under way, whatever the condition says - it is
     * the condition from before the spec changed until the controller catches up.
     */
    public function testFewerUpdatedThanWantedIsProgressing(): void {
        $result = $this->evaluate($this->snapshot(
            [$this->deployment(spec: ['replicas' => 3], status: [
                'replicas' => 2, 'updatedReplicas' => 2, 'availableReplicas' => 2,
                'conditions' => [['type' => 'Progressing', 'status' => 'True', 'reason' => 'NewReplicaSetAvailable']],
            ])],
            [$this->pod()],
        ));

        $this->assertSame(\HealthStatusTypes::Progressing, $result->health);
    }

    // </editor-fold>

    // <editor-fold desc="Pods that cannot be placed">

    /**
     * A new node takes a minute or two to come up, and a pod waiting for it is the cluster
     * doing its job.
     */
    public function testAPodThatHasWaitedForANodeBrieflyIsNotAProblem(): void {
        $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->unschedulablePod(secondsAgo: 60)]));

        $this->assertSame(\HealthStatusTypes::Healthy, $result->health);
    }

    public function testAPodThatHasWaitedForANodeForFiveMinutesIsDegraded(): void {
        $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->unschedulablePod(secondsAgo: HealthEvaluator::UnschedulableGrace)]));

        $this->assertSame(\HealthStatusTypes::Degraded, $result->health);
        $this->assertSame('Unschedulable (api-1)', $result->reason);
    }

    // </editor-fold>

    // <editor-fold desc="Restarts">

    public function testASingleRecentRestartIsANoteAndNotAProblem(): void {
        $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->pod(restarts: 1, lastExit: ['reason' => 'Error', 'secondsAgo' => 600])]));

        $this->assertSame(\HealthStatusTypes::Healthy, $result->health);
        $this->assertSame('Restarted 10 min ago (api-1)', $result->reason);
    }

    public function testARecentRestartAfterSeveralIsDegraded(): void {
        $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->pod(restarts: HealthEvaluator::RestartsThatCount, lastExit: ['reason' => 'Error', 'secondsAgo' => 600])]));

        $this->assertSame(\HealthStatusTypes::Degraded, $result->health);
        $this->assertSame('Restarted 3 times, last 10 min ago (api-1)', $result->reason);
    }

    /**
     * The lifetime count is not held against a container that has been quiet for the hour.
     */
    public function testRestartsLongerAgoThanTheHourAreForgotten(): void {
        $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->pod(restarts: 12, lastExit: ['reason' => 'Error', 'secondsAgo' => HealthEvaluator::RestartWindow + 1])]));

        $this->assertSame(\HealthStatusTypes::Healthy, $result->health);
        $this->assertSame('', $result->reason);
    }

    /**
     * Out of memory once is enough: it will happen again under the same load, and the limit is
     * what needs changing.
     */
    public function testARecentOutOfMemoryKillIsDegradedTheFirstTime(): void {
        $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->pod(restarts: 1, lastExit: ['reason' => 'OOMKilled', 'secondsAgo' => 30])]));

        $this->assertSame(\HealthStatusTypes::Degraded, $result->health);
        $this->assertSame('OOMKilled just now (api-1)', $result->reason);
    }

    /**
     * A pod on its way out is the fix, not the problem - the old ReplicaSet's crash-looping pod
     * being removed by the rollout that replaces it.
     */
    public function testAPodThatIsTerminatingIsLeftOut(): void {
        $leaving = $this->pod('api-old', waiting: 'CrashLoopBackOff');
        $leaving['metadata']['deletionTimestamp'] = '2027-01-15T08:00:00Z';

        $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->pod(), $leaving]));

        $this->assertSame(\HealthStatusTypes::Healthy, $result->health);
    }

    /**
     * An init container that crashes keeps the pod from ever starting.
     */
    public function testAnInitContainerIsLookedAtToo(): void {
        $pod = $this->pod();
        $pod['status']['initContainerStatuses'] = [['name' => 'init', 'state' => ['waiting' => ['reason' => 'CrashLoopBackOff']], 'restartCount' => 4]];

        $result = $this->evaluate($this->snapshot([$this->deployment()], [$pod]));

        $this->assertSame(\HealthStatusTypes::Degraded, $result->health);
    }

    /**
     * Only the pods with the deployment's `app` label, in its namespace, count.
     */
    public function testAnotherWorkloadsPodsAreNotThisOnes(): void {
        $other = $this->pod('web-1', waiting: 'CrashLoopBackOff');
        $other['metadata']['labels']['app'] = 'web';
        $elsewhere = $this->pod('api-9', waiting: 'CrashLoopBackOff');
        $elsewhere['metadata']['namespace'] = 'other';

        $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->pod(), $other, $elsewhere]));

        $this->assertSame(\HealthStatusTypes::Healthy, $result->health);
    }

    // </editor-fold>

    // <editor-fold desc="The migration job">

    public function testAFailedMigrationForTheVersionRunningIsDegraded(): void {
        $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->pod()]), migration: [
            'status' => \MigrationJobStatusTypes::Failed_PostCommands,
            'image' => 'registry/api:1.0',
        ]);

        $this->assertSame(\HealthStatusTypes::Degraded, $result->health);
        $this->assertSame('The migration for 1.0 failed', $result->reason);
    }

    /**
     * A migration that failed for an earlier version was put right by the deploy after it.
     * "1.0" is a suffix of "11.0" - the tag is matched with its colon.
     */
    public function testAFailedMigrationForAnotherVersionIsNotHeldAgainstThisOne(): void {
        foreach (['registry/api:0.9', 'registry/api:11.0'] as $image) {
            $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->pod()]), migration: [
                'status' => \MigrationJobStatusTypes::Failed_LogVerification,
                'image' => $image,
            ]);

            $this->assertSame(\HealthStatusTypes::Healthy, $result->health, $image);
        }
    }

    public function testAMigrationUnderWayForTheVersionIsProgressing(): void {
        $result = $this->evaluate($this->snapshot([$this->deployment()], [$this->pod()]), migration: [
            'status' => \MigrationJobStatusTypes::Started,
            'image' => 'registry/api:1.0',
        ]);

        $this->assertSame(\HealthStatusTypes::Progressing, $result->health);
    }

    // </editor-fold>

    // <editor-fold desc="Knative Services">

    public function testAReadyKnativeServiceIsHealthy(): void {
        $result = $this->evaluateKnative(ClusterSnapshot::FromArrays([], [$this->pod()], [$this->knativeService('True')]));

        $this->assertSame(\HealthStatusTypes::Healthy, $result->health);
    }

    /**
     * Scaled to zero is Knative working, not a workload that has gone.
     */
    public function testAKnativeServiceScaledToZeroIsHealthyAndSaysSo(): void {
        $result = $this->evaluateKnative(ClusterSnapshot::FromArrays([], [], [$this->knativeService('True')]));

        $this->assertSame(\HealthStatusTypes::Healthy, $result->health);
        $this->assertSame('Scaled to zero', $result->reason);
    }

    public function testAKnativeServiceThatIsNotReadyIsDegradedWithKnativesReason(): void {
        $result = $this->evaluateKnative(ClusterSnapshot::FromArrays([], [], [$this->knativeService('False', 'RevisionFailed', 'Container failed to start')]));

        $this->assertSame(\HealthStatusTypes::Degraded, $result->health);
        $this->assertSame('RevisionFailed: Container failed to start', $result->reason);
    }

    public function testAKnativeServiceStillBecomingReadyIsProgressing(): void {
        $result = $this->evaluateKnative(ClusterSnapshot::FromArrays([], [], [$this->knativeService('Unknown', 'RevisionMissing')]));

        $this->assertSame(\HealthStatusTypes::Progressing, $result->health);
        $this->assertSame('RevisionMissing', $result->reason);
    }

    public function testAKnativeServiceThatIsNotInTheClusterIsMissing(): void {
        $result = $this->evaluateKnative(ClusterSnapshot::FromArrays([], [], []));

        $this->assertSame(\HealthStatusTypes::Missing, $result->health);
    }

    /**
     * The Knative listing failed - a cluster without Knative, or a hiccup - and that is not the
     * same as the service being gone.
     */
    public function testKnativeServicesThatCouldNotBeReadAreUnknownNotMissing(): void {
        $result = $this->evaluateKnative(ClusterSnapshot::FromArrays([], [], [], 'the server could not find the requested resource'));

        $this->assertSame(\HealthStatusTypes::Unknown, $result->health);
        $this->assertStringContainsString('could not find the requested resource', $result->reason);
    }

    // </editor-fold>

    // <editor-fold desc="Custom resources">

    /**
     * kso did not write the manifest and cannot know what it means, so the operator's own
     * conditions are all there is. These are a RabbitmqCluster's, as the development cluster had
     * them on 2026-09-22.
     *
     * Argo CD reads `AllReplicasReady: False` on one of these as Progressing rather than Degraded,
     * and so does this: a condition being false is usually "not yet", not "broken".
     */
    public function testAResourceOnItsWayUpIsProgressing(): void {
        $result = $this->evaluateCustomResource([
            ['type' => 'AllReplicasReady', 'status' => 'False', 'reason' => 'NotAllPodsReady', 'message' => '0/1 Pods ready'],
            ['type' => 'ClusterAvailable', 'status' => 'True', 'reason' => 'AtLeastOneEndpointAvailable'],
            ['type' => 'NoWarnings', 'status' => 'True'],
            ['type' => 'ReconcileSuccess', 'status' => 'Unknown', 'reason' => 'Initialising'],
        ]);

        $this->assertSame(\HealthStatusTypes::Progressing, $result->health);
        $this->assertSame('NotAllPodsReady: 0/1 Pods ready', $result->reason);
    }

    /**
     * Where kso goes further than Argo CD, which leaves such a resource Progressing for as long as
     * it likes. A RabbitmqCluster that has said the same thing since this morning is not on its
     * way anywhere, and the condition carries the time it last changed.
     */
    public function testAResourceThatHasBeenOnItsWayForHoursIsDegraded(): void {
        $result = $this->evaluateCustomResource([
            [
                'type' => 'AllReplicasReady',
                'status' => 'False',
                'reason' => 'NotAllPodsReady',
                'message' => '0/1 Pods ready',
                'lastTransitionTime' => gmdate('Y-m-d\TH:i:s\Z', self::Now - 3 * 3600),
            ],
        ]);

        $this->assertSame(\HealthStatusTypes::Degraded, $result->health);
        $this->assertSame('NotAllPodsReady: 0/1 Pods ready, for 3 hours', $result->reason);
    }

    /**
     * The operator saying it could not do its work is the one case that is bad straight away -
     * Argo CD's check for the same resource reads it the same way.
     */
    public function testAnOperatorThatCouldNotReconcileIsDegradedAtOnce(): void {
        $result = $this->evaluateCustomResource([
            ['type' => 'ReconcileSuccess', 'status' => 'False', 'reason' => 'Failed', 'message' => 'admission webhook denied the request'],
            ['type' => 'ClusterAvailable', 'status' => 'True'],
        ]);

        $this->assertSame(\HealthStatusTypes::Degraded, $result->health);
        $this->assertSame('Failed: admission webhook denied the request', $result->reason);
    }

    /** And a condition whose being true is the bad news - the other polarity Kubernetes uses. */
    public function testAConditionThatReportsTroubleByBeingTrueIsDegraded(): void {
        foreach (['Degraded', 'Failed', 'Stalled'] as $type) {
            $result = $this->evaluateCustomResource([
                ['type' => $type, 'status' => 'True', 'reason' => 'SomethingBroke'],
                ['type' => 'Ready', 'status' => 'True'],
            ]);

            $this->assertSame(\HealthStatusTypes::Degraded, $result->health, $type);
        }
    }

    public function testEverythingTheOperatorReportsBeingTrueIsHealthy(): void {
        $result = $this->evaluateCustomResource([
            ['type' => 'AllReplicasReady', 'status' => 'True'],
            ['type' => 'ClusterAvailable', 'status' => 'True'],
            ['type' => 'ReconcileSuccess', 'status' => 'True'],
        ]);

        $this->assertSame(\HealthStatusTypes::Healthy, $result->health);
    }

    public function testAResourceScaledToZeroIsSuspended(): void {
        $result = HealthEvaluator::Evaluate(
            new Workload(\WorkloadTypes::CustomResource, 'ns', 'api', '', null, null, [
                'spec' => ['replicas' => 0],
                'status' => ['conditions' => [['type' => 'AllReplicasReady', 'status' => 'False']]],
            ]),
            $this->snapshot([], []),
            self::Now,
        );

        $this->assertSame(\HealthStatusTypes::Suspended, $result->health);
    }

    /**
     * A ConfigMap deployed as a custom resource has no conditions, and nothing kso can read is a
     * truer answer than a guess. Conditions it does not know the meaning of are the same case.
     */
    public function testAResourceThatSaysNothingAboutItselfHasNoHealth(): void {
        $this->assertNull($this->evaluateCustomResource([]));
        $this->assertNull($this->evaluateCustomResource([['type' => 'NoWarnings', 'status' => 'True']]));
    }

    public function testACustomResourceThatIsNotInTheClusterIsMissing(): void {
        $result = HealthEvaluator::Evaluate(
            new Workload(\WorkloadTypes::CustomResource, 'ns', 'api', ''),
            $this->snapshot([], []),
            self::Now,
        );

        $this->assertSame(\HealthStatusTypes::Missing, $result->health);
    }

    /**
     * @param list<array<string, mixed>> $conditions
     */
    private function evaluateCustomResource(array $conditions): ?HealthResult {
        return HealthEvaluator::Evaluate(
            new Workload(\WorkloadTypes::CustomResource, 'ns', 'api', '', null, null, [
                'metadata' => ['name' => 'api', 'namespace' => 'ns'],
                'status' => ['conditions' => $conditions],
            ]),
            $this->snapshot([], []),
            self::Now,
        );
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * @param array{status: string, image: string}|null $migration
     */
    private function evaluate(ClusterSnapshot $snapshot, ?array $migration = null): ?HealthResult {
        return HealthEvaluator::Evaluate(
            new Workload(\WorkloadTypes::Deployment, 'ns', 'api', '1.0', null, $migration),
            $snapshot,
            self::Now,
        );
    }

    private function evaluateKnative(ClusterSnapshot $snapshot): ?HealthResult {
        return HealthEvaluator::Evaluate(
            new Workload(\WorkloadTypes::KNativeService, 'ns', 'api', '1.0'),
            $snapshot,
            self::Now,
        );
    }

    /**
     * @param list<array> $deployments
     * @param list<array> $pods
     */
    private function snapshot(array $deployments, array $pods): ClusterSnapshot {
        return ClusterSnapshot::FromArrays($deployments, $pods);
    }

    /**
     * One replica, rolled out and available, unless told otherwise.
     */
    private function deployment(int $generation = 2, array $spec = [], array $status = []): array {
        return [
            'metadata' => ['namespace' => 'ns', 'name' => 'api', 'generation' => $generation],
            'spec' => [...['replicas' => 1], ...$spec],
            'status' => [...[
                'observedGeneration' => $generation,
                'replicas' => 1,
                'updatedReplicas' => 1,
                'availableReplicas' => 1,
                'readyReplicas' => 1,
            ], ...$status],
        ];
    }

    /**
     * @param array{reason: string, secondsAgo: int}|null $lastExit
     */
    private function pod(string $name = 'api-1', ?string $waiting = null, int $restarts = 0, ?array $lastExit = null): array {
        $container = [
            'name' => 'api',
            'state' => $waiting ? ['waiting' => ['reason' => $waiting]] : ['running' => ['startedAt' => '2027-01-15T08:00:00Z']],
            'ready' => $waiting === null,
            'restartCount' => $restarts,
            'lastState' => [],
        ];
        if ($lastExit) {
            $container['lastState'] = ['terminated' => [
                'reason' => $lastExit['reason'],
                'finishedAt' => gmdate('Y-m-d\TH:i:s\Z', self::Now - $lastExit['secondsAgo']),
            ]];
        }

        return [
            'metadata' => ['namespace' => 'ns', 'name' => $name, 'labels' => ['app' => 'api', 'role' => 'app']],
            'status' => ['phase' => 'Running', 'containerStatuses' => [$container]],
        ];
    }

    private function unschedulablePod(int $secondsAgo): array {
        return [
            'metadata' => ['namespace' => 'ns', 'name' => 'api-1', 'labels' => ['app' => 'api', 'role' => 'app']],
            'status' => [
                'phase' => 'Pending',
                'conditions' => [[
                    'type' => 'PodScheduled',
                    'status' => 'False',
                    'reason' => 'Unschedulable',
                    'lastTransitionTime' => gmdate('Y-m-d\TH:i:s\Z', self::Now - $secondsAgo),
                ]],
            ],
        ];
    }

    private function knativeService(string $ready, string $reason = '', string $message = ''): array {
        $condition = ['type' => 'Ready', 'status' => $ready];
        if ($reason !== '') {
            $condition['reason'] = $reason;
        }
        if ($message !== '') {
            $condition['message'] = $message;
        }

        return [
            'metadata' => ['namespace' => 'ns', 'name' => 'api', 'generation' => 3],
            'status' => ['observedGeneration' => 3, 'conditions' => [$condition]],
        ];
    }

    // </editor-fold>

}
