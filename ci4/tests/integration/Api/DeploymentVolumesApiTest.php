<?php namespace App\Tests\Integration\Api;

use App\ClusterControllerTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\PersistentVolumeClaimStep;
use App\Libraries\DeploymentSteps\PersistentVolumeStep;

/**
 * Editing a volume that already has its disk.
 *
 * A claim's spec cannot be changed once the claim exists: the api server answers `422` and
 * the deploy stops at that step, with the workspace still on the old disk and the error a
 * raw Kubernetes message. The edit is refused when it is saved instead, which is the only
 * place it can be said in terms of the field the user just changed.
 */
class DeploymentVolumesApiTest extends ClusterControllerTestCase {

    public function testChangingAVolumeThatHasItsDiskIsRefused(): void {
        $deployment = $this->deploymentWithAClaim();

        $body = $this->putVolumes($deployment, [$this->volume(['capacity' => 50])]);

        $this->assertStringContainsString('already has its disk', $body['error'] ?? '');
        $this->assertSame(
            20,
            (int) db_connect()->table('deployment_volumes')->where('deployment_id', $deployment->id)->get()->getRowArray()['capacity'],
            'the stored volume is untouched'
        );
    }

    /**
     * Mount path and sub path belong to the pod, not to the claim, so they can still be
     * changed on a deployment that is running.
     */
    public function testTheMountPathCanStillBeChanged(): void {
        $deployment = $this->deploymentWithAClaim();

        $body = $this->putVolumes($deployment, [$this->volume(['mount_path' => '/somewhere-else'])]);

        $this->assertSame('OK', $body['status']);
    }

    /**
     * Without a claim in the cluster there is nothing to collide with, so the same edit is
     * stored.
     */
    public function testChangingAVolumeThatWasNeverDeployedIsStored(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume(array_merge(['deployment_id' => $deployment->id], $this->volume()));

        $body = $this->putVolumes($deployment, [$this->volume(['capacity' => 50])]);

        $this->assertSame('OK', $body['status']);
    }

    /**
     * The disk's source - here a CSI volume's attributes - is not the claim's. A claim bound to a
     * disk its storage class made, or to none, leaves kso's disk unused, so the edit is stored and
     * the next deploy makes the disk again.
     */
    public function testTheDisksSourceCanBeChangedWhileTheClaimIsNotOnKsosDisk(): void {
        $deployment = $this->deploymentWithAClaim();

        $body = $this->putVolumes($deployment, [$this->volume(['nfs_path' => '/exports/other', 'mount_options' => 'vers=4'])]);

        $this->assertSame('OK', $body['status']);
    }

    /**
     * While the claim is bound to kso's own disk, the disk's source is fixed as the claim is.
     */
    public function testTheSourceOfADiskInUseCannotBeChanged(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume(array_merge(['deployment_id' => $deployment->id], $this->volume(['storage_class' => 'kso-static'])));
        (new PersistentVolumeStep())->startDeployCommand($deployment);
        (new PersistentVolumeClaimStep())->startDeployCommand($deployment);
        // A claim made before its volume is in the controller's cache waits for the next resync,
        // every 15 seconds - longer than `eventually()` waits.
        for ($second = 0; $second < 20 && !(new PersistentVolumeClaimStep())->claimIsOnKsosVolume($deployment); $second++) {
            sleep(1);
        }
        $this->assertTrue((new PersistentVolumeClaimStep())->claimIsOnKsosVolume($deployment), 'the claim did not bind to its volume');

        $body = $this->putVolumes($deployment, [$this->volume(['storage_class' => 'kso-static', 'nfs_path' => '/exports/other'])]);

        $this->assertStringContainsString('is using its disk', $body['error'] ?? '');
    }

    // <editor-fold desc="Fixtures">

    private function deploymentWithAClaim(): Deployment {
        $deployment = $this->deploymentInANamespace();
        Fixtures::deploymentVolume(array_merge(['deployment_id' => $deployment->id], $this->volume()));
        (new PersistentVolumeClaimStep())->startDeployCommand($deployment);

        return $deployment;
    }

    private function deploymentInANamespace(): Deployment {
        $deployment = $this->deploymentInTheTestNamespace();
        (new NamespaceStep())->startDeployCommand($deployment);

        return $deployment;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function volume(array $overrides = []): array {
        return array_merge([
            'type' => 'nfs',
            'mount_path' => '/data',
            'sub_path' => '',
            'capacity' => 20,
            'volume_mode' => 'Filesystem',
            'reclaim_policy' => 'Retain',
            'nfs_server' => '10.0.0.2',
            'nfs_path' => '/exports',
            'storage_class' => '',
            'csi_driver' => '',
            'csi_volume_handle' => '',
            'csi_volume_attributes' => '',
            'mount_options' => '',
        ], $overrides);
    }

    /**
     * @param array<array<string, mixed>> $volumes
     * @return array<string, mixed>
     */
    private function putVolumes(Deployment $deployment, array $volumes): array {
        $response = $this->withBodyFormat('json')->signedIn()
            ->put("deployments/{$deployment->id}/volumes", ['values' => $volumes]);

        return json_decode((string) $response->response()->getBody(), true) ?? [];
    }

    // </editor-fold>

}
