<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\CronjobStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\Libraries\DeploymentSteps\NamespaceStep;

/**
 * The two steps that run something once rather than keep something running.
 *
 * A CronJob and a Job are both `batch/v1`, and both have rules a manifest test cannot
 * consult: a schedule the api server parses itself, and a Job spec that is immutable once
 * created - which is why the migration step deletes before it creates, and why that
 * deletion is the part worth watching.
 *
 * `MigrationJobStep::getPreview()` used to strip `spec.concurrencyPolicy`,
 * `spec.jobTemplate.*` and the two history limits - CronJob fields, on a step that builds a
 * Job. Every one of those calls did nothing, while the Job's own server-filled defaults went
 * straight into the diff. The list is rebased now, and
 * `testAPreviewOfAFreshlyAppliedMigrationShowsNoDifference()` is what holds it: it compares
 * the two halves by shape, so a default added by a later Kubernetes shows up as a name here
 * rather than as a mystery in the UI.
 */
class WorkloadStepsTest extends ClusterTestCase {

    // <editor-fold desc="Cron jobs">

    public function testACronJobCarriesItsScheduleAndImage(): void {
        $deployment = $this->deploymentWithCronJob(['schedule' => '15 2 * * *']);
        $step = new CronjobStep();

        $this->assertSame([DeploymentStepHelper::Cronjob_NotFound], $step->getStatus($deployment));

        $step->startDeployCommand($deployment);

        $job = $this->cluster()->getCronjobByName("{$deployment->name}-cleanup", $this->testNamespace);
        $this->assertSame('15 2 * * *', $job->getAttribute('spec.schedule'));
        $this->assertSame('Forbid', $job->getAttribute('spec.concurrencyPolicy'));
        $this->assertSame([DeploymentStepHelper::Cronjob_Found], $step->getStatus($deployment));
    }

    /**
     * kso stores the schedule as free text and never looks at it. php-k8s does: it parses
     * the expression before anything is sent, so a typo surfaces as a plain
     * InvalidArgumentException rather than as a cron job that quietly never fires. Worth
     * pinning, because it is the library doing it and not us - and the message is what a
     * user ends up reading.
     */
    public function testAScheduleThatDoesNotParseIsRefusedBeforeItIsSent(): void {
        $deployment = $this->deploymentWithCronJob(['schedule' => 'every other tuesday']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not a valid CRON expression');

        (new CronjobStep())->startDeployCommand($deployment);
    }

    /**
     * A tag policy of MatchDeployment means the cron job follows the deployment's version
     * rather than its own. It is the difference between a nightly job running last
     * month's code and this one's.
     */
    public function testTheTagFollowsTheDeploymentWhenThePolicySaysSo(): void {
        $deployment = $this->deploymentWithCronJob(
            ['container_image_tag_policy' => \ContainerImageTagPolicies::MatchDeployment],
            ['url' => 'nginx', 'default_tag' => 'stable']
        );

        (new CronjobStep())->startDeployCommand($deployment);

        $containers = $this->cluster()
            ->getCronjobByName("{$deployment->name}-cleanup", $this->testNamespace)
            ->getAttribute('spec.jobTemplate.spec.template.spec.containers');
        $this->assertSame('nginx:' . $deployment->version, $containers[0]['image']);
    }

    public function testTerminatingRemovesTheCronJob(): void {
        $deployment = $this->deploymentWithCronJob();
        $step = new CronjobStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === [DeploymentStepHelper::Cronjob_NotFound]
        );
    }

    /**
     * Run now: a job made from the deployed cron job's own template, marked and
     * owned the way `kubectl create job --from=cronjob/...` does it.
     */
    public function testRunningACronJobNowStartsAJobFromWhatIsDeployed(): void {
        $deployment = $this->deploymentWithCronJob();
        $step = new CronjobStep();
        $step->startDeployCommand($deployment);
        $cronJob = $this->cluster()->getCronjobByName("{$deployment->name}-cleanup", $this->testNamespace);

        $jobName = $step->runNow($deployment, "{$deployment->name}-cleanup");

        $job = $this->cluster()->getJobByName($jobName, $this->testNamespace);
        $this->assertStringStartsWith("{$deployment->name}-cleanup-manual-", $jobName);
        $this->assertSame('manual', $job->getAttribute('metadata.annotations')['cronjob.kubernetes.io/instantiate']);
        $owner = $job->getAttribute('metadata.ownerReferences')[0];
        $this->assertSame(['CronJob', "{$deployment->name}-cleanup", $cronJob->getAttribute('metadata.uid')], [$owner['kind'], $owner['name'], $owner['uid']]);
        $this->assertSame(
            $cronJob->getAttribute('spec.jobTemplate.spec.template.spec.containers')[0]['image'],
            $job->getAttribute('spec.template.spec.containers')[0]['image'],
        );
    }

