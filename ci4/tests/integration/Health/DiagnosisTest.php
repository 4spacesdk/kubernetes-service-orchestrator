<?php namespace App\Tests\Integration\Health;

use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\Health\Diagnosis\Diagnoser;
use App\Libraries\Health\Diagnosis\EvidenceGatherer;
use App\Libraries\Health\Diagnosis\Finding;

/**
 * The diagnosis against pods that really are in trouble - what `EvidenceGatherer` reads off a
 * cluster, and whether the rules find the cause in it. `DiagnoserTest` has every rule on arrays
 * shaped like the cluster's; this is the check that the cluster's are shaped like that.
 *
 * **The four broken pods start together and are waited for together**, a couple of seconds in all,
 * because the suite has a time budget. They run the image the
 * throwaway cluster already has, so nothing is pulled - except the one that is meant not to be.
 */
class DiagnosisTest extends ClusterTestCase {

    /** An image the throwaway cluster ships for its own storage provisioner - see `KubernetesApiTest`. */
    private const string RunnableImage = 'rancher/local-path-provisioner:v0.0.30';

    public function testTheRulesFindWhatIsWrongWithRealPods(): void {
        $pull = $this->deploymentNamed('pull', ['pull_secret' => 'kso-test-missing']);
        $crash = $this->deploymentNamed('crash');
        $oom = $this->deploymentNamed('oom');
        $notReady = $this->deploymentNamed('not-ready');
        (new NamespaceStep())->startDeployCommand($pull);
        // A pod is refused until the namespace's `default` service account is there, which the
        // controller adds a moment after the namespace.
        $this->eventually(function () {
            try {
                return $this->cluster()->getServiceAccountByName('default', $this->testNamespace)->exists();
            } catch (\Throwable) {
                return false;
            }
        }, 'The namespace never got its default service account.');

        $this->pod('pull', ['image' => 'example.invalid/nothing:1']);
        // Crashes once and stays up the second time - a marker in an emptyDir outlives the
        // container. A container that crashes again straight away has its predecessor's log
        // removed under the test, and the kubelet answers with a sentence instead.
        $this->pod('crash', [
            'command' => ['/bin/sh', '-c', 'if [ -f /scratch/ran ]; then sleep 300; fi; touch /scratch/ran; echo kso-crashed-here; exit 1'],
            'volumeMounts' => [['name' => 'scratch', 'mountPath' => '/scratch']],
        ], [['name' => 'scratch', 'emptyDir' => new \stdClass()]]);
        $this->pod('oom', [
            // Command substitution holds all of it in the shell - far past the limit.
            'command' => ['/bin/sh', '-c', 'x=$(head -c 200000000 /dev/zero | tr "\\0" x); sleep 300'],
            'resources' => ['limits' => ['memory' => '16Mi']],
        ]);
        $this->pod('not-ready', [
            'command' => ['/bin/sh', '-c', 'sleep 300'],
            'readinessProbe' => ['exec' => ['command' => ['/bin/false']], 'periodSeconds' => 1],
        ]);

        // Crashed once, not yet in CrashLoopBackOff: the kubelet's back-off takes half a minute to
        // get there, and the rule itself is `DiagnoserTest`'s. What only a cluster can show is that
        // the crashed container's log is read.
        $this->eventuallyWithin(30, fn() => $this->waitingFor('pull') !== null
            && isset($this->containerStatus('crash')['state']['running'], $this->containerStatus('crash')['lastState']['terminated'])
            && $this->lastTerminatedFor('oom') === 'OOMKilled'
            && $this->hasEvent('not-ready', 'Unhealthy'),
            'The pods never got into the trouble they were made for.');

        $finding = $this->findingOf($pull, 'image_pull');
        $this->assertSame(\DiagnosisVerdicts::Certain, $finding->verdict);
        $this->assertSame('The pull secret kso-test-missing is not in the namespace - it has to be created there', $finding->cause);
        $this->assertStringContainsString('example.invalid/nothing:1', implode("\n", $finding->evidence));

        $evidence = (new EvidenceGatherer($this->cluster()))->gather($crash);
        $this->assertContains('kso-crashed-here', $evidence->previousLogs['crash'] ?? [], 'the crashed container\'s log is the evidence');

        $finding = $this->findingOf($oom, 'oom_killed');
        $this->assertSame(\DiagnosisVerdicts::Certain, $finding->verdict);
        $this->assertSame('The memory limit is too low: 16 MiB', $finding->cause);

        $finding = $this->findingOf($notReady, 'not_ready');
        $this->assertSame(\DiagnosisVerdicts::Certain, $finding->verdict);
        $this->assertSame('The readiness probe fails', $finding->cause);
        $this->assertStringContainsString('Readiness probe failed', $finding->evidence[0]);
    }

