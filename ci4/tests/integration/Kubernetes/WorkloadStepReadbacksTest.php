<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\CronjobStep;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\PersistentVolumeClaimStep;
use App\Libraries\DeploymentSteps\ServiceStep;

/**
 * What the workload steps read back out of the cluster once something is there.
 *
 * Applying is covered elsewhere, step by step. This is the other direction: the preview a
 * user is shown before pressing deploy, the status and the events the UI puts in front of
 * an operator, and the half of `validateDeployCommand()` that asks the cluster whether the
 * things this step depends on exist yet.
 *
 * None of it can be reached without a cluster, and most of it is a list of `unset()` calls
 * whose only job is to keep the api server's own bookkeeping out of a diff. Whether that
 * list is right is exactly what a manifest test cannot say.
 */
class WorkloadStepReadbacksTest extends ClusterTestCase {

    // <editor-fold desc="Namespace">

    public function testANamespacePreviewHasNoRemoteBeforeItIsCreated(): void {
        $deployment = $this->deploymentInTheTestNamespace();

        $this->assertNull($this->preview(new NamespaceStep(), $deployment)['remote']);
    }

    /**
     * A namespace carries almost nothing of ours and a great deal of Kubernetes': labels
     * and annotations it applies itself, a `spec.finalizers` nobody wrote, a phase. All of
     * it is stripped, and what is left is the one field kso actually sets.
     */
    public function testANamespacePreviewIsStrippedDownToTheNameKsoSets(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        $step = new NamespaceStep();
        $step->startDeployCommand($deployment);

        $remote = json_decode($this->preview($step, $deployment)['remote'], true);

        $this->assertSame($this->testNamespace, $remote['metadata']['name']);
        $this->assertSame(['name'], array_keys($remote['metadata']));
        $this->assertArrayNotHasKey('spec', $remote);
        $this->assertArrayNotHasKey('status', $remote);
    }

    /**
     * The phase is how a namespace says it is on its way out: everything in it keeps
     * answering while it is `Terminating`, and nothing new may be created in it.
     */
    public function testTheNamespacePhaseIsReportedBack(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        $step = new NamespaceStep();
        $step->startDeployCommand($deployment);

        $this->assertSame(['phase' => 'Active'], $step->getKubernetesStatus($deployment));
    }

    // </editor-fold>

    // <editor-fold desc="Service">

    public function testAServicePreviewHasNoRemoteBeforeItIsApplied(): void {
        $deployment = $this->deploymentWithAServicePort();

        $this->assertNull($this->preview(new ServiceStep(), $deployment)['remote']);
    }

    /**
     * A Service picks up more from the api server than anything else kso applies: a cluster
     * ip, a node port per port, an ip family, three policies. None of it was sent, and all
     * of it would read as a change the user is about to make.
     */
    public function testAServicePreviewLeavesOutEverythingTheApiServerFilledIn(): void {
        $deployment = $this->deploymentWithAServicePort();
        $step = new ServiceStep();
        $step->startDeployCommand($deployment);

        $remote = json_decode($this->preview($step, $deployment)['remote'], true);

        $this->assertSame(80, $remote['spec']['ports'][0]['port']);
        $this->assertArrayNotHasKey('nodePort', $remote['spec']['ports'][0]);
        foreach (['clusterIP', 'clusterIPs', 'sessionAffinity', 'ipFamilies', 'ipFamilyPolicy', 'internalTrafficPolicy'] as $filledIn) {
            $this->assertArrayNotHasKey($filledIn, $remote['spec'], "spec.$filledIn should not be in a preview");
        }
        $this->assertArrayNotHasKey('status', $remote);
    }

