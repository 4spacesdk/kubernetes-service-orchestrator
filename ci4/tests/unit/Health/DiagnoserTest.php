<?php namespace App\Tests\Unit\Health;

use App\Libraries\Health\Diagnosis\Diagnoser;
use App\Libraries\Health\Diagnosis\Evidence;
use App\Libraries\Health\Diagnosis\Finding;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The rules that say why a deployment is doing badly - and that say so when they cannot.
 *
 * The pods and events are shaped like what the api server answers. The tag that does not exist is
 * the development cluster's case from 2026-09-22, which health already calls Degraded.
 */
class DiagnoserTest extends CIUnitTestCase {

    private const int Now = 1_800_000_000;

    public function testAHealthyPodFindsNothing(): void {
        $this->assertSame([], $this->diagnose(new Evidence('1.1', [$this->pod()])));
    }

    // <editor-fold desc="Image pull">

    public function testATagTheRegistryDoesNotHaveIsCertain(): void {
        $finding = $this->only(new Evidence('1.1', [$this->pod(waiting: 'ImagePullBackOff', waitingMessage: 'Back-off pulling image "reg/app:1.1"')], imageTags: ['1.0']));

        $this->assertSame('image_pull', $finding->rule);
        $this->assertSame(\DiagnosisVerdicts::Certain, $finding->verdict);
        $this->assertSame('The tag 1.1 is not in the registry', $finding->cause);
        $this->assertSame('version', $finding->action['section']);
    }

    public function testAPullSecretMissingFromTheNamespaceIsCertain(): void {
        $finding = $this->only(new Evidence('1.1', [$this->pod(waiting: 'ErrImagePull')], imageTags: ['1.1'], pullSecrets: ['kso-registry-3' => false]));

        $this->assertSame(\DiagnosisVerdicts::Certain, $finding->verdict);
        $this->assertSame('The pull secret kso-registry-3 is not in the namespace', $finding->cause);
        $this->assertSame('deploy', $finding->action['type']);
    }

    /**
     * A secret named on the image is made by whoever set it up, not by kso's deploy - so there is
     * no button that would help.
     */
    public function testAPullSecretKsoDoesNotWriteHasNoDeployButton(): void {
        $finding = $this->only(new Evidence('1.1', [$this->pod(waiting: 'ErrImagePull')], imageTags: ['1.1'], pullSecrets: ['their-secret' => false]));

        $this->assertStringContainsString('has to be created there', $finding->cause);
        $this->assertNull($finding->action);
    }

    public function testAPodThatDoesNotNameTheSecretIsCertain(): void {
        $finding = $this->only(new Evidence('1.1', [$this->pod(waiting: 'ErrImagePull', pullSecrets: [])], imageTags: ['1.1'], pullSecrets: ['kso-registry-3' => true]));

        $this->assertSame('The pod does not name the pull secret kso-registry-3', $finding->cause);
    }

    public function testARefusedLoginWithTheTagThereIsCertain(): void {
        $finding = $this->only(new Evidence(
            '1.1',
            [$this->pod(waiting: 'ErrImagePull', waitingMessage: 'failed to authorize: 401 Unauthorized', pullSecrets: ['kso-registry-3'])],
            imageTags: ['1.1'],
            pullSecrets: ['kso-registry-3' => true],
        ));

        $this->assertSame(\DiagnosisVerdicts::Certain, $finding->verdict);
        $this->assertStringContainsString('refused the login', $finding->cause);
    }

    public function testNotFoundWithoutARegistryToAskIsOnlyPossible(): void {
        $finding = $this->only(new Evidence('1.1', [$this->pod(waiting: 'ErrImagePull', waitingMessage: 'reg/app:1.1: not found')]));

        $this->assertSame(\DiagnosisVerdicts::Possible, $finding->verdict);
        $this->assertSame('The tag 1.1 does not seem to exist', $finding->cause);
    }

    public function testAPullThatFailsForNoReasonItCanSeeSaysItCannotTell(): void {
        $finding = $this->only(new Evidence('1.1', [$this->pod(waiting: 'ErrImagePull', waitingMessage: 'i/o timeout')], imageTagsError: 'registry timed out'));

        $this->assertSame(\DiagnosisVerdicts::CannotTell, $finding->verdict);
        $this->assertContains('The registry could not be asked for its tags: registry timed out', $finding->evidence);
    }

