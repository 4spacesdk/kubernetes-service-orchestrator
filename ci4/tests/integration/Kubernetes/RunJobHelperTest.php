<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\Kubernetes\RunJobHelper;

/**
 * The one-off Job kso runs inside a customer's workspace to ask their container something.
 *
 * It is how `EnvironmentVariableCommitIdentification` finds out which commit a running
 * image was built from: apply a Job that runs `printenv`, wait for it, read its log, delete
 * it. The manifest it builds is the same shape as a deployment's - image, environment,
 * volumes, pull secret, service account - built a second time in a second place.
 *
 * Most of what is checked here is the manifest and the cleanup, which need nothing to start.
 * One test runs the whole thing for real - apply, wait, read, delete - because the four
 * halves only prove anything together: a wait that returns too early and a log read that
 * finds no pod both come back as an empty string, and nothing else would tell them apart.
 * It is the only test here that waits for a container, and it uses the image the rest of
 * the cluster suite already pulls.
 *
 * Three decisions in the helper are deliberately not asserted, because nothing this suite
 * can arrange makes them observable:
 *
 * - `getLogs()` joins the pods' logs with a newline. The job is pinned to one completion
 *   and one parallelism, so a run that gets as far as reading logs has exactly one pod and
 *   the separator is never reached.
 * - `waitForCompletion()` stops the watch on a `DELETED` or `ERROR` event. Getting one
 *   means deleting the job from outside while `runJob()` is blocked on the watch, which
 *   needs a second process. Without that stop the watch falls through to its event cap, so
 *   the difference is how long it lingers rather than what it answers.
 * - `deleteJob()` asks for `DeletePropagationBackground`. That is not one of Kubernetes'
 *   three policies (`Orphan`, `Background`, `Foreground`) and the api server rejects it
 *   with a 422 when it is sent as one - but php-k8s nests `propagationPolicy` inside
 *   `preconditions`, where the api server ignores it, so the value never takes effect and
 *   changing it changes nothing. Both halves of that are reported rather than fixed here.
 */
class RunJobHelperTest extends ClusterTestCase {

    /**
     * The version deployed, not the image's default tag. The two are the same on most
     * workspaces, which is exactly why this uses one where they differ: a job built from
     * the default tag would answer about a version nobody is running.
     */
    public function testTheJobRunsTheCommandInTheDeploymentsImage(): void {
        $deployment = $this->deploymentInANamespace(['version' => '1.27-alpine']);

        $this->applyJob($deployment, 'printenv');

        $container = $this->job($deployment)['spec']['template']['spec']['containers'][0];
        $this->assertSame('nginx:1.27-alpine', $container['image']);
        $this->assertSame('Always', $container['imagePullPolicy'], 'a one-off job always pulls');

        // The command is handed to a shell, which is what makes `sleep 2; echo x` - or any
        // other caller's command with a pipe or a semicolon in it - one command rather than
        // a program called `sleep` with four arguments.
        $this->assertSame(['/bin/sh'], $container['command']);
        $this->assertSame(['-c', 'printenv'], $container['args']);
    }

    /**
     * The two variables every caller's command can count on. They are the ones the running
     * container has, so the job answers as the application would; swapped, a command that
     * branches on the environment takes the wrong branch and still exits zero.
     */
    public function testTheJobGetsTheSameEnvironmentAndBaseUrlAsTheApplication(): void {
        $deployment = $this->deploymentInANamespace(['environment' => 'staging']);

        $this->applyJob($deployment, 'printenv');

        $environment = [];
        foreach ($this->job($deployment)['spec']['template']['spec']['containers'][0]['env'] as $entry) {
            $environment[$entry['name']] = $entry['value'] ?? null;
        }

        $this->assertSame('staging', $environment['ENVIRONMENT']);
        $this->assertStringStartsWith('https://tenant.', $environment['BASE_URL']);
    }

    /**
     * The job is named after the deployment plus the id of this particular run, so two
     * runs never collide - and so the cleanup afterwards can find its own.
     */
    public function testTheJobIsNamedAfterTheRunAndNotJustTheDeployment(): void {
        $deployment = $this->deploymentInANamespace();

        $this->applyJob($deployment, 'printenv', 'abc123');

        $names = array_column(array_column($this->jobs($deployment), 'metadata'), 'name');
        $this->assertSame(["{$deployment->name}-run-job-abc123"], $names);
    }

    /**
     * The limits that stop a forgotten job from running for ever: it may try once, and it
     * is killed after a minute. `parallelism` is the fourth of them and belongs with the
     * others - the command is asked once and its log is read as one answer, so a second
     * pod running it at the same time is both a second copy of whatever the command does
     * and a log the reader concatenates out of two pods.
     */
    public function testTheJobIsBoundedInTimeAndAttempts(): void {
        $deployment = $this->deploymentInANamespace();

        $this->applyJob($deployment, 'printenv');

        $spec = $this->job($deployment)['spec'];
        $this->assertSame(60, $spec['activeDeadlineSeconds']);
        $this->assertSame(1, $spec['backoffLimit']);
        $this->assertSame(1, $spec['completions']);
        $this->assertSame(1, $spec['parallelism'], 'one pod runs the command, once');
    }

