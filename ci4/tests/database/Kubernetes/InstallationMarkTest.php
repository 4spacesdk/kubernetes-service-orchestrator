<?php namespace App\Tests\Database\Kubernetes;

use App\DatabaseTestCase;
use App\Entities\System;
use App\Libraries\GatewaySteps\ClusterGateways;
use App\Libraries\Kubernetes\ClusterDomains;
use App\Libraries\Kubernetes\KubeHelper;

/**
 * Which kso a resource is, by the mark kso writes on it - what keeps a kso from offering to take
 * over what another kso on the same cluster owns. The development cluster has two: a local kso,
 * and the one deployed into it.
 */
class InstallationMarkTest extends DatabaseTestCase {

    public function testTheInstallationHasAnIdThatStays(): void {
        $id = System::InstallationId();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $id);
        $this->assertSame($id, System::InstallationId());
    }

    public function testAResourceIsOursTheirsOrNobodysByItsMark(): void {
        $this->assertSame('ours', KubeHelper::OwnerOf(KubeHelper::Marked()));
        $this->assertSame('theirs', KubeHelper::OwnerOf([KubeHelper::InstallationAnnotation => 'another-kso']));
        $this->assertNull(KubeHelper::OwnerOf(['app.kubernetes.io/managed-by' => '4spaces.kso']));
    }

    public function testAnotherKsosGatewayIsTheirsAndCannotBeTakenOver(): void {
        $rows = ClusterGateways::Compare([[
            'metadata' => ['name' => 'edge', 'namespace' => 'gw', 'annotations' => [
                'app.kubernetes.io/managed-by' => '4spaces.kso',
                KubeHelper::InstallationAnnotation => 'another-kso',
            ]],
            'spec' => ['gatewayClassName' => 'eg', 'listeners' => []],
        ]], [], []);

        $this->assertSame(ClusterGateways::Theirs, $rows[0]['status']);
        $this->assertNull($rows[0]['plan']);
        $this->assertSame([], $rows[0]['annotations'], 'the mark is bookkeeping, not a setting');
    }

    /**
     * Our own mark and no row is ours, deleted in kso - left behind, and ours to take back.
     */
    public function testOurOwnGatewayWithNoRowIsAnOrphan(): void {
        $rows = ClusterGateways::Compare([[
            'metadata' => ['name' => 'edge', 'namespace' => 'gw', 'annotations' => [
                'app.kubernetes.io/managed-by' => '4spaces.kso',
                ...KubeHelper::Marked(),
            ]],
            'spec' => ['gatewayClassName' => 'eg', 'listeners' => []],
        ]], [], []);

        $this->assertSame(ClusterGateways::Orphan, $rows[0]['status']);
    }

    public function testAnotherKsosCertificateIsTheirs(): void {
        $rows = ClusterDomains::Compare([[
            'metadata' => ['name' => 'shop', 'namespace' => 'certs', 'annotations' => [KubeHelper::InstallationAnnotation => 'another-kso']],
            'spec' => ['dnsNames' => ['shop.org', '*.shop.org'], 'secretName' => 'shop', 'issuerRef' => ['name' => 'le']],
        ]], [], []);

        $this->assertSame(ClusterDomains::Theirs, $rows[0]['status']);
        $this->assertNull($rows[0]['plan']);
    }

}
