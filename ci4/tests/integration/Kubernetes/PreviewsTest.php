<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\ServiceAccountStep;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the user is shown before they press deploy.
 *
 * `getPreview()` returns the manifest kso would send next to the one the cluster is
 * holding, and the UI diffs the two. Every step has one, and none of them can be reached
 * without a cluster: the whole method is about reading a resource back.
 *
 * Most of each one is a list of `unset()` calls. They are there because the api server
 * hands back a great deal nobody wrote - a uid, a resource version, managed fields, every
 * default it filled in - and a diff that shows all of it is a diff nobody reads. That the
 * list is right is exactly what a manifest test cannot say.
 */
class PreviewsTest extends ClusterTestCase {

    /**
     * Nothing applied yet, so there is nothing to compare against and the preview says so
     * rather than inventing an empty remote.
     */
    public function testAPreviewOfSomethingNotYetAppliedHasNoRemote(): void {
        $deployment = $this->deploymentInANamespace();

        $preview = $this->preview(new DeploymentStep(), $deployment);

        $this->assertNull($preview['remote']);
        $this->assertSame($deployment->name, json_decode($preview['local'], true)['metadata']['name']);
    }

    public function testAPreviewOfSomethingAppliedShowsWhatTheClusterHolds(): void {
        $deployment = $this->deploymentInANamespace(['replicas' => 1]);
        $step = new DeploymentStep();
        $step->startDeployCommand($deployment);

        $preview = $this->preview($step, $deployment);

        $remote = json_decode($preview['remote'], true);
        $this->assertSame(1, $remote['spec']['replicas']);
        $this->assertSame($this->testNamespace, $remote['metadata']['namespace']);
    }

    /**
     * The point of the whole thing: local is what would be sent, remote is what is there,
     * and the difference is the change about to be made.
     */
    public function testThePreviewShowsTheChangeThatIsAboutToBeMade(): void {
        $deployment = $this->deploymentInANamespace(['replicas' => 1]);
        $step = new DeploymentStep();
        $step->startDeployCommand($deployment);

        $deployment->replicas = 4;
        $deployment->save();
        $preview = $this->preview($step, $deployment);

        $this->assertSame(4, json_decode($preview['local'], true)['spec']['replicas']);
        $this->assertSame(1, json_decode($preview['remote'], true)['spec']['replicas']);
    }