    public function testTheDeploymentsEnvironmentIsCarriedIntoTheJob(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentEnvironmentVariable([
            'deployment_id' => $deployment->id,
            'name' => 'GIT_COMMIT',
            'value' => 'abc1234',
        ]);

        $this->applyJob($deployment, 'printenv');

        $env = $this->job($deployment)['spec']['template']['spec']['containers'][0]['env'];
        $this->assertContains(['name' => 'GIT_COMMIT', 'value' => 'abc1234'], $env);
    }

    /**
     * A workspace with a volume gets it mounted in the job too. Without it the command
     * runs against a container that is missing the customer's data, which is the difference
     * between reading a file and reading nothing.
     */
    public function testAVolumeIsMountedInTheJob(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id, 'mount_path' => '/data']);

        $this->applyJob($deployment, 'printenv');

        $volumes = $this->job($deployment)['spec']['template']['spec']['volumes'];
        $this->assertSame($deployment->name, $volumes[0]['persistentVolumeClaim']['claimName']);
    }

    /**
     * A second run replaces the first. A Job's spec is immutable, so this is a delete and a
     * create rather than an update - the same shape as the migration step.
     */
    public function testRunningAgainWithTheSameIdReplacesTheJob(): void {
        $deployment = $this->deploymentInANamespace();
        $this->applyJob($deployment, 'printenv', 'same-id');
        $first = $this->job($deployment)['metadata']['uid'];

        $this->applyJob($deployment, 'printenv', 'same-id');

        $this->assertNotSame($first, $this->job($deployment)['metadata']['uid']);
    }

    /**
     * A private registry needs its secret in the job too, or the pod cannot pull the image
     * and the command never runs. It is the same secret the workload uses; a job that
     * leaves it out fails on a workspace that works.
     */
    public function testThePullSecretIsCarriedIntoTheJob(): void {
        $deployment = $this->deploymentInANamespace();
        $specification = $deployment->findDeploymentSpecification();
        $specification->container_image_id = Fixtures::containerImage([
            'url' => 'nginx',
            'default_tag' => '1.29-alpine',
            'pull_secret' => 'registry-credentials',
        ])->id;
        $specification->save();

        $this->applyJob($deployment, 'printenv');

        $this->assertSame(
            [['name' => 'registry-credentials']],
            $this->job($deployment)['spec']['template']['spec']['imagePullSecrets']
        );
    }

    /**
     * An image from a public registry has no secret, and the job must then reference none
     * at all rather than one with an empty name - which is a secret the api server looks
     * for, does not find, and refuses the pod over.
     */
    public function testAnImageWithoutAPullSecretReferencesNone(): void {
        $deployment = $this->deploymentInANamespace();

        $this->applyJob($deployment, 'printenv');

        $this->assertArrayNotHasKey(
            'imagePullSecrets',
            $this->job($deployment)['spec']['template']['spec']
        );
    }

    /**
     * With RBAC on, the job runs as the workspace's own service account - the same
     * permissions the application has. Running as `default` instead means a command that
     * works in the running container fails in the job, for no reason anyone can see.
     */
    public function testTheJobRunsUnderTheDeploymentsServiceAccountWhenRbacIsOn(): void {
        $deployment = $this->deploymentInANamespace();
        $specification = $deployment->findDeploymentSpecification();
        $specification->enable_rbac = true;
        $specification->save();

        $this->applyJob($deployment, 'printenv');

        $this->assertSame(
            $deployment->name,
            $this->job($deployment)['spec']['template']['spec']['serviceAccountName']
        );
    }

    /**
     * The specification's variables reach the job as well as the deployment's own, and a
     * deployment level one of the same name wins - the same order the workload uses. The
     * command runs against the environment the application would see, or it is answering
     * about a different container than the one running.
     */
    public function testSpecificationVariablesAreCarriedOverAndOverriddenPerDeployment(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::specificationEnvironmentVariable([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'SHARED',
            'value' => 'from-specification',
        ]);
        Fixtures::specificationEnvironmentVariable([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'ONLY_ON_THE_SPECIFICATION',
            'value' => 'inherited',
        ]);
        Fixtures::deploymentEnvironmentVariable([
            'deployment_id' => $deployment->id,
            'name' => 'SHARED',
            'value' => 'from-deployment',
        ]);

        $this->applyJob($deployment, 'printenv');

        $env = $this->job($deployment)['spec']['template']['spec']['containers'][0]['env'];
        $this->assertContains(['name' => 'ONLY_ON_THE_SPECIFICATION', 'value' => 'inherited'], $env);
        $this->assertContains(['name' => 'SHARED', 'value' => 'from-deployment'], $env);
    }

    /**
     * The whole thing, once: apply the job, wait for it to finish, read what it printed,
     * and take it away again. That return value is what
     * `EnvironmentVariableCommitIdentification` reads a commit id out of, so an empty
     * string here is an auto update that never notices a new image.
     *
     * The four halves are only worth anything together - a wait that gives up early and a
     * log read that finds no pod both come back as nothing at all.
     */
    public function testRunningAJobWaitsForItReadsItsOutputAndCleansUp(): void {
        $deployment = $this->deploymentInANamespace();

        // The command waits before it prints, so a run that read the log without waiting
        // for the job would come back empty. Without that pause the wait cannot be told
        // from no wait at all - the pod is finished before the first watch event arrives.
        $startedAt = microtime(true);
        $log = (new RunJobHelper())->runJob($deployment, 'sleep 2; echo kso-ran-this');
        $took = microtime(true) - $startedAt;

        $this->assertNotNull($log, 'the run reported a failure');
        $this->assertStringContainsString('kso-ran-this', $log);
        $this->eventually(fn () => $this->jobs($deployment) === [], 'the job was left behind');

        // The watch has two ways to stop: the job reporting itself Complete, and a cap of
        // ten events. Only the first is the wait working - the cap is the safety net, and a
        // wait that always runs into it takes twenty seconds per call whatever the command
        // did. Nothing else here can tell them apart, because both end with the same log.
        //
        // The bound is loose on purpose: the whole run is a two second sleep plus an image
        // that is already on the node, so anything near half a minute means the watch sat
        // through its entire event budget.
        $this->assertLessThan(
            30,
            $took,
            'the watch ran to its event cap instead of stopping when the job completed'
        );
    }

    /**
     * A run against a namespace that is not there cannot apply anything, and says so with a
     * null rather than by throwing. Every caller treats null as "ask something else"; an
     * exception here would take down whatever was doing the asking.
     */
    public function testARunThatCannotBeAppliedComesBackAsNothing(): void {
        $deployment = $this->deploymentInTheTestNamespace(['namespace' => 'kso-test-no-such-namespace']);

        $this->assertNull((new RunJobHelper())->runJob($deployment, 'printenv'));
    }

    /**
     * Cleanup takes the pods with it, and takes them first. Deleting the job alone leaves
     * the api server to collect them in its own time, and a workspace where kso asks a
     * question every few minutes then carries a growing pile of finished pods against its
     * quota - which is a workspace that eventually cannot start the pod it is deploying.
     */
    public function testDeletingRemovesTheJobAndItsPods(): void {
        $deployment = $this->deploymentInANamespace();
        $this->applyJob($deployment, 'sleep 30', 'gone-soon');
        $this->eventually(fn () => $this->livePods() !== [], 'the job never started a pod');

        $this->call($deployment, 'deleteJob', 'gone-soon');

        $this->assertSame([], $this->livePods(), 'the pods should be gone before the call returns');
        $this->eventually(fn () => $this->jobs($deployment) === []);
    }

    // <editor-fold desc="Fixtures">

    /**
     * `applyJob` and `deleteJob` are private, and deliberately: the class has one public
     * entry point and it blocks on a watch. Reflection reaches the halves that can be
     * checked without a pod.
     */
    private function applyJob(Deployment $deployment, string $command, string $jobId = 'test-run'): void {
        $this->call($deployment, 'applyJob', $command, $jobId);
    }

    private function call(Deployment $deployment, string $method, string ...$arguments): mixed {
        $helper = new RunJobHelper();
        $reflected = (new \ReflectionClass(RunJobHelper::class))->getMethod($method);

        return $reflected->invoke($helper, $deployment, ...$arguments);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function deploymentInANamespace(array $overrides = []): Deployment {
        $deployment = $this->deploymentInTheTestNamespace($overrides);
        (new NamespaceStep())->startDeployCommand($deployment);

        return $deployment;
    }

    /**
     * @return array<string, mixed>
     */
    private function job(Deployment $deployment): array {
        return $this->jobs($deployment)[0];
    }

    /**
     * The pods in this test's namespace that have not been asked to go yet. A pod the api
     * server has accepted a delete for keeps answering until its grace period is up, and
     * it carries a `deletionTimestamp` the whole time - so counting those as still there
     * would make every cleanup look incomplete.
     *
     * @return array<int, array<string, mixed>>
     */
    private function livePods(): array {
        $body = json_decode($this->cluster()->call(
            'GET',
            "/api/v1/namespaces/{$this->testNamespace}/pods"
        )->getBody()->getContents(), true);

        return array_values(array_filter(
            $body['items'] ?? [],
            static fn ($pod) => ($pod['metadata']['deletionTimestamp'] ?? null) === null
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function jobs(Deployment $deployment): array {
        $body = json_decode($this->cluster()->call(
            'GET',
            "/apis/batch/v1/namespaces/{$this->testNamespace}/jobs"
        )->getBody()->getContents(), true);

        return $body['items'] ?? [];
    }

    // </editor-fold>

}
