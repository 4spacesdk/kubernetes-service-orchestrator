<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use App\Libraries\DeploymentSteps\PersistentVolumeClaimStep;
use App\ManifestTestCase;

/**
 * The claim that hands a deployment's pods their volume.
 *
 * Short manifest, built from the same rows as the PersistentVolume, and the two have to
 * agree about capacity and storage class or the claim never binds. A claim that does not
 * bind leaves the pods Pending with nothing obviously wrong in the deployment itself.
 */
class PersistentVolumeClaimStepTest extends ManifestTestCase {

    /**
     * The one trigger is what makes an edited volume reach the cluster without a full
     * deploy - and the claim is the resource that edit cannot actually change, see `StorageStepsTest`.
     */
    public function testTheStepIsWiredInAtTheDeploymentLevel(): void {
        $step = new PersistentVolumeClaimStep();
        $deployment = $this->deploymentWithVolume();

        $this->assertSame(DeploymentSteps::PersistentVolumeClaim, $step->getIdentifier());
        $this->assertSame(DeploymentStepLevels::Deployment, $step->getLevel());
        $this->assertSame('Persistent Volume Claim', $step->getName());
        $this->assertSame([DeploymentStepTriggers::Deployment_Volume_Updated], $step->getTriggers());
        $this->assertSame(DeploymentStepHelper::PersistentVolumeClaim_Found, $step->getSuccessStatus($deployment));
        $this->assertTrue($step->hasPreviewCommand());
        $this->assertTrue($step->hasStatusCommand());
        $this->assertTrue($step->hasDeployCommand());
        $this->assertTrue($step->hasTerminateCommand());
    }

    /**
     * A claim's own status says whether it is Bound, which is the thing an operator wants
     * to know - but kso does not read it, and the flags say so.
     */
    public function testTheStepReportsNoEventsOrStatusOfItsOwn(): void {
        $step = new PersistentVolumeClaimStep();
        $deployment = $this->deploymentWithVolume();

        $this->assertFalse($step->hasKubernetesEvents());
        $this->assertSame([], $step->getKubernetesEvents($deployment));

        $this->assertFalse($step->hasKubernetesStatus());
        $this->assertSame([], $step->getKubernetesStatus($deployment));
    }

    /**
     * Validation here never looks at the cluster: the name, and then every volume row in
     * turn. A claim is built from the row, so a row that is incomplete builds a manifest
     * the api server rejects with a message about a field rather than about a volume.
     */
    public function testDeployIsRefusedWithoutAName(): void {
        $deployment = $this->deploymentWithVolume([], ['name' => '']);

        $this->assertSame('Missing name', (new PersistentVolumeClaimStep())->validateDeployCommand($deployment));
    }

    public function testDeployIsAllowedWhenEveryVolumeRowIsComplete(): void {
        $deployment = $this->deploymentWithVolume();
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
        ]);

        $this->assertNull((new PersistentVolumeClaimStep())->validateDeployCommand($deployment));
    }

    /**
     * Both sources are checked, and the message is the volume's own. An nfs volume without
     * a server is the case that reaches the cluster as a claim that never binds.
     */
    public function testAnIncompleteVolumeOnTheDeploymentIsReported(): void {
        $deployment = $this->deploymentWithVolume(['nfs_server' => '']);

        $this->assertSame('Missing nfs server', (new PersistentVolumeClaimStep())->validateDeployCommand($deployment));
    }

    public function testAnIncompleteVolumeOnTheSpecificationIsReported(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'mount_path' => '',
        ]);

        $this->assertSame('Missing mount path', (new PersistentVolumeClaimStep())->validateDeployCommand($deployment));
    }

    /**
     * Unlike the volume, the claim is namespaced, so it keeps the plain deployment name.
     */
    public function testClaimIsNamedAndNamespacedAfterTheDeployment(): void {
        $deployment = $this->deploymentWithVolume();

        $manifest = $this->build($deployment)[0];

        $this->assertSame($deployment->name, $manifest['metadata']['name']);
        $this->assertSame($deployment->namespace, $manifest['metadata']['namespace']);
    }

    /**
     * The volume states a capacity, the claim requests one. Same number, different place
     * in the manifest.
     */
    public function testCapacityIsARequestInGibibytes(): void {
        $deployment = $this->deploymentWithVolume(['capacity' => 50]);

        $this->assertSame('50Gi', $this->spec($deployment)['resources']['requests']['storage']);
    }

    public function testStorageClassComesFromTheVolumeRow(): void {
        $deployment = $this->deploymentWithVolume(['storage_class' => 'standard-rwx']);

        $this->assertSame('standard-rwx', $this->spec($deployment)['storageClassName']);
    }

    public function testAccessModeIsAlwaysReadWriteMany(): void {
        $deployment = $this->deploymentWithVolume();

        $this->assertSame(['ReadWriteMany'], $this->spec($deployment)['accessModes']);
    }

    /**
     * The claim names no volume, so Kubernetes matches it to one by capacity, access mode
     * and storage class. With a class set here and none on the PersistentVolume, the match
     * is with a dynamically provisioned volume rather than the one the previous step made.
     * See the same note in PersistentVolumeStepTest.
     */
    public function testClaimDoesNotBindToANamedVolume(): void {
        $deployment = $this->deploymentWithVolume();

        $this->assertArrayNotHasKey('volumeName', $this->spec($deployment));
    }

    public function testNoVolumesGeneratesNothing(): void {
        $deployment = Fixtures::deployableDeployment();

        $this->assertCount(0, $this->build($deployment));
    }

    public function testSpecificationVolumeAlsoGeneratesAClaim(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'storage_class' => 'from-specification',
        ]);

        $manifests = $this->build($deployment);

        $this->assertCount(1, $manifests);
        $this->assertSame('from-specification', $manifests[0]['spec']['storageClassName']);
    }

    /**
     * Same trap as on the volume: two volume rows build two claims with one name.
     */
    public function testTwoVolumesCollideOnOneName(): void {
        $deployment = $this->deploymentWithVolume();
        Fixtures::specificationVolume([
            'deployment_specification_id' => $deployment->deployment_specification_id,
        ]);

        $manifests = $this->build($deployment);

        $this->assertCount(2, $manifests);
        $this->assertSame($manifests[0]['metadata']['name'], $manifests[1]['metadata']['name']);
    }

    // <editor-fold desc="Fixtures and reading">

    /**
     * @param array<string, mixed> $volume
     * @param array<string, mixed> $deploymentOverrides
     */
    private function deploymentWithVolume(array $volume = [], array $deploymentOverrides = []): Deployment {
        $deployment = Fixtures::deployableDeployment($deploymentOverrides);
        Fixtures::deploymentVolume(array_merge(['deployment_id' => $deployment->id], $volume));

        return $deployment;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function build(Deployment $deployment): array {
        return $this->manifests(PersistentVolumeClaimStep::class, $deployment);
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(Deployment $deployment): array {
        return $this->build($deployment)[0]['spec'];
    }

    // </editor-fold>

}