    /**
     * Every one of these is written by the api server and none of them by kso. Left in,
     * each shows up as a change the user never made - and `managedFields` alone is longer
     * than the manifest it is attached to.
     */
    #[DataProvider('theNoiseTheApiServerAdds')]
    public function testTheApiServersOwnBookkeepingIsStrippedFromTheRemote(string $field): void {
        $deployment = $this->deploymentInANamespace();
        $step = new DeploymentStep();
        $step->startDeployCommand($deployment);

        $remote = json_decode($this->preview($step, $deployment)['remote'], true);

        $this->assertArrayNotHasKey($field, $remote['metadata'], "metadata.$field should not be in a preview");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function theNoiseTheApiServerAdds(): array {
        return [
            'uid' => ['uid'],
            'resourceVersion' => ['resourceVersion'],
            'generation' => ['generation'],
            'creationTimestamp' => ['creationTimestamp'],
            'managedFields' => ['managedFields'],
        ];
    }

    /**
     * The rest of the list, below `metadata`. Two thirds of `getPreview()` is stripping
     * these, and they are the ones that would actually be mistaken for a change: a
     * `strategy` or a `terminationGracePeriodSeconds` reads as a setting somebody chose,
     * where a `uid` is obviously machinery. Every one of them is a default the api server
     * filled in and none of them was ever sent by kso.
     */
    #[DataProvider('theDefaultsTheApiServerFillsIn')]
    public function testTheDefaultsTheApiServerFillsInAreStrippedFromTheRemote(string $path): void {
        $deployment = $this->deploymentInANamespace();
        $step = new DeploymentStep();
        $step->startDeployCommand($deployment);

        $remote = json_decode($this->preview($step, $deployment)['remote'], true);

        $this->assertPathIsAbsent($remote, $path);
    }

    /**
     * Each of these was confirmed present on the resource the api server hands back, so
     * the assertion above fails if its `unset()` is removed rather than passing because
     * the field was never there in the first place.
     *
     * `kubectl.kubernetes.io/last-applied-configuration` is deliberately not in this list:
     * kso applies through the api directly, and only `kubectl apply` ever writes that
     * annotation, so on a resource kso created it is never present. Removing its `unset()`
     * is an equivalent mutation here - it would only matter for a resource somebody had
     * also touched with kubectl, which is not something a test can arrange.
     *
     * @return array<string, array{0: string}>
     */
    public static function theDefaultsTheApiServerFillsIn(): array {
        $paths = [
            'spec.strategy',
            'spec.revisionHistoryLimit',
            'spec.progressDeadlineSeconds',
            'spec.template.metadata.creationTimestamp',
            'spec.template.metadata.annotations',
            'spec.template.spec.dnsPolicy',
            'spec.template.spec.schedulerName',
            'spec.template.spec.restartPolicy',
            'spec.template.spec.terminationGracePeriodSeconds',
            'spec.template.spec.containers.0.terminationMessagePath',
            'spec.template.spec.containers.0.terminationMessagePolicy',
        ];

        return array_combine($paths, array_map(static fn ($path) => [$path], $paths));
    }

    /**
     * The two annotations that are only there once something has happened to the
     * Deployment: the revision the controller counts up on every rollout, and the change
     * cause kso itself wrote on the last deploy. Both change on every deploy and neither
     * is part of what the next one would send, so a preview that kept them would show a
     * difference before the user had changed anything at all.
     */
    public function testTheAnnotationsARolloutLeavesBehindAreNotPartOfTheDiff(): void {
        $deployment = $this->deploymentInANamespace();
        $step = new DeploymentStep();
        $step->startDeployCommand($deployment, 'version updated to 1.29-alpine');

        // The revision is written by the deployment controller rather than by the apply,
        // so it turns up a moment later - and a preview taken before it does would pass
        // whether or not it is stripped.
        $this->eventually(fn () => isset($this->cluster()
            ->getDeploymentByName($deployment->name, $this->testNamespace)
            ->getAnnotations()['deployment.kubernetes.io/revision']));

        $annotations = json_decode($this->preview($step, $deployment)['remote'], true)['metadata']['annotations'];

        $this->assertArrayNotHasKey('deployment.kubernetes.io/revision', $annotations);
        $this->assertArrayNotHasKey('kubernetes.io/change-cause', $annotations);
        $this->assertSame('kso', $annotations['app.kubernetes.io/managed-by'], 'kso\'s own marker stays');
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function assertPathIsAbsent(array $manifest, string $path): void {
        $walked = [];

        foreach (explode('.', $path) as $segment) {
            $this->assertIsArray($manifest, implode('.', $walked) . ' should have been an array');

            if (!array_key_exists($segment, $manifest)) {
                $this->assertTrue(true);

                return;
            }

            $walked[] = $segment;
            $manifest = $manifest[$segment];
        }

        $this->fail("$path should not be in a preview");
    }

    /**
     * `status` is the largest single thing the api server adds, it changes on its own while
     * nobody is looking, and it is never something a deploy would send. A preview that kept
     * it would differ from one second to the next.
     */
    public function testTheStatusTheControllerWritesIsNotPartOfTheDiff(): void {
        $deployment = $this->deploymentInANamespace();
        $step = new DeploymentStep();
        $step->startDeployCommand($deployment);

        $remote = json_decode($this->preview($step, $deployment)['remote'], true);

        $this->assertArrayNotHasKey('status', $remote);
    }

    /**
     * A second step, with a manifest of a different shape, so the test says something about
     * previews rather than about one step.
     */
    public function testAServiceAccountPreviewWorksTheSameWay(): void {
        $deployment = $this->deploymentInANamespace();
        $step = new ServiceAccountStep();

        $this->assertNull($this->preview($step, $deployment)['remote']);

        $step->startDeployCommand($deployment);

        $remote = json_decode($this->preview($step, $deployment)['remote'], true);
        $this->assertSame($deployment->name, $remote['metadata']['name']);
        $this->assertArrayNotHasKey('uid', $remote['metadata']);
    }

    /**
     * @return array<string, mixed>
     */
    private function preview(object $step, Deployment $deployment): array {
        return json_decode($step->getPreview($deployment), true);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function deploymentInANamespace(array $overrides = []): Deployment {
        $deployment = $this->deploymentInTheTestNamespace($overrides);
        (new NamespaceStep())->startDeployCommand($deployment);

        return $deployment;
    }

}
