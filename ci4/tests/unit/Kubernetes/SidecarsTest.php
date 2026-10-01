<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\KubeHelper;
use App\Libraries\Kubernetes\Sidecars;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Native sidecars as a pod read from the cluster has them - init containers with
 * `restartPolicy: Always` - told from the init containers that run to an end.
 */
class SidecarsTest extends CIUnitTestCase {

    public function testASidecarIsAnInitContainerThatIsRestartedAlways(): void {
        $spec = ['initContainers' => [
            ['name' => 'wait-for-db'],
            ['name' => 'centrifugo', 'restartPolicy' => 'Always'],
        ]];

        $this->assertSame(['centrifugo'], Sidecars::Names($spec));
        $this->assertSame([], Sidecars::Names(['containers' => [['name' => 'app']]]));
    }

    /**
     * Started and not ready holds the pod back; an ordinary init container is never ready and
     * is not asked.
     */
    public function testOnlyASidecarThatIsNotReadyCounts(): void {
        $pod = fn(bool $sidecarReady) => [
            'spec' => ['initContainers' => [['name' => 'wait-for-db'], ['name' => 'centrifugo', 'restartPolicy' => 'Always']]],
            'status' => ['initContainerStatuses' => [
                ['name' => 'wait-for-db', 'ready' => false],
                ['name' => 'centrifugo', 'ready' => $sidecarReady],
            ]],
        ];

        $this->assertFalse(Sidecars::AnyNotReady($pod(true)));
        $this->assertTrue(Sidecars::AnyNotReady($pod(false)));
    }

    /**
     * What the api server writes on every init container and kso never sends. Left in, a
     * preview shows a change on each of them - sidecars too - that nobody made.
     */
    public function testThePreviewLosesTheDefaultsOfInitContainersAndKeepsTheRest(): void {
        $remote = ['initContainers' => [[
            'name' => 'centrifugo',
            'image' => 'centrifugo/centrifugo:v6.9.6',
            'restartPolicy' => 'Always',
            'resources' => [],
            'terminationMessagePath' => '/dev/termination-log',
            'terminationMessagePolicy' => 'File',
        ], [
            'name' => 'migrate',
            'resources' => ['requests' => ['cpu' => '10m']],
        ]]];

        $this->assertSame(['initContainers' => [[
            'name' => 'centrifugo',
            'image' => 'centrifugo/centrifugo:v6.9.6',
            'restartPolicy' => 'Always',
        ], [
            'name' => 'migrate',
            'resources' => ['requests' => ['cpu' => '10m']],
        ]]], KubeHelper::WithoutInitContainerDefaults($remote));
    }

}
