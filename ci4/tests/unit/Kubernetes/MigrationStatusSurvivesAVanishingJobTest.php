<?php namespace App\Tests\Unit\Kubernetes;

use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\MigrationJobStep;
use CodeIgniter\Test\CIUnitTestCase;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sJob;

/**
 * Asking for the status of a migration that is being cleaned up.
 *
 * `getStatus()` makes two calls: is the job there, and then give me the job. A job deleted
 * between the two - which is what happens for a second or so after every terminate - used
 * to come back as a 404 exception from a method whose whole purpose is to answer a
 * question. The cluster suite hit it about one run in three before it was fixed, which is
 * the worst frequency there is.
 *
 * The window cannot be forced from the outside, so the branch is checked here instead.
 */
class MigrationStatusSurvivesAVanishingJobTest extends CIUnitTestCase {

    public function testAJobThatDisappearsWhileBeingReadIsReportedNotFound(): void {
        $step = $this->stepWhoseJob($this->jobThatExistsButCannotBeFetched(404));

        $this->assertSame(DeploymentStepHelper::MigrationJob_NotFound, $step->getStatus(new Deployment()));
    }

    /**
     * Only 404. Anything else - no permission, an unreachable api server - is a question
     * the step cannot answer, and saying "not found" would report a healthy deployment as
     * one that never migrated.
     */
    public function testAnyOtherFailureIsStillRaised(): void {
        $step = $this->stepWhoseJob($this->jobThatExistsButCannotBeFetched(403));

        $this->expectException(KubernetesAPIException::class);
        $this->expectExceptionCode(403);

        $step->getStatus(new Deployment());
    }

    /**
     * The one thing that tells a finished migration from one still going: the api server
     * stamps `completionTime` when the last pod succeeds. Until it is there the release is
     * shown as running, and a step that read it wrongly would either hold a finished
     * release open or call a half-run migration done.
     */
    public function testAJobIsCompletedOnceTheClusterHasStampedIt(): void {
        $step = $this->stepWhoseJob($this->jobWhoseCompletionTimeIs('2026-09-17T08:00:00Z'));

        $this->assertSame(
            DeploymentStepHelper::MigrationJob_Completed,
            $step->getStatus(new Deployment())
        );
    }

    public function testAJobWithNoCompletionTimeIsStillRunning(): void {
        $step = $this->stepWhoseJob($this->jobWhoseCompletionTimeIs(null));

        $this->assertSame(
            DeploymentStepHelper::MigrationJob_Running,
            $step->getStatus(new Deployment())
        );
    }

    public function testAJobThatIsNotThereAtAllIsReportedNotFound(): void {
        $job = $this->createMock(K8sJob::class);
        $job->method('exists')->willReturn(false);

        $this->assertSame(
            DeploymentStepHelper::MigrationJob_NotFound,
            $this->stepWhoseJob($job)->getStatus(new Deployment())
        );
    }

    private function jobWhoseCompletionTimeIs(?string $completionTime): K8sJob {
        $job = $this->createMock(K8sJob::class);
        $job->method('exists')->willReturn(true);
        $job->method('get')->willReturnSelf();
        $job->method('getStatus')->with('completionTime')->willReturn($completionTime);

        return $job;
    }

    private function jobThatExistsButCannotBeFetched(int $code): K8sJob {
        $job = $this->createMock(K8sJob::class);
        $job->method('exists')->willReturn(true);
        $job->method('get')->willThrowException(new KubernetesAPIException('gone', $code));

        return $job;
    }

    private function stepWhoseJob(K8sJob $job): MigrationJobStep {
        return new class ($job) extends MigrationJobStep {
            public function __construct(private readonly K8sJob $job) {
            }

            protected function getResource(Deployment $deployment, bool $auth = false): K8sJob {
                return $this->job;
            }
        };
    }

}