    public function testTwoPodsThatCannotPullTheSameTagAreOneFinding(): void {
        $findings = $this->diagnose(new Evidence('1.1', [
            $this->pod('app-a', waiting: 'ErrImagePull'),
            $this->pod('app-b', waiting: 'ImagePullBackOff'),
        ], imageTags: ['1.0']));

        $this->assertCount(1, $findings);
        $this->assertSame([
            'The registry has 1 tags for the image, and not this one',
            'app-a: ErrImagePull',
            'app-b: ImagePullBackOff',
        ], $findings[0]->evidence);
    }

    // </editor-fold>

    // <editor-fold desc="Out of memory">

    public function testOomKilledWithALimitNamesTheLimitAndWhatItUsesNow(): void {
        $finding = $this->only(new Evidence(
            '1.1',
            [$this->pod(lastTerminated: ['reason' => 'OOMKilled', 'exitCode' => 137, 'finishedAt' => $this->ago(120)], memoryLimit: '256Mi')],
            metrics: ['pods' => [['pod' => 'app-a', 'container' => 'app', 'memory_bytes' => 250 * 1024 ** 2]]],
        ));

        $this->assertSame('oom_killed', $finding->rule);
        $this->assertSame(\DiagnosisVerdicts::Certain, $finding->verdict);
        $this->assertSame('The memory limit is too low: 256 MiB', $finding->cause);
        $this->assertContains('Using 250 MiB now of 256 MiB', $finding->evidence);
    }

    public function testOomKilledWithoutALimitIsTheNodeAndOnlyPossible(): void {
        $finding = $this->only(new Evidence('1.1', [$this->pod(lastTerminated: ['reason' => 'OOMKilled', 'finishedAt' => $this->ago(120)])]));

        $this->assertSame(\DiagnosisVerdicts::Possible, $finding->verdict);
    }

    public function testAnOomKillLongAgoIsNotACause(): void {
        $this->assertSame([], $this->diagnose(new Evidence('1.1', [$this->pod(lastTerminated: ['reason' => 'OOMKilled', 'finishedAt' => $this->ago(7200)], memoryLimit: '256Mi')])));
    }

    // </editor-fold>

    // <editor-fold desc="Crash after a version change">

    public function testANewVersionThatCrashesBesideTheOldOneIsCertain(): void {
        $finding = $this->only(new Evidence(
            '1.1',
            [
                $this->pod('app-new', waiting: 'CrashLoopBackOff', restarts: 5, lastTerminated: ['exitCode' => 1, 'reason' => 'Error', 'finishedAt' => $this->ago(30)]),
                $this->pod('app-old', image: 'reg/app:1.0'),
            ],
            versionChange: ['from' => '1.0', 'to' => '1.1', 'at' => self::Now - 600],
            previousLogs: ['app-new' => ['Starting', 'Fatal: DATABASE_URL is not set']],
        ));

        $this->assertSame('crash_after_version_change', $finding->rule);
        $this->assertSame(\DiagnosisVerdicts::Certain, $finding->verdict);
        $this->assertSame('1.1 crashes, and 1.0 still runs beside it', $finding->cause);
        $this->assertSame(['type' => 'rollback', 'label' => 'Roll back to 1.0', 'version' => '1.0'], $finding->action);
        $this->assertContains('app-new: Fatal: DATABASE_URL is not set', $finding->evidence);
        $this->assertContains('app-new: restarted 5 times - exit code 1 (Error)', $finding->evidence);
    }

    public function testACrashSoonAfterAChangeWithTheOldPodsGoneIsOnlyPossible(): void {
        $finding = $this->only(new Evidence(
            '1.1',
            [$this->pod(waiting: 'CrashLoopBackOff', restarts: 5)],
            versionChange: ['from' => '1.0', 'to' => '1.1', 'at' => self::Now - 600],
        ));

        $this->assertSame(\DiagnosisVerdicts::Possible, $finding->verdict);
        $this->assertSame('rollback', $finding->action['type']);
    }

    public function testACrashLongAfterTheLastChangeCannotBePutDownToIt(): void {
        $finding = $this->only(new Evidence(
            '1.1',
            [$this->pod(waiting: 'CrashLoopBackOff', restarts: 5)],
            versionChange: ['from' => '1.0', 'to' => '1.1', 'at' => self::Now - 3 * 86400],
        ));

        $this->assertSame(\DiagnosisVerdicts::CannotTell, $finding->verdict);
        $this->assertNull($finding->action);
    }

    public function testACrashWithNoVersionChangeOnRecordCannotTell(): void {
        $finding = $this->only(new Evidence('1.1', [$this->pod(waiting: 'CrashLoopBackOff', restarts: 5)]));

        $this->assertSame(\DiagnosisVerdicts::CannotTell, $finding->verdict);
    }

