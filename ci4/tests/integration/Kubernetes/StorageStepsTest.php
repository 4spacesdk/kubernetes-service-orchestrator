<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\PersistentVolumeClaimStep;
use App\Libraries\DeploymentSteps\PersistentVolumeStep;
use PHPUnit\Framework\Attributes\DataProvider;
use RenokiCo\PhpK8s\Kinds\K8sPersistentVolumeClaim;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;

/**
 * The two steps that hand a workspace its disk.
 *
 * Both build a list of resources rather than one, from two sources that are merged: the
 * volumes on the deployment and the volumes on its specification. The merge is where the
 * interesting behaviour is, and it is not visible in a single manifest.
 *
 * A persistent volume is **not namespaced**, so `ClusterTestCase` removes it by the
 * `<namespace>-<name>` prefix kso gives it.
 */
class StorageStepsTest extends ClusterTestCase {

    // <editor-fold desc="Claims">

    public function testAClaimIsCreatedWithTheSizeAndClassAskedFor(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume([
            'deployment_id' => $deployment->id,
            'capacity' => 5,
            'storage_class' => 'local-path',
        ]);
        $step = new PersistentVolumeClaimStep();

        $this->assertSame(DeploymentStepHelper::PersistentVolumeClaim_NotFound, $step->getStatus($deployment));

        $step->startDeployCommand($deployment);

        $this->assertSame(DeploymentStepHelper::PersistentVolumeClaim_Found, $step->getStatus($deployment));
        $claim = $this->cluster()->getPersistentVolumeClaimByName($deployment->name, $this->testNamespace);
        $this->assertSame('5Gi', $claim->getAttribute('spec.resources.requests')['storage']);
        $this->assertSame('local-path', $claim->getAttribute('spec.storageClassName'));
        $this->assertSame(['ReadWriteMany'], $claim->getAttribute('spec.accessModes'));
    }

