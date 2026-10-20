<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\VolumeFingerprint;
use App\Libraries\Kubernetes\VolumeSource;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A CSI volume's attributes are text in kso - one `key=value` a line - and a map in the
 * PersistentVolume. A line that cannot be read is named when the volume is checked, rather
 * than dropped and the volume left without what its driver needs to mount it.
 */
class VolumeSourceTest extends CIUnitTestCase {

    #[DataProvider('attributes')]
    public function testWhichAttributesCanBeRead(string $text, bool $readable): void {
        $this->assertSame($readable, VolumeSource::AttributesProblem($text) === null, $text);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function attributes(): array {
        return [
            'none' => ['', true],
            'two lines' => ["server=nfs-server.storage.svc.cluster.local\nshare=/\${namespace}", true],
            'windows line ends' => ["server=a\r\nshare=/b", true],
            'blank lines and a comment' => ["\n# where the files are\nshare=/b\n\n", true],
            'a value with an equals sign' => ['mountOptions=vers=4', true],
            'an empty value' => ['subDir=', true],
            'no equals sign' => ['server', false],
            'no key' => ['=nfs-server', false],
        ];
    }

    public function testTheLineThatCannotBeReadIsNamed(): void {
        $this->assertSame(
            "Invalid csi volume attribute 'share /b': one key=value a line",
            VolumeSource::AttributesProblem("server=a\nshare /b")
        );
    }

    /**
     * The claim's part of a volume is fixed once the claim is there; the disk's source only
     * while the claim is bound to kso's disk. An edit to the attributes is one to the source.
     */
    public function testTheAttributesAndMountOptionsAreTheDisksAndNotTheClaims(): void {
        $before = [(object) ['capacity' => 1, 'storage_class' => 'nfs-csi', 'csi_volume_attributes' => '', 'mount_options' => '']];
        $after = [(object) ['capacity' => 1, 'storage_class' => 'nfs-csi', 'csi_volume_attributes' => 'share=/a', 'mount_options' => 'vers=4']];

        $this->assertNotSame(VolumeFingerprint::of($before), VolumeFingerprint::of($after));
        $this->assertSame(VolumeFingerprint::claim($before), VolumeFingerprint::claim($after));
    }

}
