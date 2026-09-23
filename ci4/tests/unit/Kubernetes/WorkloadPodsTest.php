<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\WorkloadPods;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * A custom resource's pods, found by who owns them rather than by labels kso did not write.
 *
 * The RabbitmqCluster case is dev-martin's `rabbitmq-server` on 2026-09-23: its pod list was empty,
 * because the operator labels its pods `app.kubernetes.io/name`, not `app`.
 */
class WorkloadPodsTest extends CIUnitTestCase {

    public function testAPodOfAStatefulSetTheResourceOwnsIsItsPod(): void {
        $owners = ['sts-uid' => $this->owned('StatefulSet', 'rabbitmq-server-server', 'cr-uid')];

        $pods = WorkloadPods::OwnedBy('cr-uid', [$this->pod('rabbitmq-server-server-0', 'StatefulSet', 'sts-uid')], $this->lookUp($owners));

        $this->assertSame(['rabbitmq-server-server-0'], $this->names($pods));
    }

    /**
     * An operator that makes a Deployment: pod → ReplicaSet → Deployment → the resource.
     */
    public function testAPodTwoOwnersDownIsItsPod(): void {
        $owners = [
            'rs-uid' => $this->owned('ReplicaSet', 'db-7f9', 'deploy-uid'),
            'deploy-uid' => $this->owned('Deployment', 'db', 'cr-uid'),
        ];

        $pods = WorkloadPods::OwnedBy('cr-uid', [$this->pod('db-7f9-abc', 'ReplicaSet', 'rs-uid')], $this->lookUp($owners));

        $this->assertSame(['db-7f9-abc'], $this->names($pods));
    }

    public function testAnotherResourcesPodsAreLeftOut(): void {
        $owners = [
            'mine' => $this->owned('StatefulSet', 'mine', 'cr-uid'),
            'theirs' => $this->owned('StatefulSet', 'theirs', 'other-cr-uid'),
        ];

        $pods = WorkloadPods::OwnedBy('cr-uid', [
            $this->pod('mine-0', 'StatefulSet', 'mine'),
            $this->pod('theirs-0', 'StatefulSet', 'theirs'),
            $this->pod('loose'),
        ], $this->lookUp($owners));

        $this->assertSame(['mine-0'], $this->names($pods));
    }

    public function testAPodOwnedByTheResourceItselfIsItsPod(): void {
        $pods = WorkloadPods::OwnedBy('cr-uid', [$this->pod('direct', 'MyThing', 'cr-uid')], $this->lookUp([]));

        $this->assertSame(['direct'], $this->names($pods));
    }

    /**
     * An owner that cannot be read ends the search there, rather than the whole list failing.
     */
    public function testAnOwnerThatCannotBeReadIsNotFollowed(): void {
        $pods = WorkloadPods::OwnedBy('cr-uid', [$this->pod('orphan', 'StatefulSet', 'gone')], $this->lookUp([]));

        $this->assertSame([], $pods);
    }

    public function testEachOwnerIsReadOnce(): void {
        $reads = 0;
        $owners = ['sts-uid' => $this->owned('StatefulSet', 'sts', 'cr-uid')];
        $lookUp = function (array $reference) use (&$reads, $owners) {
            $reads++;
            return $owners[$reference['uid']] ?? null;
        };

        WorkloadPods::OwnedBy('cr-uid', [
            $this->pod('sts-0', 'StatefulSet', 'sts-uid'),
            $this->pod('sts-1', 'StatefulSet', 'sts-uid'),
            $this->pod('sts-2', 'StatefulSet', 'sts-uid'),
        ], $lookUp);

        $this->assertSame(1, $reads);
    }

    // <editor-fold desc="Helpers">

    private function pod(string $name, ?string $ownerKind = null, ?string $ownerUid = null): array {
        return [
            'metadata' => [
                'name' => $name,
                'ownerReferences' => $ownerKind ? [['apiVersion' => 'apps/v1', 'kind' => $ownerKind, 'name' => 'owner', 'uid' => $ownerUid]] : [],
            ],
        ];
    }

    private function owned(string $kind, string $name, string $ownerUid): array {
        return [
            'kind' => $kind,
            'metadata' => [
                'name' => $name,
                'ownerReferences' => [['apiVersion' => 'rabbitmq.com/v1beta1', 'kind' => 'Owner', 'name' => 'owner', 'uid' => $ownerUid]],
            ],
        ];
    }

    /**
     * @param array<string, array> $owners uid => the object
     */
    private function lookUp(array $owners): \Closure {
        return fn(array $reference) => $owners[$reference['uid']] ?? null;
    }

    /**
     * @param list<array> $pods
     * @return list<string>
     */
    private function names(array $pods): array {
        return array_map(fn(array $pod) => $pod['metadata']['name'], $pods);
    }

    // </editor-fold>

}
