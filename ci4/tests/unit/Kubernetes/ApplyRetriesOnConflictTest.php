<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\KubeHelper;
use CodeIgniter\Test\CIUnitTestCase;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sResource;

/**
 * Applying a resource survives losing a write race, and only that.
 *
 * A 409 from the api server means the resource was written to between the read of its
 * version and our write - almost always by a controller updating `status` during a
 * rollout. The answer is to read and write again, which is what `update()` does on the
 * next attempt.
 *
 * Found by the cluster suite: `testDeployingTwiceUpdatesRatherThanFails` failed once with
 * a 409 and passed the next thirteen runs. The race is narrow, which is exactly why it
 * cannot be left to be reproduced - the branches are checked here instead, with no
 * cluster and no timing.
 */
class ApplyRetriesOnConflictTest extends CIUnitTestCase {

    public function testAConflictIsTriedAgain(): void {
        $attempts = 0;
        $resource = $this->resourceThat(function () use (&$attempts) {
            if (++$attempts === 1) {
                throw $this->conflict();
            }
        });

        KubeHelper::Apply($resource, 3);

        $this->assertSame(2, $attempts, 'the second attempt is the one that wins');
    }

    /**
     * The retry has to stop. A resource that is genuinely contended would otherwise hold
     * the deploy open for as long as the contention lasts.
     */
    public function testItGivesUpAndThrowsTheConflict(): void {
        $attempts = 0;
        $resource = $this->resourceThat(function () use (&$attempts) {
            $attempts++;

            throw $this->conflict();
        });

        try {
            KubeHelper::Apply($resource, 3);
            $this->fail('the conflict should have been rethrown');
        } catch (KubernetesAPIException $e) {
            $this->assertSame(KubeHelper::ConflictCode, $e->getCode());
        }

        $this->assertSame(3, $attempts);
    }

    /**
     * Anything that is not a conflict is the manifest's fault, or the cluster's, and would
     * fail identically however many times it is sent. Retrying it would turn one clear
     * error into the same error half a second later.
     */
    public function testAnyOtherErrorIsThrownStraightAway(): void {
        $attempts = 0;
        $resource = $this->resourceThat(function () use (&$attempts) {
            $attempts++;

            throw new KubernetesAPIException('is invalid: spec.replicas', 422);
        });

        $this->expectException(KubernetesAPIException::class);
        $this->expectExceptionCode(422);

        try {
            KubeHelper::Apply($resource, 3);
        } finally {
            $this->assertSame(1, $attempts, 'a rejected manifest is not retried');
        }
    }

    public function testAResourceThatAppliesCleanlyIsSentOnce(): void {
        $attempts = 0;
        $resource = $this->resourceThat(function () use (&$attempts) {
            $attempts++;
        });

        KubeHelper::Apply($resource);

        $this->assertSame(1, $attempts);
    }

    /**
     * The defaults are part of the behaviour: every one of the twenty call sites applies a
     * resource without passing an attempt count, so four attempts and a doubling wait is
     * what they all get.
     *
     * The wait is asserted through the clock because there is nothing else to hold it
     * against - `usleep()` leaves no trace. Four attempts sleep 100ms, then 200ms, then
     * 400ms, so the call cannot return in under 700ms. A flat 100ms each would take 300,
     * and no wait at all would return immediately; both are the mutations this is here to
     * catch. Only the lower bound is asserted - a loaded machine may sleep longer, and
     * failing for that would make the suite flaky.
     */
    public function testTheDefaultIsFourAttemptsWithAWaitThatDoubles(): void {
        $attempts = 0;
        $resource = $this->resourceThat(function () use (&$attempts) {
            $attempts++;

            throw $this->conflict();
        });

        $startedAt = microtime(true);

        try {
            KubeHelper::Apply($resource);
            $this->fail('the conflict should have been rethrown');
        } catch (KubernetesAPIException $e) {
            $this->assertSame(KubeHelper::ConflictCode, $e->getCode());
        }

        $elapsed = microtime(true) - $startedAt;

        $this->assertSame(4, $attempts, 'four attempts by default');
        $this->assertGreaterThanOrEqual(
            0.7,
            $elapsed,
            'the wait between attempts doubles: 100ms + 200ms + 400ms'
        );
    }

    private function resourceThat(callable $behaviour): K8sResource {
        $resource = $this->createMock(K8sResource::class);
        $resource->method('createOrUpdate')->willReturnCallback($behaviour);

        return $resource;
    }

    private function conflict(): KubernetesAPIException {
        return new KubernetesAPIException(
            'Operation cannot be fulfilled on deployments.apps "api": the object has been '
            . 'modified; please apply your changes to the latest version and try again',
            KubeHelper::ConflictCode
        );
    }

}