    /**
     * A manifest the api server refuses is kept on the deployment, the diagnosis reads it back, and
     * the next deploy of that step that goes through clears it.
     */
    public function testARefusedDeployIsKeptUntilTheStepDeploysAgain(): void {
        $deployment = $this->deploymentNamed('refused', [], ['cpu_request' => 1000, 'cpu_limit' => 500]);
        (new NamespaceStep())->startDeployCommand($deployment);

        $error = (new DeploymentStep())->tryExecuteDeployCommand($deployment);

        $this->assertNotNull($error, 'requests above limits should be refused');
        $stored = $this->reload($deployment);
        $this->assertSame('Deployment', $stored->last_deploy_error_step);
        $this->assertStringContainsString('"code":422', str_replace(' ', '', (string) $stored->last_deploy_error));

        $causes = array_map(fn(Finding $f) => $f->cause, array_filter(
            $this->diagnose($stored),
            fn(Finding $f) => $f->rule === 'rejected_by_api_server',
        ));
        $this->assertContains('The cluster refused the manifest for Deployment', $causes);
        $this->assertContains('The cpu request is above its limit, which the cluster refuses', $causes);

        $stored->cpu_request = 100;
        $stored->save();
        $this->assertNull((new DeploymentStep())->tryExecuteDeployCommand($stored));

        $this->assertNull($this->reload($deployment)->last_deploy_error);
    }

    // <editor-fold desc="Helpers">

    /**
     * @param array<string, mixed> $imageOverrides
     * @param array<string, mixed> $overrides
     */
    private function deploymentNamed(string $name, array $imageOverrides = [], array $overrides = []): Deployment {
        $deployment = $this->deploymentInTheTestNamespace(array_merge(['name' => $name, 'status' => \DeploymentStatusTypes::Synced], $overrides));
        $image = Fixtures::containerImage(array_merge(['url' => 'nginx'], $imageOverrides));
        $specification = $deployment->findDeploymentSpecification();
        $specification->container_image_id = $image->id;
        $specification->enable_internal_access = false;
        $specification->save();

        return $this->reload($deployment);
    }

    /**
     * A pod carrying the labels kso puts on its own, so it reads as the deployment's.
     *
     * @param array<string, mixed> $container
     * @param list<array<string, mixed>> $volumes
     */
    private function pod(string $app, array $container, array $volumes = []): void {
        $this->cluster()->call('POST', "/api/v1/namespaces/{$this->testNamespace}/pods", json_encode([
            'apiVersion' => 'v1',
            'kind' => 'Pod',
            'metadata' => ['name' => $app, 'namespace' => $this->testNamespace, 'labels' => ['app' => $app, 'role' => 'app']],
            'spec' => [
                'terminationGracePeriodSeconds' => 0,
                'volumes' => $volumes,
                'containers' => [array_merge(['name' => 'app', 'image' => self::RunnableImage, 'imagePullPolicy' => 'IfNotPresent'], $container)],
            ],
        ]));
    }

    private function containerStatus(string $pod): array {
        return $this->cluster()->getPodByName($pod, $this->testNamespace)->getAttribute('status.containerStatuses', [])[0] ?? [];
    }

    private function waitingFor(string $pod): ?string {
        return $this->containerStatus($pod)['state']['waiting']['reason'] ?? null;
    }

    private function lastTerminatedFor(string $pod): ?string {
        $status = $this->containerStatus($pod);
        return $status['lastState']['terminated']['reason'] ?? $status['state']['terminated']['reason'] ?? null;
    }

    private function hasEvent(string $pod, string $reason): bool {
        foreach ($this->cluster()->getAllEvents($this->testNamespace) as $event) {
            if ($event->getAttribute('involvedObject.name') === $pod && $event->getAttribute('reason') === $reason) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return list<Finding>
     */
    private function diagnose(Deployment $deployment): array {
        return Diagnoser::Diagnose((new EvidenceGatherer($this->cluster()))->gather($deployment), time());
    }

    private function findingOf(Deployment $deployment, string $rule): Finding {
        $findings = array_values(array_filter($this->diagnose($deployment), fn(Finding $f) => $f->rule === $rule));
        $this->assertNotEmpty($findings, "{$deployment->name} had no {$rule} finding");
        return $findings[0];
    }

    private function reload(Deployment $deployment): Deployment {
        $fresh = new Deployment();
        $fresh->find($deployment->id);
        return $fresh;
    }

    /**
     * `eventually()` waits five seconds, the order of a resource turning up. The kubelet's back-off
     * and a probe that has to fail first take longer.
     *
     * @param callable(): bool $condition
     */
    private function eventuallyWithin(int $seconds, callable $condition, string $message): void {
        $until = time() + $seconds;
        while (time() < $until) {
            if ($condition()) {
                $this->assertTrue(true);
                return;
            }
            usleep(500000);
        }
        $this->fail($message);
    }

    // </editor-fold>

}