    public function testOneOrTwoRestartsAreNotACrash(): void {
        $this->assertSame([], $this->diagnose(new Evidence('1.1', [$this->pod(restarts: 2, lastTerminated: ['exitCode' => 1, 'finishedAt' => $this->ago(60)])])));
    }

    // </editor-fold>

    // <editor-fold desc="Not ready">

    public function testAFailingReadinessProbeIsCertainWithTheProbesOwnWords(): void {
        $finding = $this->only(new Evidence(
            '1.1',
            [$this->pod(ready: false)],
            events: [$this->event('app-a', 'Unhealthy', 'Readiness probe failed: HTTP probe failed with statuscode: 500', 12)],
        ));

        $this->assertSame('not_ready', $finding->rule);
        $this->assertSame('The readiness probe fails', $finding->cause);
        $this->assertSame(['Readiness probe failed: HTTP probe failed with statuscode: 500 (×12)'], $finding->evidence);
    }

    public function testAProbeAnsweredWith401AsksAboutBasicAuth(): void {
        $finding = $this->only(new Evidence(
            '1.1',
            [$this->pod(ready: false, readinessPath: '/health')],
            events: [$this->event('app-a', 'Unhealthy', 'Readiness probe failed: HTTP probe failed with statuscode: 401')],
        ));

        $this->assertSame('The readiness probe is answered with 401 on /health - is the path behind a login, such as basic auth?', $finding->cause);
    }

    public function testNotReadyWithoutAProbeEventCannotTell(): void {
        $finding = $this->only(new Evidence('1.1', [$this->pod(ready: false)]));

        $this->assertSame(\DiagnosisVerdicts::CannotTell, $finding->verdict);
        $this->assertSame('Not ready, though it has no readiness probe', $finding->cause);
    }

    public function testAnotherPodsEventsAreNotThisPodsEvidence(): void {
        $finding = $this->only(new Evidence(
            '1.1',
            [$this->pod(ready: false)],
            events: [$this->event('someone-else', 'Unhealthy', 'Readiness probe failed')],
        ));

        $this->assertSame(\DiagnosisVerdicts::CannotTell, $finding->verdict);
    }

    // </editor-fold>

    // <editor-fold desc="Refused by the api server">

    public function testAManifestTheApiServerRefusedIsCertainWithItsCauses(): void {
        $finding = $this->only(new Evidence('1.1', lastDeployError: [
            'step' => 'Deployment',
            'at' => self::Now - 60,
            'error' => json_encode([
                'kind' => 'Status',
                'code' => 422,
                'message' => 'Deployment.apps "app" is invalid',
                'details' => ['causes' => [['field' => 'spec.template.spec.containers[0].resources.requests', 'message' => 'Invalid value: "1": must be less than or equal to cpu limit']]],
            ]),
        ]));

        $this->assertSame('rejected_by_api_server', $finding->rule);
        $this->assertSame(\DiagnosisVerdicts::Certain, $finding->verdict);
        $this->assertSame('The cluster refused the manifest for Deployment', $finding->cause);
        $this->assertContains('spec.template.spec.containers[0].resources.requests: Invalid value: "1": must be less than or equal to cpu limit', $finding->evidence);
    }

    public function testForbiddenIsKsosOwnRights(): void {
        $finding = $this->only(new Evidence('1.1', lastDeployError: [
            'step' => 'Ingress', 'at' => self::Now, 'error' => json_encode(['kind' => 'Status', 'code' => 403, 'message' => 'forbidden']),
        ]));

        $this->assertStringContainsString('not allowed', $finding->cause);
    }

    public function testAnErrorThatIsNotTheApiServersIsOnlyPossible(): void {
        $finding = $this->only(new Evidence('1.1', lastDeployError: ['step' => 'Deployment', 'at' => self::Now, 'error' => 'cURL error 28: timed out']));

        $this->assertSame(\DiagnosisVerdicts::Possible, $finding->verdict);
    }

    public function testRequestsAboveLimitsAreCertainBeforeTheyAreEvenDeployed(): void {
        $finding = $this->only(new Evidence('1.1', resources: ['cpu_request' => 1000, 'cpu_limit' => 500, 'memory_request' => null, 'memory_limit' => 256]));

        $this->assertSame('The cpu request is above its limit, which the cluster refuses', $finding->cause);
        $this->assertSame('resource-management', $finding->action['section']);
    }

