<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\PersistentVolumeStep;
use App\ManifestTestCase;

/**
 * The PersistentVolume behind a deployment's storage.
 *
 * This is the manifest where a mistake costs data rather than uptime: the reclaim policy
 * decides whether the disk survives a Terminate, and it is the only thing that does. The
 * tests below hold today's behaviour in place so it can be changed on purpose.
 */
class PersistentVolumeStepTest extends ManifestTestCase {

    /**
     * A PersistentVolume is cluster scoped, so two workspaces cannot each have one called
     * after their deployment. The namespace is folded into the name instead.
     */
    public function testVolumeIsClusterScopedAndCarriesTheNamespaceInItsName(): void {
        $deployment = $this->deploymentWithVolume();

        $manifest = $this->build($deployment)[0];

        $this->assertSame("{$deployment->namespace}-{$deployment->name}", $manifest['metadata']['name']);
        $this->assertArrayNotHasKey('namespace', $manifest['metadata']);
    }

    /**
     * Capacity is stored as a bare number, and php-k8s appends the unit. A change of unit
     * here would resize every volume in every installation.
     */
    public function testCapacityIsWrittenInGibibytes(): void {
        $deployment = $this->deploymentWithVolume(['capacity' => 50]);

        $this->assertSame('50Gi', $this->spec($deployment)['capacity']['storage']);
    }

    /**
     * The field everything turns on: `Retain` keeps the disk when the volume is deleted,
     * `Delete` throws it away. Terminate deletes the PV either way.
     */
    public function testReclaimPolicyIsCarriedOver(): void {
        $retain = $this->deploymentWithVolume(['reclaim_policy' => 'Retain']);
        $delete = $this->deploymentWithVolume(['reclaim_policy' => 'Delete']);

        $this->assertSame('Retain', $this->spec($retain)['persistentVolumeReclaimPolicy']);
        $this->assertSame('Delete', $this->spec($delete)['persistentVolumeReclaimPolicy']);
    }

    public function testVolumeModeIsCarriedOver(): void {
        $deployment = $this->deploymentWithVolume(['volume_mode' => 'Block']);

        $this->assertSame('Block', $this->spec($deployment)['volumeMode']);
    }

    /**
     * Hardcoded, not configurable. Several pods of the same deployment mount the same
     * volume, which ReadWriteOnce would not allow.
     */
    public function testAccessModeIsAlwaysReadWriteMany(): void {
        $deployment = $this->deploymentWithVolume();

        $this->assertSame(['ReadWriteMany'], $this->spec($deployment)['accessModes']);
    }

    public function testNfsVolumeGetsServerAndPath(): void {
        $deployment = $this->deploymentWithVolume([
            'type' => 'nfs',
            'nfs_server' => 'nfs.example.org',
            'nfs_path' => '/exports/tenant',
        ]);

        $spec = $this->spec($deployment);

        $this->assertSame('nfs.example.org', $spec['nfs']['server']);
        $this->assertSame('/exports/tenant', $spec['nfs']['path']);
        $this->assertArrayNotHasKey('csi', $spec);
    }

    public function testCsiVolumeGetsDriverAndHandle(): void {
        $deployment = $this->deploymentWithVolume([
            'type' => 'csi',
            'csi_driver' => 'filestore.csi.storage.gke.io',
            'csi_volume_handle' => 'modeInstance/europe-north1-a/instance/share',
        ]);

        $spec = $this->spec($deployment);

        $this->assertSame('filestore.csi.storage.gke.io', $spec['csi']['driver']);
        $this->assertSame('modeInstance/europe-north1-a/instance/share', $spec['csi']['volumeHandle']);
        $this->assertArrayNotHasKey('nfs', $spec);
    }

    /**
     * The handle is the only volume field that takes variables, which is how one
     * specification can point every workspace at its own share.
     */
    public function testCsiVolumeHandleHasVariablesApplied(): void {
        $deployment = $this->deploymentWithVolume([
            'type' => 'csi',
            'csi_driver' => 'filestore.csi.storage.gke.io',
            'csi_volume_handle' => 'modeInstance/zone/instance/${namespace}-${deployment.name}',
        ]);

        $this->assertSame(
            "modeInstance/zone/instance/{$deployment->namespace}-{$deployment->name}",
            $this->spec($deployment)['csi']['volumeHandle']
        );
    }

