<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\PersistentVolumeClaimStep;
use App\Libraries\DeploymentSteps\PersistentVolumeStep;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * What the claim and the volume steps show. Both used to say only whether the object was there,
 * so a claim that was being deleted - and held back by the pods waiting for it - was "found", as
 * was a volume that could not be mounted.
 */
class VolumeStatusTest extends CIUnitTestCase {

    public function testABoundClaimIsFound(): void {
        $this->assertSame(DeploymentStepHelper::PersistentVolumeClaim_Found, PersistentVolumeClaimStep::StatusOf($this->claim('Bound')));
    }

    public function testAClaimWaitingForAVolumeIsPending(): void {
        $this->assertSame(DeploymentStepHelper::PersistentVolumeClaim_Pending, PersistentVolumeClaimStep::StatusOf($this->claim('Pending')));
    }

    public function testADeletedClaimIsTerminatingWhateverItsPhase(): void {
        $claim = $this->claim('Bound');
        $claim['metadata']['deletionTimestamp'] = '2026-10-20T10:26:04Z';

        $this->assertSame(DeploymentStepHelper::PersistentVolumeClaim_Terminating, PersistentVolumeClaimStep::StatusOf($claim));
    }

    /**
     * Every pod that names the claim holds it back from being deleted, a finished one too.
     */
    public function testTheClaimNamesThePodsThatHoldIt(): void {
        $claim = $this->claim('Bound', volume: 'ns-api');
        $claim['metadata']['deletionTimestamp'] = '2026-10-20T10:26:04Z';
        $claim['metadata']['finalizers'] = ['kubernetes.io/pvc-protection'];

        $described = PersistentVolumeClaimStep::Describe($claim, [
            $this->pod('api-1', 'Pending', 'api'),
            $this->pod('api-migration', 'Succeeded', 'api'),
            $this->pod('other', 'Running', 'other'),
        ], 'ns-api');

        $this->assertSame(DeploymentStepHelper::PersistentVolumeClaim_Terminating, $described['status']);
        $this->assertSame('2026-10-20T10:26:04Z', $described['terminatingSince']);
        $this->assertSame(['kubernetes.io/pvc-protection'], $described['finalizers']);
        $this->assertSame(['api-1 (Pending)', 'api-migration (Succeeded)'], $described['usedBy']);
        $this->assertTrue($described['onKsosVolume']);
    }

    public function testAClaimOnAVolumeItsStorageClassMadeIsNotOnKsos(): void {
        $this->assertFalse(PersistentVolumeClaimStep::Describe($this->claim('Bound', volume: 'pvc-1234'), [], 'ns-api')['onKsosVolume']);
    }

    /**
     * A volume that mounts something other than kso has says so before anything else, and shows
     * both.
     */
    public function testAVolumeWhoseSourceIsNotKsosSaysSoAndShowsBoth(): void {
        $live = $this->volume('Bound', []);
        $manifest = $this->volume(null, ['server' => 'nfs', 'share' => '/ns']);

        $described = PersistentVolumeStep::Describe($live, $manifest);

        $this->assertSame(DeploymentStepHelper::PersistentVolume_SourceDiffers, $described['status']);
        $this->assertFalse($described['sourceMatchesKso']);
        $this->assertSame(['server' => 'nfs', 'share' => '/ns'], $described['inKso']['csi']['volumeAttributes']);
        $this->assertSame('ns/api', $described['claim']);
    }

    public function testAReleasedVolumeIsReleased(): void {
        $volume = $this->volume('Released', ['server' => 'nfs']);

        $this->assertSame(DeploymentStepHelper::PersistentVolume_Released, PersistentVolumeStep::StatusOf($volume, $volume));
    }

    public function testABoundVolumeWithKsosSourceIsFound(): void {
        $volume = $this->volume('Bound', ['server' => 'nfs']);

        $described = PersistentVolumeStep::Describe($volume, $volume);

        $this->assertSame(DeploymentStepHelper::PersistentVolume_Found, $described['status']);
        $this->assertArrayNotHasKey('inKso', $described);
    }

    // <editor-fold desc="Fixtures">

    /**
     * @return array<string, mixed>
     */
    private function claim(string $phase, ?string $volume = null): array {
        return [
            'metadata' => ['name' => 'api', 'namespace' => 'ns'],
            'spec' => array_filter(['storageClassName' => 'nfs-csi', 'volumeName' => $volume, 'resources' => ['requests' => ['storage' => '1Gi']]]),
            'status' => ['phase' => $phase],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pod(string $name, string $phase, string $claim): array {
        return [
            'metadata' => ['name' => $name],
            'spec' => ['volumes' => [['name' => 'data', 'persistentVolumeClaim' => ['claimName' => $claim]]]],
            'status' => ['phase' => $phase],
        ];
    }

    /**
     * @param array<string, string> $attributes
     * @return array<string, mixed>
     */
    private function volume(?string $phase, array $attributes): array {
        $csi = ['driver' => 'nfs.csi.k8s.io', 'volumeHandle' => 'ns'];
        if ($attributes) {
            $csi['volumeAttributes'] = $attributes;
        }

        return array_filter([
            'metadata' => ['name' => 'ns-api'],
            'spec' => ['csi' => $csi, 'claimRef' => ['namespace' => 'ns', 'name' => 'api'], 'persistentVolumeReclaimPolicy' => 'Retain'],
            'status' => $phase ? ['phase' => $phase] : null,
        ]);
    }

    // </editor-fold>

}