    public function testACronJobThatIsNotDeployedCannotBeRun(): void {
        $deployment = $this->deploymentWithCronJob();

        $this->expectExceptionMessage("'{$deployment->name}-cleanup' is not deployed");

        (new CronjobStep())->runNow($deployment, "{$deployment->name}-cleanup");
    }

    // </editor-fold>

    // <editor-fold desc="Migration jobs">

    public function testAMigrationJobIsCreatedAndReportsItselfRunning(): void {
        $deployment = $this->migratableDeployment();
        $step = new MigrationJobStep();

        $this->assertSame(DeploymentStepHelper::MigrationJob_NotFound, $step->getStatus($deployment));

        $step->startDeployCommand($deployment);

        // Running, not Completed: nothing has set `status.completionTime`, and nothing will
        // here - no image is pulled and no pod is scheduled in a cluster with no registry
        // access. The distinction is what the UI shows while a migration is under way.
        $this->assertSame(DeploymentStepHelper::MigrationJob_Running, $step->getStatus($deployment));
        $this->assertSame(
            $deployment->name,
            $this->cluster()->getJobByName($deployment->name, $this->testNamespace)->getName()
        );
    }

    /**
     * A Job's spec is immutable, so a second migration cannot update the first - it has to
     * remove it and wait for it to go. That wait is the whole reason this step is written
     * the way it is, and it only happens against a real api server.
     */
    public function testASecondMigrationReplacesTheFirst(): void {
        $deployment = $this->migratableDeployment();
        $step = new MigrationJobStep();
        $step->startDeployCommand($deployment);
        $first = $this->cluster()->getJobByName($deployment->name, $this->testNamespace)
            ->getAttribute('metadata.uid');

        $step->startDeployCommand($deployment);

        $second = $this->cluster()->getJobByName($deployment->name, $this->testNamespace)
            ->getAttribute('metadata.uid');
        $this->assertNotSame($first, $second, 'the job was replaced, not updated');
    }