    /**
     * An empty driver or handle is left out rather than written as an empty string, which
     * Kubernetes would reject.
     */
    public function testEmptyCsiFieldsAreLeftOut(): void {
        $deployment = $this->deploymentWithVolume([
            'type' => 'csi',
            'csi_driver' => '',
            'csi_volume_handle' => '',
        ]);

        $this->assertArrayNotHasKey('csi', $this->spec($deployment));
    }

    public function testNoVolumesGeneratesNothing(): void {
        $deployment = Fixtures::deployableDeployment();

        $this->assertCount(0, $this->build($deployment));
    }

    /**
     * A volume can come from the specification instead, and then every deployment of that
     * specification gets one.
     */
    public function testSpecificationVolumeAlsoGeneratesAVolume(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'capacity' => 20,
        ]);

        $manifests = $this->build($deployment);

        $this->assertCount(1, $manifests);
        $this->assertSame('20Gi', $manifests[0]['spec']['capacity']['storage']);
    }

    /**
     * Today's behaviour, and it is a trap. Unlike the Ingress step, which suffixes its
     * second object, every volume is named after the deployment alone - so a deployment
     * with two volumes builds two manifests with the same name, and applying them leaves
     * one volume in the cluster with the last one's spec. Saving a second volume is refused
     * now (`Deployment::volumeCountProblem()`); this is what rows from before that still
     * build.
     */
    public function testTwoVolumesCollideOnOneName(): void {
        $deployment = $this->deploymentWithVolume(['capacity' => 10]);
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'capacity' => 20,
        ]);

        $manifests = $this->build($deployment);

        $this->assertCount(2, $manifests);
        $this->assertSame($manifests[0]['metadata']['name'], $manifests[1]['metadata']['name']);
    }

    /**
     * The volume carries the row's class, like the claim does. A claim that asks for a class
     * only binds to a volume of the same one - without it here, a filled-in class left the
     * volume unused and the claim provisioned a disk of its own somewhere else.
     */
    public function testTheStorageClassIsWrittenOnTheVolumeAsOnTheClaim(): void {
        $deployment = $this->deploymentWithVolume(['storage_class' => 'standard-rwx']);

        $this->assertSame('standard-rwx', $this->spec($deployment)['storageClassName']);
    }

    /**
     * An empty class is sent as an empty string, not left out: `""` means no class, where a
     * missing field would let a default class be filled in on the claim's side.
     */
    public function testAnEmptyStorageClassIsSentAsAnEmptyString(): void {
        $deployment = $this->deploymentWithVolume(['storage_class' => '']);

        $this->assertSame('', $this->spec($deployment)['storageClassName']);
    }

    /**
     * The claim reservation is only added when the volume is created, which takes a
     * cluster - see StorageStepsTest. The manifest itself never carries it.
     */
    public function testTheManifestCarriesNoClaimReservation(): void {
        $deployment = $this->deploymentWithVolume();

        $this->assertArrayNotHasKey('claimRef', $this->spec($deployment));
    }

    /**
     * A volume from the specification takes the same two CSI branches as one from the
     * deployment, through a second copy of the code. The copy is the point: a fix applied
     * to one half and not the other is invisible unless both are asked.
     */
    public function testASpecificationVolumeGetsItsDriverAndHandleToo(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'type' => 'csi',
            'csi_driver' => 'filestore.csi.storage.gke.io',
            'csi_volume_handle' => 'modeInstance/zone/instance/${namespace}',
        ]);

        $spec = $this->spec($deployment);

        $this->assertSame('filestore.csi.storage.gke.io', $spec['csi']['driver']);
        $this->assertSame("modeInstance/zone/instance/{$deployment->namespace}", $spec['csi']['volumeHandle']);
    }

    /**
     * The hardcoded fields are hardcoded in both copies. `ReadWriteOnce` here would let
     * only one node mount the volume, so a deployment of two replicas would have a pod
     * stuck Pending - and the specification copy is the one nobody looks at.
     */
    public function testASpecificationVolumeIsAlsoReadWriteMany(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'volume_mode' => 'Block',
            'reclaim_policy' => 'Delete',
        ]);

        $spec = $this->spec($deployment);

        $this->assertSame(['ReadWriteMany'], $spec['accessModes']);
        $this->assertSame('Block', $spec['volumeMode']);
        $this->assertSame('Delete', $spec['persistentVolumeReclaimPolicy']);
    }

    public function testASpecificationVolumeWithEmptyCsiFieldsLeavesThemOut(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'type' => 'csi',
            'csi_driver' => '',
            'csi_volume_handle' => '',
        ]);

        $this->assertArrayNotHasKey('csi', $this->spec($deployment));
    }

    // <editor-fold desc="What the step says about itself">

    /**
     * The description the API hands the frontend. Unlike the RBAC steps this one listens
     * for a trigger: editing a workspace's volume reapplies the PersistentVolume without
     * anyone pressing deploy.
     */
    public function testTheStepDescribesItself(): void {
        $this->assertSame([
            'identifier' => 'persistent-volume',
            'level' => 'deployment',
            'name' => 'Persistent Volume',
            'hasPreviewCommand' => true,
            'hasStatusCommand' => true,
            'hasDeployCommand' => true,
            'hasKubernetesEvents' => false,
            'hasKubernetesStatus' => false,
            'hasTerminateCommand' => true,
        ], (new PersistentVolumeStep())->toArray());

        $this->assertSame(['deployment-volume-updated'], (new PersistentVolumeStep())->getTriggers());
    }

    public function testTheStepReportsNoEventsOrStatusFromKubernetes(): void {
        $deployment = $this->deploymentWithVolume();
        $step = new PersistentVolumeStep();

        $this->assertSame([], $step->getKubernetesEvents($deployment));
        $this->assertSame([], $step->getKubernetesStatus($deployment));
    }

    /**
     * A deployment that has reached this step always wants its volume, so the only
     * finished state is that it is there. Contrast the RBAC steps, whose success depends
     * on what is configured.
     */
    public function testTheVolumeIsAlwaysExpected(): void {
        $this->assertSame('found', (new PersistentVolumeStep())->getSuccessStatus($this->deploymentWithVolume()));
    }

    // </editor-fold>

    // <editor-fold desc="What is refused before anything is sent">

    public function testADeploymentWithoutANameIsRefused(): void {
        $deployment = $this->deploymentWithVolume();
        $deployment->name = '';

        $this->assertSame('Missing name', (new PersistentVolumeStep())->validateDeployCommand($deployment));
    }

    /**
     * Most deployments have no volume at all. The step has to say so rather than apply an
     * empty list and report success, because `getStatus()` on an empty list answers
     * `found` - nothing is missing when nothing is expected.
     */
    public function testADeploymentWithNoVolumesAtAllIsRefused(): void {
        $deployment = Fixtures::deployableDeployment();

        $this->assertSame('No volumes found', (new PersistentVolumeStep())->validateDeployCommand($deployment));
    }

    /**
     * An NFS volume with no server would be sent as a manifest Kubernetes refuses. The row
     * validates itself; this is about the step asking it before it applies anything.
     */
    public function testAnInvalidVolumeOnTheDeploymentIsRefused(): void {
        $deployment = $this->deploymentWithVolume(['nfs_server' => '']);

        $this->assertSame('Missing nfs server', (new PersistentVolumeStep())->validateDeployCommand($deployment));
    }

    /**
     * The same check on the other source. Both are looped, and a volume from the
     * specification reaches exactly as far into the cluster as one from the deployment.
     */
    public function testAnInvalidVolumeOnTheSpecificationIsRefused(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'type' => 'csi',
            'csi_driver' => '',
        ]);

        $this->assertSame('Missing csi_driver', (new PersistentVolumeStep())->validateDeployCommand($deployment));
    }

    public function testAVolumeThatIsFilledInIsAccepted(): void {
        $this->assertNull((new PersistentVolumeStep())->validateDeployCommand($this->deploymentWithVolume()));
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures and reading">

    /**
     * @param array<string, mixed> $volume
     */
    private function deploymentWithVolume(array $volume = []): Deployment {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::deploymentVolume(array_merge(['deployment_id' => $deployment->id], $volume));

        return $deployment;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function build(Deployment $deployment): array {
        return $this->manifests(PersistentVolumeStep::class, $deployment);
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(Deployment $deployment): array {
        return $this->build($deployment)[0]['spec'];
    }

    // </editor-fold>

}