    /**
     * The status the UI reads, on both sides of the step's own two commands. A Service that
     * is reported found when it is gone is the state in which nothing is wrong on the page
     * and nothing answers on the network.
     */
    public function testTheServiceIsFoundOnceAppliedAndGoneOnceTerminated(): void {
        $deployment = $this->deploymentWithAServicePort();
        $step = new ServiceStep();

        $this->assertSame(DeploymentStepHelper::Service_NotFound, $step->getStatus($deployment));

        $step->startDeployCommand($deployment);
        $this->assertSame(DeploymentStepHelper::Service_Found, $step->getStatus($deployment));

        $step->startTerminateCommand($deployment);
        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::Service_NotFound
        );
    }

    /**
     * The Service exists to put one workload on the network, so both the namespace and the
     * Deployment have to be there first. Applied without the Deployment it would be a
     * Service with no endpoints - which answers every request with a connection refused and
     * looks, from the outside, exactly like an application that is down.
     */
    public function testTheServiceStepWaitsForTheNamespaceAndTheDeployment(): void {
        $deployment = $this->deploymentWithAServicePort(applyNamespace: false);
        $step = new ServiceStep();

        $this->assertSame('Missing Namespace', $step->validateDeployCommand($deployment));

        (new NamespaceStep())->startDeployCommand($deployment);
        $this->assertSame('Missing Deployment', $step->validateDeployCommand($deployment));

        (new DeploymentStep())->startDeployCommand($deployment);
        $this->assertNull($step->validateDeployCommand($deployment));
    }

    // </editor-fold>

    // <editor-fold desc="Persistent volume claim">

    public function testAClaimPreviewHasNoRemoteBeforeItIsApplied(): void {
        $deployment = $this->deploymentWithAVolume();

        $preview = $this->preview(new PersistentVolumeClaimStep(), $deployment);

        $this->assertCount(1, $preview['local']);
        $this->assertSame([], $preview['remote']);
    }

    /**
     * The claim step builds a list rather than a single resource, so its preview is two
     * lists. A deployment with a volume of its own and one from its specification has two
     * of each - and the pair collides on one name, which is FEAT-13 seen before it is
     * applied rather than after.
     */
    public function testAClaimPreviewPairsEachLocalWithWhatTheClusterHolds(): void {
        $deployment = $this->deploymentWithAVolume();
        $step = new PersistentVolumeClaimStep();
        $step->startDeployCommand($deployment);

        $preview = $this->preview($step, $deployment);

        $remote = json_decode($preview['remote'][0], true);
        $this->assertSame($deployment->name, $remote['metadata']['name']);
        $this->assertSame('5Gi', $remote['spec']['resources']['requests']['storage']);
        foreach (['uid', 'resourceVersion', 'creationTimestamp', 'managedFields'] as $noise) {
            $this->assertArrayNotHasKey($noise, $remote['metadata'], "metadata.$noise should not be in a preview");
        }
    }

    // </editor-fold>

    // <editor-fold desc="Cron jobs">

    public function testACronJobPreviewHasNoRemoteBeforeItIsApplied(): void {
        $deployment = $this->deploymentWithACronJob();

        $preview = $this->preview(new CronjobStep(), $deployment);

        $this->assertCount(1, $preview['local']);
        $this->assertSame([], $preview['remote']);
    }

    /**
     * A CronJob's manifest is mostly pod template, and the api server fills in a default
     * for nearly every field of one. What is left after the stripping is the schedule and
     * the image, which is what a user is deciding about.
     */
    public function testACronJobPreviewKeepsTheScheduleAndDropsTheDefaults(): void {
        $deployment = $this->deploymentWithACronJob(['schedule' => '15 2 * * *']);
        $step = new CronjobStep();
        $step->startDeployCommand($deployment);

        $remote = json_decode($this->preview($step, $deployment)['remote'][0], true);

        $this->assertSame('15 2 * * *', $remote['spec']['schedule']);
        $this->assertArrayNotHasKey('suspend', $remote['spec']);
        $this->assertArrayNotHasKey('status', $remote);
        $this->assertArrayNotHasKey('generation', $remote['metadata']);

        $podSpec = $remote['spec']['jobTemplate']['spec']['template']['spec'];
        $this->assertArrayNotHasKey('dnsPolicy', $podSpec);
        $this->assertArrayNotHasKey('schedulerName', $podSpec);
        $this->assertArrayNotHasKey('terminationGracePeriodSeconds', $podSpec);
        $this->assertArrayNotHasKey('resources', $podSpec['containers'][0]);
    }

    public function testTheCronJobStepWaitsForTheNamespace(): void {
        $deployment = $this->deploymentWithACronJob(applyNamespace: false);
        $step = new CronjobStep();

        $this->assertSame('Missing Namespace', $step->validateDeployCommand($deployment));

        (new NamespaceStep())->startDeployCommand($deployment);

        $this->assertNull($step->validateDeployCommand($deployment));
    }

    /**
     * One status per cron job, in the order the manifests were built. Nothing has run here -
     * a job that has fired carries `lastScheduleTime`, and that is the field an operator
     * looks for when something did not happen.
     */
    public function testEachCronJobReportsAStatusOfItsOwn(): void {
        $deployment = $this->deploymentWithACronJob();
        $step = new CronjobStep();
        $step->startDeployCommand($deployment);

        $status = $step->getKubernetesStatus($deployment);

        $this->assertCount(1, $status);
        $this->assertArrayNotHasKey('lastScheduleTime', $status[0]);
    }

    /**
     * Events are the only thing the UI shows that comes straight from the cluster. Nothing
     * generates one here - k3s runs the cron controller, but not within a test - so the
     * test writes the event the controller would and checks the step finds and reshapes it.
     */
    public function testEventsAboutACronJobAreReportedWithTheirSourceAndTime(): void {
        $deployment = $this->deploymentWithACronJob();
        $step = new CronjobStep();
        $step->startDeployCommand($deployment);
        $this->recordEvent("{$deployment->name}-cleanup", [
            'count' => 4,
            'type' => 'Warning',
            'reason' => 'FailedNeedsStart',
            'message' => 'Cannot determine if job needs to be started',
            'lastTimestamp' => '2026-09-17T10:00:00Z',
            'source' => ['component' => 'cronjob-controller'],
        ]);

        $events = $step->getKubernetesEvents($deployment);

        $this->assertCount(1, $events);
        $this->assertSame([
            'count' => 4,
            'type' => 'Warning',
            'reason' => 'FailedNeedsStart',
            'date' => date('Y-m-d H:i:s', strtotime('2026-09-17T10:00:00Z')),
            'from' => 'cronjob-controller',
            'message' => 'Cannot determine if job needs to be started',
        ], $events[0]);
    }

    public function testACronJobNothingHasHappenedToHasNoEvents(): void {
        $deployment = $this->deploymentWithACronJob();
        $step = new CronjobStep();
        $step->startDeployCommand($deployment);

        $this->assertSame([], $step->getKubernetesEvents($deployment));
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures and reading">

    private function deploymentWithAServicePort(bool $applyNamespace = true): Deployment {
        $deployment = $this->deploymentInANamespace($applyNamespace);
        Fixtures::servicePort([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'http',
            'protocol' => 'TCP',
            'port' => 80,
            'target_port' => 80,
        ]);

        return $deployment;
    }

    private function deploymentWithAVolume(): Deployment {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume([
            'deployment_id' => $deployment->id,
            'capacity' => 5,
            'storage_class' => 'local-path',
        ]);

        return $deployment;
    }

    /**
     * @param array<string, mixed> $cronJob
     */
    private function deploymentWithACronJob(array $cronJob = [], bool $applyNamespace = true): Deployment {
        $deployment = $this->deploymentInANamespace($applyNamespace);
        $image = Fixtures::containerImage(['url' => 'nginx', 'default_tag' => 'stable']);
        $definition = Fixtures::cronJob(array_merge(['container_image_id' => $image->id], $cronJob));
        Fixtures::specificationCronJob([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'k8s_cron_job_id' => $definition->id,
        ]);

        return $deployment;
    }

    private function deploymentInANamespace(bool $applyNamespace = true): Deployment {
        $deployment = $this->deploymentInTheTestNamespace();

        if ($applyNamespace) {
            (new NamespaceStep())->startDeployCommand($deployment);
        }

        return $deployment;
    }

    /**
     * The event a controller would record against a resource. `getEvents()` finds it by
     * involvedObject, so the kind and the name are what make it belong to this one.
     *
     * @param array<string, mixed> $attributes
     */
    private function recordEvent(string $resourceName, array $attributes): void {
        $event = $this->cluster()->event()
            ->setName($resourceName . '.' . bin2hex(random_bytes(4)))
            ->setNamespace($this->testNamespace)
            ->setAttribute('involvedObject', [
                'kind' => 'CronJob',
                'name' => $resourceName,
                'namespace' => $this->testNamespace,
                'apiVersion' => 'batch/v1',
            ]);

        foreach ($attributes as $key => $value) {
            $event->setAttribute($key, $value);
        }

        $event->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function preview(object $step, Deployment $deployment): array {
        return json_decode($step->getPreview($deployment), true);
    }

    // </editor-fold>

}