    public function testTerminatingRemovesTheClaim(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id]);
        $step = new PersistentVolumeClaimStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::PersistentVolumeClaim_NotFound
        );
    }

    /**
     * Most deployments have no volume at all, and the step has to say so rather than apply
     * an empty list and report success.
     */
    public function testADeploymentWithNoVolumesIsRefused(): void {
        $deployment = $this->deploymentInANamespace();

        $this->assertSame('No volumes found', (new PersistentVolumeClaimStep())->validateDeployCommand($deployment));
    }

    /**
     * A claim's spec cannot be changed after it is created - the api server refuses
     * anything but growing `resources.requests` on an already bound claim. Saving such an
     * edit is refused now, so the only way here is a row changed behind the endpoint's
     * back; what it shows is why the refusal exists. The deploy stops at that step, the
     * workspace keeps the old disk, and the error is a raw 422 from Kubernetes.
     */
    public function testChangingAVolumeMakesTheNextDeployFail(): void {
        $deployment = $this->deploymentInANamespace();
        $volume = Fixtures::deploymentVolume([
            'deployment_id' => $deployment->id,
            'capacity' => 5,
            'storage_class' => 'local-path',
        ]);
        $step = new PersistentVolumeClaimStep();
        $step->startDeployCommand($deployment);

        $volume->storage_class = 'nfs';
        $volume->save();

        $this->expectException(KubernetesAPIException::class);
        $this->expectExceptionMessage('422');

        $step->startDeployCommand($deployment);
    }

    /**
     * The same immutability, reached a second way: both sources name their claim after the
     * deployment, so a deployment carrying a volume of its own **and** one from its
     * specification builds two resources with one name. The second is an update of the
     * first, and it is refused. Saving the pair is refused now; this is what rows from
     * before that still meet.
     */
    public function testTwoVolumesCollideOnOneName(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume([
            'deployment_id' => $deployment->id,
            'capacity' => 5,
            'storage_class' => 'local-path',
        ]);
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'capacity' => 20,
            'storage_class' => 'local-path',
        ]);

        try {
            (new PersistentVolumeClaimStep())->startDeployCommand($deployment);
            $this->fail('the second claim should have been refused');
        } catch (KubernetesAPIException) {
            // What we came for.
        }

        $claims = $this->cluster()->getAllPersistentVolumeClaims($this->testNamespace);
        $this->assertCount(1, $claims, 'only the first of the two was ever created');
        $this->assertSame('5Gi', $claims[0]->getAttribute('spec.resources.requests')['storage']);
    }

    // </editor-fold>

    // <editor-fold desc="Volumes">

    public function testAnNfsVolumeCarriesItsServerAndPath(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume([
            'deployment_id' => $deployment->id,
            'type' => 'nfs',
            'nfs_server' => '10.0.0.7',
            'nfs_path' => '/exports/tenant',
            'reclaim_policy' => 'Retain',
            'capacity' => 8,
        ]);
        $step = new PersistentVolumeStep();

        $step->startDeployCommand($deployment);

        $volume = $this->cluster()->getPersistentVolumeByName("{$this->testNamespace}-{$deployment->name}");
        $this->assertSame(['server' => '10.0.0.7', 'path' => '/exports/tenant'], $volume->getAttribute('spec.nfs'));
        $this->assertSame('8Gi', $volume->getAttribute('spec.capacity')['storage']);
        $this->assertSame('Retain', $volume->getAttribute('spec.persistentVolumeReclaimPolicy'));
        $this->assertSame(DeploymentStepHelper::PersistentVolume_Found, $step->getStatus($deployment));
    }

    /**
     * The reclaim policy decides whether a customer's data survives their workspace being
     * torn down. It is one word in a manifest and it is not reversible afterwards, so it is
     * worth knowing that the api server got the one we sent.
     */
    public function testTheReclaimPolicyIsWhatWasAskedFor(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume([
            'deployment_id' => $deployment->id,
            'reclaim_policy' => 'Delete',
        ]);

        (new PersistentVolumeStep())->startDeployCommand($deployment);

        $this->assertSame(
            'Delete',
            $this->cluster()->getPersistentVolumeByName("{$this->testNamespace}-{$deployment->name}")
                ->getAttribute('spec.persistentVolumeReclaimPolicy')
        );
    }

    /**
     * With a storage class filled in, the claim now binds to the volume kso made for it. It
     * used to ask for the class while the volume had none, so the volume sat unused and the
     * claim waited for - or got - a disk from somewhere else.
     */
    public function testAClaimWithAStorageClassBindsToItsOwnVolume(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id, 'storage_class' => 'kso-static']);

        (new PersistentVolumeStep())->startDeployCommand($deployment);
        (new PersistentVolumeClaimStep())->startDeployCommand($deployment);

        $volumeName = "{$this->testNamespace}-{$deployment->name}";
        $this->eventually(
            fn () => $this->cluster()->getPersistentVolumeClaimByName($deployment->name, $this->testNamespace)
                ->getAttribute('spec.volumeName') === $volumeName,
            'the claim did not bind to its own volume'
        );
    }

    /**
     * A new volume is reserved for its deployment's claim. Another claim asking for the same
     * class and size - another workspace's, say - cannot take it first.
     */
    public function testANewVolumeCannotBeTakenByAnotherClaim(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id, 'storage_class' => 'kso-static', 'capacity' => 1]);
        (new PersistentVolumeStep())->startDeployCommand($deployment);

        $other = new K8sPersistentVolumeClaim($this->cluster());
        $other->setName('someone-else')
            ->setNamespace($this->testNamespace)
            ->setCapacity(1)
            ->setAccessModes(['ReadWriteMany'])
            ->setStorageClass('kso-static')
            ->create();
        sleep(3);

        $this->assertSame('Pending', $this->cluster()->getPersistentVolumeClaimByName('someone-else', $this->testNamespace)->getAttribute('status.phase'));
    }

    /**
     * A second deploy must leave a bound volume bound. The update replaces the whole object,
     * and before the live `claimRef` and `pv.kubernetes.io/*` annotations were carried over,
     * every deploy of this step turned a bound volume `Available` - with no class as much as
     * with one.
     */
    #[DataProvider('storageClasses')]
    public function testDeployingAgainKeepsABoundVolumeBound(string $storageClass): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id, 'storage_class' => $storageClass]);
        (new PersistentVolumeStep())->startDeployCommand($deployment);
        (new PersistentVolumeClaimStep())->startDeployCommand($deployment);
        $volumeName = "{$this->testNamespace}-{$deployment->name}";
        $this->eventually(fn () => $this->cluster()->getPersistentVolumeByName($volumeName)->getAttribute('status.phase') === 'Bound');

        (new PersistentVolumeStep())->startDeployCommand($deployment);
        sleep(3);

        $this->assertSame('Bound', $this->cluster()->getPersistentVolumeByName($volumeName)->getAttribute('status.phase'));
        $this->assertSame('Bound', $this->cluster()->getPersistentVolumeClaimByName($deployment->name, $this->testNamespace)->getAttribute('status.phase'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function storageClasses(): array {
        return ['no class' => [''], 'a class' => ['kso-static']];
    }

    public function testTerminatingRemovesTheVolume(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id]);
        $step = new PersistentVolumeStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::PersistentVolume_NotFound
        );
    }

    /**
     * The volume step previews a *list*, because it builds one. An empty remote list is
     * how it says the volume has not been created yet - there is no null here, unlike the
     * steps that build a single resource, so an operator reading the preview sees the
     * local half alone.
     */
    public function testThePreviewOfAVolumeShowsTheLocalHalfBeforeItIsApplied(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id, 'capacity' => 7]);

        $preview = $this->preview($deployment);

        $this->assertCount(1, $preview['local']);
        $this->assertSame('7Gi', $preview['local'][0]['spec']['capacity']['storage']);
        $this->assertSame([], $preview['remote']);
    }

    /**
     * Once it is out there the remote half appears, stripped of what the api server filled
     * in so the two can be compared. `status` is left in - a volume reports whether it is
     * Available or Bound, and that is worth seeing.
     */
    public function testThePreviewOfALiveVolumeDropsWhatTheClusterAddedToIt(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume(['deployment_id' => $deployment->id, 'capacity' => 7]);
        (new PersistentVolumeStep())->startDeployCommand($deployment);

        $preview = $this->preview($deployment);

        $this->assertCount(1, $preview['remote']);
        $this->assertSame("{$this->testNamespace}-{$deployment->name}", $preview['remote'][0]['metadata']['name']);
        $this->assertArrayNotHasKey('uid', $preview['remote'][0]['metadata']);
        $this->assertArrayNotHasKey('resourceVersion', $preview['remote'][0]['metadata']);
        $this->assertArrayNotHasKey('creationTimestamp', $preview['remote'][0]['metadata']);
        $this->assertArrayNotHasKey('managedFields', $preview['remote'][0]['metadata']);
    }

    /**
     * A deployment with no volumes previews two empty lists rather than failing. The step
     * is only in the list at all when a volume exists, but the endpoint behind it can be
     * asked directly.
     */
    public function testThePreviewOfADeploymentWithoutVolumesIsEmpty(): void {
        $preview = $this->preview($this->deploymentInANamespace());

        $this->assertSame([], $preview['local']);
        $this->assertSame([], $preview['remote']);
    }

    /**
     * The step's preview, decoded into the two lists it is made of. Each entry is itself a
     * json string, which is why this is not one `json_decode`.
     *
     * @return array{local: array<array<string, mixed>>, remote: array<array<string, mixed>>}
     */
    private function preview(Deployment $deployment): array {
        $envelope = json_decode((new PersistentVolumeStep())->getPreview($deployment), true);

        return [
            'local' => array_map(static fn (string $one) => json_decode($one, true), $envelope['local']),
            'remote' => array_map(static fn (string $one) => json_decode($one, true), $envelope['remote']),
        ];
    }

    // </editor-fold>

    private function deploymentInANamespace(): Deployment {
        $deployment = $this->deploymentInTheTestNamespace();
        (new NamespaceStep())->startDeployCommand($deployment);

        return $deployment;
    }

}