    public function testTerminatingRemovesTheMigrationJob(): void {
        $deployment = $this->migratableDeployment();
        $step = new MigrationJobStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::MigrationJob_NotFound
        );
    }

    /**
     * The migration runs against a database service, and without one there is nothing to
     * migrate. Refusing is the difference between no migration and a migration pointed at
     * nothing.
     */
    public function testAMigrationWithoutADatabaseServiceIsRefused(): void {
        $deployment = $this->migratableDeployment(withDatabase: false);

        $this->assertSame('Missing database service', (new MigrationJobStep())->validateDeployCommand($deployment));
    }

    /**
     * The last check the step makes is the one it cannot make on its own: the namespace has
     * to be there, and only the cluster knows. With everything else in place it is the one
     * thing standing between the step and a deploy.
     */
    public function testAMigrationIntoANamespaceThatIsNotThereIsRefused(): void {
        $deployment = $this->migratableDeployment();
        $step = new MigrationJobStep();

        $this->assertNull($step->validateDeployCommand($deployment));

        // The namespace the step looks for is the workspace's, not the deployment's own.
        $elsewhere = $this->deploymentInTheTestNamespace(
            [],
            ['namespace' => $this->testNamespace . '-never-created']
        );
        $elsewhere->database_service_id = Fixtures::databaseService()->id;
        $elsewhere->save();

        $this->assertSame('Missing Namespace', $step->validateDeployCommand($elsewhere));
    }

    /**
     * Terminating a migration job that is there answers with no error. That null is how the
     * caller knows there is nothing to put in front of the user.
     */
    public function testTerminatingAMigrationJobThatIsThereReportsNoError(): void {
        $deployment = $this->migratableDeployment();
        $step = new MigrationJobStep();
        $step->startDeployCommand($deployment);

        $this->assertNull($step->tryExecuteTerminateCommand($deployment));
    }

    /**
     * The preview is what the UI shows before a deploy: the manifest kso would send, and
     * the one the cluster already has, side by side. Before anything is applied there is no
     * remote half at all - and it must say so rather than show the local one twice.
     */
    public function testThePreviewShowsTheLocalManifestAndThenTheAppliedOne(): void {
        $deployment = $this->migratableDeployment();
        $step = new MigrationJobStep();

        $before = json_decode($step->getPreview($deployment), true);
        $this->assertNull($before['remote'], 'nothing is applied yet');
        $this->assertSame($deployment->name, json_decode($before['local'], true)['metadata']['name']);

        $step->startDeployCommand($deployment);

        $after = json_decode($step->getPreview($deployment), true);
        $remote = json_decode($after['remote'], true);
        $this->assertSame($deployment->name, $remote['metadata']['name']);

        // The fields the cluster fills in itself are stripped, or every preview would show
        // a difference that nobody can do anything about. Each of these was confirmed
        // present on the Job the api server hands back, so dropping its `unset()` is
        // noticed here rather than passing because the field was never there.
        foreach (['uid', 'resourceVersion', 'generation', 'creationTimestamp', 'managedFields'] as $noise) {
            $this->assertArrayNotHasKey($noise, $remote['metadata'], "metadata.$noise should not be in a preview");
        }
        $this->assertArrayNotHasKey('suspend', $remote['spec']);
        $this->assertArrayNotHasKey('status', $remote);

    }

    /**
     * The whole point of the stripping: a preview taken straight after a deploy shows
     * **nothing** the user did not ask for.
     *
     * The list used to be a copy of the cron job step's, aimed at `spec.jobTemplate.*` and
     * the CronJob fields beside it - none of which a Job has, so every one of those calls
     * was a no-op, while the Job's own server-filled defaults went straight into the diff.
     * Somebody previewing a migration saw half a dozen removals they could do nothing
     * about, every single time.
     *
     * Asserted as a set rather than field by field, because what matters is that the two
     * sides *agree* - a new default in a later Kubernetes shows up here as a name, which is
     * the whole of the maintenance this needs.
     */
    public function testAPreviewOfAFreshlyAppliedMigrationShowsNoDifference(): void {
        $deployment = $this->migratableDeployment();
        $step = new MigrationJobStep();
        $step->startDeployCommand($deployment);

        $preview = json_decode($step->getPreview($deployment), true);
        $local = json_decode($preview['local'], true);
        $remote = json_decode($preview['remote'], true);

        $this->assertSame(
            [],
            array_diff($this->pathsIn($remote), $this->pathsIn($local)),
            'the preview shows fields the api server filled in, which nobody can act on'
        );
    }

    /**
     * Every leaf of a manifest as a dotted path, for comparing two of them by shape rather
     * than by value. A list is a leaf: the containers are compared whole, which is what a
     * reader of a preview cares about.
     *
     * @param array<string, mixed> $manifest
     * @return string[]
     */
    private function pathsIn(array $manifest, string $prefix = ''): array {
        $paths = [];

        foreach ($manifest as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value) && $value !== [] && !array_is_list($value)) {
                $paths = array_merge($paths, $this->pathsIn($value, $path));
                continue;
            }

            $paths[] = $path;
        }

        return $paths;
    }

    /**
     * The raw `status` of the Job, which the UI shows next to the step. It is the cluster's
     * own words - how many pods are running, failed or succeeded - and the only place a
     * migration that is stuck says so.
     */
    public function testTheKubernetesStatusOfTheJobIsReadBack(): void {
        $deployment = $this->migratableDeployment();
        $step = new MigrationJobStep();
        $step->startDeployCommand($deployment);

        $status = $step->getKubernetesStatus($deployment);

        $this->assertIsArray($status);

        // A Job that has not been scheduled yet has an empty status, which is why this says
        // what the block is *not* rather than what is in it: the spec is the other half of
        // the same manifest and is never empty, so reading that one instead - which is a
        // one word change away - would fill the drawer with the manifest kso sent and call
        // it what the cluster reported.
        $this->assertArrayNotHasKey('template', $status, 'this is the spec, not the status');
        $this->assertArrayNotHasKey('activeDeadlineSeconds', $status, 'this is the spec, not the status');
        $this->assertArrayNotHasKey('backoffLimit', $status, 'this is the spec, not the status');
    }

    /**
     * Events are what the cluster says happened to the job - the pod being created, an
     * image that cannot be pulled. The step reports none of its own (`hasKubernetesEvents`
     * is false), but the reader is there and is what the migration log page falls back to.
     */
    public function testTheJobsEventsAreReadBackInTheShapeThePageExpects(): void {
        $deployment = $this->migratableDeployment();
        $step = new MigrationJobStep();
        $step->startDeployCommand($deployment);

        $events = [];
        $this->eventually(function () use ($step, $deployment, &$events) {
            $events = $step->getKubernetesEvents($deployment);

            return count($events) > 0;
        }, 'the job never produced an event');

        $this->assertSame(
            ['count', 'type', 'reason', 'date', 'from', 'message'],
            array_keys($events[0])
        );
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $events[0]['date']);

        // The keys being right says nothing about which value ended up under which one, and
        // the page reads every one of them by name: a `type` holding what was meant to be
        // the `reason` shows a Warning labelled `BackoffLimitExceeded` as a
        // `BackoffLimitExceeded` labelled Warning. So the mapping is checked against an
        // event whose fields are all different from each other, written here rather than
        // waited for - the ones the job controller produces do not cover every field.
        $this->recordJobEvent($deployment->name, [
            'count' => 9,
            'type' => 'Warning',
            'reason' => 'BackoffLimitExceeded',
            'message' => 'Job has reached the specified backoff limit',
            'lastTimestamp' => '2026-09-17T10:00:00Z',
            'source' => ['component' => 'job-controller'],
        ]);

        $recorded = array_values(array_filter(
            $step->getKubernetesEvents($deployment),
            fn ($event) => $event['count'] === 9
        ));

        $this->assertCount(1, $recorded, 'the recorded event should come back exactly once');
        $this->assertSame([
            'count' => 9,
            'type' => 'Warning',
            'reason' => 'BackoffLimitExceeded',
            'date' => date('Y-m-d H:i:s', strtotime('2026-09-17T10:00:00Z')),
            'from' => 'job-controller',
            'message' => 'Job has reached the specified backoff limit',
        ], $recorded[0]);
    }

    /**
     * The event the job controller would record against the Job. `getEvents()` finds it by
     * involvedObject, so the kind and the name are what make it belong to this one.
     *
     * @param array<string, mixed> $attributes
     */
    private function recordJobEvent(string $resourceName, array $attributes): void {
        $event = $this->cluster()->event()
            ->setName($resourceName . '.' . bin2hex(random_bytes(4)))
            ->setNamespace($this->testNamespace)
            ->setAttribute('involvedObject', [
                'kind' => 'Job',
                'name' => $resourceName,
                'namespace' => $this->testNamespace,
                'apiVersion' => 'batch/v1',
            ]);

        foreach ($attributes as $key => $value) {
            $event->setAttribute($key, $value);
        }

        $event->create();
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * @param array<string, mixed> $cronJob
     * @param array<string, mixed> $image
     */
    private function deploymentWithCronJob(array $cronJob = [], array $image = []): Deployment {
        $deployment = $this->deploymentInANamespace();
        $containerImage = Fixtures::containerImage(array_merge(
            ['url' => 'nginx', 'default_tag' => 'stable'],
            $image
        ));
        $definition = Fixtures::cronJob(array_merge(
            ['container_image_id' => $containerImage->id],
            $cronJob
        ));
        Fixtures::specificationCronJob([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'k8s_cron_job_id' => $definition->id,
        ]);

        return $deployment;
    }

    private function migratableDeployment(bool $withDatabase = true): Deployment {
        $deployment = $this->deploymentInANamespace();

        if ($withDatabase) {
            $deployment->database_service_id = Fixtures::databaseService()->id;
            $deployment->save();
        }

        return $deployment;
    }

    private function deploymentInANamespace(): Deployment {
        $deployment = $this->deploymentInTheTestNamespace();
        (new NamespaceStep())->startDeployCommand($deployment);

        return $deployment;
    }

    // </editor-fold>

}
