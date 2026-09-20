<?php namespace App\Tests\Integration\Api;

use App\ClusterControllerTestCase;
use App\ClusterOutages;

/**
 * The endpoints behind the pod list, the shell and the log viewer.
 *
 * These are the only place in kso that reads a customer's running containers rather than
 * writing manifests to them, and all four are a `try`/`catch` around a cluster call whose
 * job is to turn whatever came back into something a page can render.
 *
 * What a workspace's own images would need is a registry the throwaway cluster cannot
 * reach, so a pod that is *not* running is arranged with an image that does not exist, and
 * a pod that is carries an image k3s already has on disk. The first is not an edge case: it
 * is what the page shows while a workspace is coming up.
 */
class KubernetesApiTest extends ClusterControllerTestCase {

    use ClusterOutages;

    /**
     * An image the throwaway cluster already has, because k3s ships it for its own storage
     * provisioner. Nothing here pulls anything - the cluster may have no route out at all -
     * and it carries a shell, which is what makes a pod that runs, logs and can be exec'd
     * into cost about a second.
     */
    private const RUNNABLE_IMAGE = 'rancher/local-path-provisioner:v0.0.30';

    // <editor-fold desc="Listing pods">

    public function testAnEmptyNamespaceListsNoPods(): void {
        $this->namespaceExists();

        $body = $this->decode($this->signedIn()->get("kubernetes/namespaces/{$this->testNamespace}/pods"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame([], $body['resources']);
    }

    public function testAPodIsListedWithItsContainerAndStatus(): void {
        $this->namespaceExists();
        $this->pod('api', ['app' => 'api', 'role' => 'app']);

        $items = $this->decode($this->signedIn()->get("kubernetes/namespaces/{$this->testNamespace}/pods"))['resources'];

        $this->assertCount(1, $items);
        $this->assertSame('api', $items[0]['pod']);
        $this->assertSame('app', $items[0]['container'], 'a row per container, not per pod');
        $this->assertSame($this->testNamespace, $items[0]['namespace']);
        $this->assertSame('Pending', $items[0]['status'], 'nothing pulls an image here');
        $this->assertSame(date('Y-m-d'), substr($items[0]['created'], 0, 10), 'scheduled just now');
    }

    /**
     * The list is filtered by `app` and `role` together, and the selector is built by hand
     * with `http_build_query` and then url-decoded again. That round trip is the part worth
     * checking: get it wrong and the workspace page shows every pod in the namespace, or
     * none.
     */
    public function testTheListIsFilteredByAppAndRole(): void {
        $this->namespaceExists();
        $this->pod('api', ['app' => 'api', 'role' => 'app']);
        $this->pod('worker', ['app' => 'api', 'role' => 'worker']);

        $items = $this->decode($this->signedIn()->get(
            "kubernetes/namespaces/{$this->testNamespace}/pods?app=api&role=app"
        ))['resources'];

        $this->assertSame(['api'], array_column($items, 'pod'));
    }

    /**
     * Both halves are needed, or the filter is dropped entirely and everything is listed.
     */
    public function testHalfAFilterIsNoFilter(): void {
        $this->namespaceExists();
        $this->pod('api', ['app' => 'api', 'role' => 'app']);
        $this->pod('worker', ['app' => 'api', 'role' => 'worker']);

        $items = $this->decode($this->signedIn()->get(
            "kubernetes/namespaces/{$this->testNamespace}/pods?app=api"
        ))['resources'];

        $this->assertCount(2, $items);
    }

    /**
     * A pod gets its startTime when it is placed on a node, and until then it has none. That
     * is every pod for the first moment after it is created, and one that cannot be placed
     * stays that way. Without a fallback the whole list failed, and outside the tests showed
     * 1970-01-01. A node selector nothing matches keeps the pod unplaced for as long as the
     * test needs.
     */
    public function testAPodNotYetOnANodeIsListedWithItsCreationTime(): void {
        $this->namespaceExists();
        $this->cluster()->call(
            'POST',
            "/api/v1/namespaces/{$this->testNamespace}/pods",
            json_encode([
                'apiVersion' => 'v1',
                'kind' => 'Pod',
                'metadata' => ['name' => 'unplaced', 'namespace' => $this->testNamespace],
                'spec' => [
                    'nodeSelector' => ['kso-test/no-such-node' => 'true'],
                    'containers' => [['name' => 'app', 'image' => 'example.invalid/nothing:1']],
                ],
            ])
        );

        $body = $this->decode($this->signedIn()->get("kubernetes/namespaces/{$this->testNamespace}/pods"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame(date('Y-m-d'), substr($body['resources'][0]['created'], 0, 10));
    }

    /**
     * A namespace that is not there is an ordinary answer, not an error: the workspace page
     * asks for pods before the namespace step has run.
     */
    public function testANamespaceThatDoesNotExistListsNothing(): void {
        $body = $this->decode($this->signedIn()->get(
            "kubernetes/namespaces/{$this->testNamespace}-never-made/pods"
        ));

        $this->assertSame('OK', $body['status']);
        $this->assertSame([], $body['resources']);
    }

    /**
     * The selector is pasted together from two query parameters and sent on unexamined, so
     * whatever the caller puts in `app` ends up in it. The api server refuses a selector it
     * cannot parse, and that has to arrive as an error rather than as a 500.
     */
    public function testAFilterTheClusterCannotParseIsReportedAsAnError(): void {
        $this->namespaceExists();

        $body = $this->decode($this->signedIn()->get(
            "kubernetes/namespaces/{$this->testNamespace}/pods?app=" . urlencode('(((') . '&role=app'
        ));

        $this->assertNotSame('OK', $body['status']);
    }

    // </editor-fold>

    // <editor-fold desc="Shell and logs">

    public function testExecWithoutACommandIsRefused(): void {
        $this->namespaceExists();
        $this->pod('api', ['app' => 'api', 'role' => 'app']);

        $body = $this->decode($this->signedIn()->put(
            "kubernetes/namespaces/{$this->testNamespace}/pods/api/containers/app/exec"
        ));

        $this->assertNotSame('OK', $body['status']);
    }

    /**
     * The shell on a pod that is not running. The api server answers `500 container not
     * found` over the websocket, and php-k8s does that work on a ReactPHP loop - so the
     * failure used to arrive as a rejected promise nobody handled: react/promise wrote a
     * line with `error_log()`, the loop ended, and the endpoint answered OK with no lines.
     * A user clicking the shell on a workspace that was still starting got an empty box,
     * and the reason was in the server's log.
     *
     * The message is matched on the status line, not the body. pawl builds it from what it
     * has read when the headers end, so `container not found` is only in it when the body
     * came in the same packet - which it does locally and did not in Cloud Build.
     */
    public function testExecAgainstAPodThatIsNotRunningIsReportedWithTheReason(): void {
        $this->namespaceExists();
        $this->pod('api', ['app' => 'api', 'role' => 'app']);

        $body = $this->decode($this->signedIn()->put(
            "kubernetes/namespaces/{$this->testNamespace}/pods/api/containers/app/exec?command=" . urlencode('echo hello')
        ));

        $this->assertSame('ERROR', $body['status']);
        $this->assertStringContainsString('500 Internal Server Error', $body['error'] ?? '');
    }

    public function testLogsForAContainerThatHasNotStartedComeBackAsAMessage(): void {
        $this->namespaceExists();
        $this->pod('api', ['app' => 'api', 'role' => 'app']);

        $body = $this->decode($this->signedIn()->get(
            "kubernetes/namespaces/{$this->testNamespace}/pods/api/containers/app/logs"
        ));

        $this->assertNotSame('OK', $body['status']);
    }

    /**
     * The shell, doing what the page opens it for. The frames do not line up with lines,
     * and how they are cut differs from one machine to the next - see
     * `ExecOutputLinesTest` for that, without a cluster.
     */
    public function testExecRunsTheCommandAndReturnsWhatItPrinted(): void {
        $this->namespaceExists();
        $this->runningPod('shell');

        $body = $this->decode($this->signedIn()->put(
            "kubernetes/namespaces/{$this->testNamespace}/pods/shell/containers/app/exec?command="
            . urlencode('echo first; echo second')
        ));

        $this->assertSame('OK', $body['status']);
        $this->assertSame(['first', 'second'], $body['resource']['lines']);
    }

    public function testExecOnAPodThatIsNotThereIsReportedAsAnError(): void {
        $this->namespaceExists();

        $body = $this->decode($this->signedIn()->put(
            "kubernetes/namespaces/{$this->testNamespace}/pods/never-made/containers/app/exec?command="
            . urlencode('echo hello')
        ));

        $this->assertNotSame('OK', $body['status']);
    }

    /**
     * A container that has run has a log, and the endpoint hands back one row per line with
     * the timestamp split off the front - which is how the log viewer renders a date column
     * without parsing anything itself.
     */
    public function testLogsComeBackAsOneDatedRowPerLine(): void {
        $this->namespaceExists();
        $this->finishedPod('logger');

        $rows = $this->decode($this->signedIn()->get(
            "kubernetes/namespaces/{$this->testNamespace}/pods/logger/containers/app/logs"
        ))['resources'];

        $this->assertCount(1, $rows);
        $this->assertStringStartsWith(date('Y-m-d') . 'T', $rows[0]['date']);
        // The whole marker, against a real timestamp from a real cluster. The split used to
        // be by character position and ate the start of the line whenever the timestamp was
        // shorter than thirty characters - see `KubeHelper::LogLines()`.
        $this->assertSame('kso-log-marker', $rows[0]['line']);
    }

    /**
     * Watching follows the log until the stream ends, and pushes what arrives out over the
     * push service rather than answering with it. A container that has finished is what
     * makes that testable at all: the stream ends by itself, so the request returns.
     *
     * The push events go nowhere here - see `SilentZmq`.
     */
    public function testWatchingTheLogOfAFinishedContainerRunsToTheEndOfTheStream(): void {
        $this->namespaceExists();
        $this->finishedPod('watched');

        $body = $this->decode($this->signedIn()->put(
            "kubernetes/namespaces/{$this->testNamespace}/pods/watched/containers/app/logs/watch"
        ));

        $this->assertSame('OK', $body['status']);
    }

    public function testWatchingTheLogOfAPodThatIsNotThereIsReportedAsAnError(): void {
        $this->namespaceExists();

        $body = $this->decode($this->signedIn()->put(
            "kubernetes/namespaces/{$this->testNamespace}/pods/never-made/containers/app/logs/watch"
        ));

        $this->assertNotSame('OK', $body['status']);
    }

    // </editor-fold>

    // <editor-fold desc="The cluster itself">

    /**
     * The permissions page. It reads a YAML file that ships with kso and turns it into rows
     * an operator can compare against what they granted - so the file and the page have to
     * agree, and nothing but this reads that file.
     */
    public function testThePermissionsPageListsTheRulesFromTheShippedRole(): void {
        $body = $this->decode($this->signedIn()->get('kubernetes/rbac/show'));

        $rules = $body['resource']['rules'];
        $this->assertNotEmpty($rules);
        foreach ($rules as $rule) {
            $this->assertArrayHasKey('apiGroup', $rule);
            $this->assertArrayHasKey('resource', $rule);
            $this->assertIsArray($rule['verbs']);
        }

        // One row read in full, so that the three fields are pinned to the right parts of
        // the file rather than merely being present. The first rule is the core api group,
        // which is written as the empty string - the row a page most easily gets wrong.
        $this->assertSame(
            ['apiGroup' => '', 'resource' => 'bindings', 'verbs' => ['create']],
            $rules[0]
        );
    }

    public function testNodeInfoReportsTheClusterItCanReach(): void {
        $body = $this->decode($this->signedIn()->get('kubernetes/node-info'));

        $this->assertSame('success', $body['resource']['status']);
        $this->assertCount(1, $body['resource']['nodes'], 'k3s is a single node');
    }

    /**
     * This is the page an operator opens to find out *why* kso cannot see the cluster, so
     * a failure here is an ordinary answer with the reason in it - never a refused request.
     * Both failures are reported, and they are reported differently: what the api server
     * said comes with its payload attached, and anything else comes with the message alone.
     */
    public function testNodeInfoReportsACredentialTheClusterRefuses(): void {
        $this->withCredentialsTheClusterRejects(function () {
            $body = $this->decode($this->signedIn()->get('kubernetes/node-info'));

            $this->assertSame('OK', $body['status'], 'the request itself succeeded');
            $this->assertSame('error', $body['resource']['status']);
            $this->assertSame([], $body['resource']['nodes']);
            $this->assertArrayHasKey('details', $body['resource'], "what the api server sent back");
        });
    }

    public function testNodeInfoReportsThatNoClusterIsConfiguredAtAll(): void {
        $this->withNoClusterConfigured(function () {
            $body = $this->decode($this->signedIn()->get('kubernetes/node-info'));

            $this->assertSame('OK', $body['status']);
            $this->assertSame('error', $body['resource']['status']);
            $this->assertSame('missing KUBERNETES_AUTH', $body['resource']['message']);
            $this->assertSame([], $body['resource']['nodes']);
            $this->assertArrayNotHasKey('details', $body['resource'], 'there was no api server to answer');
        });
    }

    // </editor-fold>

    // <editor-fold desc="Authorization">

    /**
     * Not one endpoint here is public. The runtime check is the `is_public` column - see
     * `PublicSurfaceTest` - and this is the controller's own statement, which is what that
     * column is generated from. Every one of these reads a customer's running containers.
     */
    public function testEveryEndpointRequiresAToken(): void {
        $controller = new \App\Controllers\Kubernetes();

        foreach (['getPods', 'exec', 'getLogs', 'watchLogs', 'rbacShow', 'nodeInfo'] as $method) {
            $this->assertTrue($controller->requireAuth($method), $method);
        }
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    private function namespaceExists(): void {
        $this->cluster()->namespace()->setName($this->testNamespace)->createOrUpdate();
    }

    /**
     * A pod that will never start: nothing pulls `example.invalid/nothing`, which is what
     * keeps this test fast. The object is what the endpoints read.
     *
     * @param array<string, string> $labels
     */
    private function pod(string $name, array $labels): void {
        $this->cluster()->call(
            'POST',
            "/api/v1/namespaces/{$this->testNamespace}/pods",
            json_encode([
                'apiVersion' => 'v1',
                'kind' => 'Pod',
                'metadata' => ['name' => $name, 'namespace' => $this->testNamespace, 'labels' => $labels],
                'spec' => [
                    'containers' => [['name' => 'app', 'image' => 'example.invalid/nothing:1']],
                ],
            ])
        );
    }

    /**
     * A pod that is up and stays up, for the endpoints that talk to a live container.
     */
    private function runningPod(string $name): void {
        $this->podFromTheCachedImage($name, 'sleep 300', 'Always');
        $this->eventually(
            fn () => $this->phaseOf($name) === 'Running',
            "{$name} never started."
        );
    }

    /**
     * A pod that prints one line and stops.
     *
     * The stopping is the point: a log watch follows the stream until it ends, and the
     * stream of a container that is still running never does. Waiting for `Succeeded` also
     * means the log is complete before anything reads it, so neither test races the pod.
     */
    private function finishedPod(string $name): void {
        $this->podFromTheCachedImage($name, 'echo kso-log-marker', 'Never');
        $this->eventually(
            fn () => $this->phaseOf($name) === 'Succeeded',
            "{$name} never finished."
        );
    }

    private function podFromTheCachedImage(string $name, string $command, string $restartPolicy): void {
        $this->cluster()->call(
            'POST',
            "/api/v1/namespaces/{$this->testNamespace}/pods",
            json_encode([
                'apiVersion' => 'v1',
                'kind' => 'Pod',
                'metadata' => ['name' => $name, 'namespace' => $this->testNamespace],
                'spec' => [
                    'restartPolicy' => $restartPolicy,
                    'containers' => [[
                        'name' => 'app',
                        'image' => self::RUNNABLE_IMAGE,
                        'imagePullPolicy' => 'IfNotPresent',
                        'command' => ['/bin/sh', '-c', $command],
                    ]],
                ],
            ])
        );
    }

    private function phaseOf(string $name): string {
        return (string) $this->cluster()
            ->getPodByName($name, $this->testNamespace)
            ->getStatus('phase');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