    // </editor-fold>

    // <editor-fold desc="Migration">

    public function testAFailedMigrationForThisVersionIsCertainWithItsLog(): void {
        $finding = $this->only(new Evidence('1.1', lastMigration: [
            'id' => 7, 'status' => \MigrationJobStatusTypes::Failed_PostCommands, 'image' => 'reg/app:1.1', 'log' => "Migrating\nSQLSTATE[42S01]: table exists\n",
        ]));

        $this->assertSame('migration_failed', $finding->rule);
        $this->assertSame('The migration for 1.1 failed: a post command failed', $finding->cause);
        $this->assertSame(['Migrating', 'SQLSTATE[42S01]: table exists'], $finding->evidence);
        $this->assertSame(7, $finding->action['migration_job_id']);
    }

    public function testAFailedMigrationForAnEarlierVersionIsNotACause(): void {
        $this->assertSame([], $this->diagnose(new Evidence('1.1', lastMigration: [
            'id' => 7, 'status' => \MigrationJobStatusTypes::Failed_PostCommands, 'image' => 'reg/app:1.0', 'log' => '',
        ])));
    }

    // </editor-fold>

    public function testTheMostCertainComesFirst(): void {
        $findings = $this->diagnose(new Evidence(
            '1.1',
            [$this->pod(waiting: 'CrashLoopBackOff', restarts: 5)],
            lastMigration: ['id' => 7, 'status' => \MigrationJobStatusTypes::Failed_LogVerification, 'image' => 'reg/app:1.1', 'log' => ''],
        ));

        $this->assertSame([\DiagnosisVerdicts::Certain, \DiagnosisVerdicts::CannotTell], array_map(fn(Finding $f) => $f->verdict, $findings));
    }

    // <editor-fold desc="Helpers">

    /**
     * @return list<Finding>
     */
    private function diagnose(Evidence $evidence): array {
        return Diagnoser::Diagnose($evidence, self::Now);
    }

    private function only(Evidence $evidence): Finding {
        $findings = $this->diagnose($evidence);
        $this->assertCount(1, $findings, 'Expected one finding, got: ' . json_encode(array_map(fn(Finding $f) => $f->toArray(), $findings)));
        return $findings[0];
    }

    private function ago(int $seconds): string {
        return gmdate('Y-m-d\TH:i:s\Z', self::Now - $seconds);
    }

    private function pod(
        string $name = 'app-a',
        string $image = 'reg/app:1.1',
        ?string $waiting = null,
        string $waitingMessage = '',
        int $restarts = 0,
        ?array $lastTerminated = null,
        bool $ready = true,
        ?string $memoryLimit = null,
        ?string $readinessPath = null,
        array $pullSecrets = ['kso-registry-3'],
    ): array {
        $spec = ['name' => 'app', 'image' => $image];
        if ($memoryLimit) {
            $spec['resources'] = ['limits' => ['memory' => $memoryLimit]];
        }
        if ($readinessPath) {
            $spec['readinessProbe'] = ['httpGet' => ['path' => $readinessPath, 'port' => 80]];
        }

        $status = ['name' => 'app', 'image' => $image, 'restartCount' => $restarts, 'ready' => $ready && $waiting === null];
        $status['state'] = $waiting
            ? ['waiting' => ['reason' => $waiting, 'message' => $waitingMessage]]
            : ['running' => ['startedAt' => $this->ago(60)]];
        if ($lastTerminated) {
            $status['lastState'] = ['terminated' => $lastTerminated];
        }

        return [
            'metadata' => ['name' => $name, 'namespace' => 'ns', 'labels' => ['app' => 'app', 'role' => 'app']],
            'spec' => [
                'containers' => [$spec],
                'imagePullSecrets' => array_map(fn(string $secret) => ['name' => $secret], $pullSecrets),
            ],
            'status' => [
                'phase' => $waiting === 'ErrImagePull' || $waiting === 'ImagePullBackOff' ? 'Pending' : 'Running',
                'conditions' => [['type' => 'Ready', 'status' => $status['ready'] ? 'True' : 'False']],
                'containerStatuses' => [$status],
            ],
        ];
    }

    private function event(string $pod, string $reason, string $message, int $count = 1): array {
        return [
            'involvedObject' => ['kind' => 'Pod', 'name' => $pod],
            'reason' => $reason,
            'message' => $message,
            'count' => $count,
            'type' => 'Warning',
            'lastTimestamp' => $this->ago(30),
        ];
    }

    // </editor-fold>

}
