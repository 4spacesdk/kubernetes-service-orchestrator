<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterOutages;
use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\CustomResourceStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\NamespaceStep;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;

/**
 * The step that applies whatever manifest an operator pasted in.
 *
 * The specification holds a block of free text. The step runs `${...}` substitution over
 * it, parses it as YAML, and sends the result - so the kind, the group, the namespace and
 * the whole shape of the request are decided by a string in the database. None of that can
 * be checked without an api server: there is no manifest to compare against, because the
 * manifest *is* the input.
 *
 * Two paths matter and they differ. A core kind goes to `/api/v1`; anything else goes to
 * `/apis/<group>/<version>`, with the plural guessed by pluralising the kind. Both are
 * exercised here, the second against a CRD the test cluster already has installed.
 *
 * A manifest that names no namespace is applied into the workspace's own. One that names
 * a namespace is applied there, wherever that is.
 */
class CustomResourceStepTest extends ClusterTestCase {

    use ClusterOutages;

    // <editor-fold desc="Applying">

    public function testACoreKindIsAppliedThroughTheCoreApiPath(): void {
        $deployment = $this->deploymentWithCustomResource($this->aConfigMap());
        $step = new CustomResourceStep();

        $this->assertSame(DeploymentStepHelper::CustomResource_NotFound, $step->getStatus($deployment));

        $step->startDeployCommand($deployment);

        $this->assertSame(DeploymentStepHelper::CustomResource_Found, $step->getStatus($deployment));
        $this->assertSame(
            ['greeting' => 'hello'],
            $this->get("/api/v1/namespaces/{$this->testNamespace}/configmaps/settings")['data']
        );
    }

    /**
     * The kind this step is named after. The group and version come out of the pasted text,
     * and the plural - `virtualservices` - is guessed by lowercasing and pluralising the
     * kind. That guess is the part that can be wrong, and only the api server can say.
     */
    public function testACustomKindIsAppliedThroughItsOwnGroup(): void {
        $deployment = $this->deploymentWithCustomResource(<<<YAML
            apiVersion: networking.istio.io/v1
            kind: VirtualService
            metadata:
              name: pasted-by-hand
              namespace: \${workspace.name}
            spec:
              hosts:
                - example.test
              http:
                - route:
                    - destination:
                        host: api.svc.cluster.local
            YAML);

        (new CustomResourceStep())->startDeployCommand($deployment);

        $resource = $this->get(
            "/apis/networking.istio.io/v1/namespaces/{$this->testNamespace}/virtualservices/pasted-by-hand"
        );
        $this->assertSame(['example.test'], $resource['spec']['hosts']);
    }

    /**
     * The same `${...}` substitution the rest of the system uses, over a whole manifest.
     * It is what lets one specification serve every workspace - and `${workspace.name}`
     * resolves to the namespace, not the readable name.
     */
    public function testPlaceholdersAreFilledInBeforeTheYamlIsParsed(): void {
        $deployment = $this->deploymentWithCustomResource(<<<YAML
            apiVersion: v1
            kind: ConfigMap
            metadata:
              name: \${deployment.name}-settings
              namespace: \${workspace.name}
            data:
              subdomain: \${workspace.subdomain}
              workspace: "\${workspace.id}"
            YAML);

        (new CustomResourceStep())->startDeployCommand($deployment);

        $configMap = $this->get(
            "/api/v1/namespaces/{$this->testNamespace}/configmaps/{$deployment->name}-settings"
        );
        $this->assertSame('tenant', $configMap['data']['subdomain']);
        $this->assertSame((string) $deployment->workspace_id, $configMap['data']['workspace']);
    }

    /**
     * A manifest that does not say where it goes goes to the workspace it belongs to.
     *
     * It used to go to `default`: the step set no namespace and php-k8s fell back to its
     * own, so every workspace's resource landed in one namespace shared by the cluster,
     * under the same name. The second workspace deployed overwrote the first, terminating
     * either removed it for both, and `getStatus()` read that one resource for all of
     * them.
     */
    public function testAManifestWithoutANamespaceGoesToTheWorkspace(): void {
        $deployment = $this->deploymentWithCustomResource(<<<YAML
            apiVersion: v1
            kind: ConfigMap
            metadata:
              name: kso-test-stray
            data:
              greeting: hello
            YAML);

        (new CustomResourceStep())->startDeployCommand($deployment);

        $this->assertSame(
            ['greeting' => 'hello'],
            $this->get("/api/v1/namespaces/{$this->testNamespace}/configmaps/kso-test-stray")['data']
        );
        $this->assertNotContains(
            'kso-test-stray',
            $this->configMapNamesIn('default'),
            'and nothing was left in the namespace the cluster shares'
        );
    }

    /**
     * Today's behaviour, and the hole still open: a manifest that names a namespace is
     * applied there, including one the workspace does not own. The text comes from an
     * operator rather than from a customer, so it is a smaller hole than the default was -
     * but it is still a workspace's deployment writing outside its own namespace, and
     * nothing warns.
     */
    public function testAManifestMayStillNameANamespaceOfItsOwn(): void {
        $elsewhere = $this->anotherNamespace('elsewhere');
        $deployment = $this->deploymentWithCustomResource(<<<YAML
            apiVersion: v1
            kind: ConfigMap
            metadata:
              name: placed-by-hand
              namespace: $elsewhere
            data:
              greeting: hello
            YAML);

        (new CustomResourceStep())->startDeployCommand($deployment);

        $this->assertContains('placed-by-hand', $this->configMapNamesIn($elsewhere));
        $this->assertNotContains('placed-by-hand', $this->configMapNamesIn($this->testNamespace));
    }

    /**
     * A manifest naming a kind the cluster has never heard of is refused by the api server,
     * not by kso. Worth pinning for what an operator actually sees: the api server answers
     * an unknown group with a bare `404 page not found` rather than a Status object, so
     * there is no reason in it - and no code either, which is why this asserts on the type
     * rather than on 404.
     */
    public function testAKindTheClusterDoesNotKnowIsRefusedByTheApiServer(): void {
        $deployment = $this->deploymentWithCustomResource(<<<YAML
            apiVersion: acme.example/v1
            kind: Sprocket
            metadata:
              name: nothing-defines-this
              namespace: \${workspace.name}
            spec:
              teeth: 12
            YAML);

        $this->expectException(KubernetesAPIException::class);
        $this->expectExceptionMessage('404 page not found');

        (new CustomResourceStep())->startDeployCommand($deployment);
    }

    // </editor-fold>

    // <editor-fold desc="Status, terminate and refusals">

    public function testTerminatingRemovesIt(): void {
        $deployment = $this->deploymentWithCustomResource($this->aConfigMap());
        $step = new CustomResourceStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::CustomResource_NotFound
        );
    }

    /**
     * Deploying twice is what a version change does, and the resource is whatever the
     * operator pasted - so the update has to go through the same path the create did.
     */
    public function testDeployingTwiceUpdatesTheResource(): void {
        $deployment = $this->deploymentWithCustomResource($this->aConfigMap('first'));
        $step = new CustomResourceStep();
        $step->startDeployCommand($deployment);

        $specification = $deployment->findDeploymentSpecification();
        $specification->custom_resource = $this->aConfigMap('second');
        $specification->save();
        $step->startDeployCommand($deployment);

        $this->assertSame(
            ['greeting' => 'second'],
            $this->get("/api/v1/namespaces/{$this->testNamespace}/configmaps/settings")['data']
        );
    }

    /**
     * An empty block is the normal state of a specification that does not use this step,
     * and every deployment runs every step's validation.
     */
    public function testASpecificationWithNoManifestIsRefused(): void {
        $deployment = $this->deploymentWithCustomResource('');

        $this->assertSame('Missing custom resource', (new CustomResourceStep())->validateDeployCommand($deployment));
    }

    /**
     * Today's behaviour. The status panel is declared to return an array and hands back
     * whatever is under `status` - which for a kind that has no status subresource, such as
     * a ConfigMap, is nothing at all. The panel dies on the return type.
     */
    public function testTheStatusPanelDiesOnAKindThatHasNoStatus(): void {
        $deployment = $this->deploymentWithCustomResource($this->aConfigMap());
        $step = new CustomResourceStep();
        $step->startDeployCommand($deployment);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must be of type array, null returned');

        $step->getKubernetesStatus($deployment);
    }

    // </editor-fold>

    // <editor-fold desc="The panels a deployment's page shows">

    /**
     * The preview is the diff an operator is shown before deploying: what kso would send on
     * the left, what the cluster has on the right. With nothing applied there is no right
     * hand side, and the whole point of this step is that the left hand side is whatever was
     * pasted - so it is the one preview where a wrong answer is not obviously wrong.
     */
    public function testThePreviewShowsTheManifestAndNothingOnTheRightUntilItIsApplied(): void {
        $deployment = $this->deploymentWithCustomResource($this->aConfigMap());
        $step = new CustomResourceStep();

        $preview = json_decode($step->getPreview($deployment), true);

        $this->assertNull($preview['remote'], 'nothing is applied yet');
        $this->assertSame('settings', json_decode($preview['local'], true)['metadata']['name']);
        $this->assertSame(['greeting' => 'hello'], json_decode($preview['local'], true)['data']);
    }

    /**
     * Once it is applied the right hand side is what the api server holds, with everything
     * the api server added stripped out - uid, resourceVersion, timestamps, managed fields.
     * Those change on every write, so leaving them in would show a difference on a resource
     * nobody has touched.
     *
     * `spec` is stripped too, which for a ConfigMap leaves `data` as the only thing that can
     * differ. For a kind that keeps its content under `spec` - which is most of the kinds
     * this step is used for - the remote side shows metadata and nothing else, so the
     * preview cannot show a spec that drifted. See the report.
     */
    public function testThePreviewStripsWhatTheApiServerAddedAndTheWholeSpec(): void {
        $deployment = $this->deploymentWithCustomResource($this->aVirtualService());
        $step = new CustomResourceStep();
        $step->startDeployCommand($deployment);

        $remote = json_decode(json_decode($step->getPreview($deployment), true)['remote'], true);

        $this->assertSame('pasted-by-hand', $remote['metadata']['name']);
        $this->assertArrayNotHasKey('uid', $remote['metadata']);
        $this->assertArrayNotHasKey('resourceVersion', $remote['metadata']);
        $this->assertArrayNotHasKey('creationTimestamp', $remote['metadata']);
        $this->assertArrayNotHasKey('managedFields', $remote['metadata']);
        $this->assertArrayNotHasKey('spec', $remote, 'the hosts and routes are not shown');
    }

    /**
     * The status panel, on a kind that has one. A Service always carries `status`, which is
     * why it is the resource used here - and it is the whole reason the panel is offered:
     * for a kind with no status the method dies on its return type, which is pinned below.
     */
    public function testTheStatusPanelShowsWhatTheApiServerPutUnderStatus(): void {
        $deployment = $this->deploymentWithCustomResource(<<<YAML
            apiVersion: v1
            kind: Service
            metadata:
              name: pasted-service
              namespace: \${workspace.name}
            spec:
              selector:
                app: api
              ports:
                - port: 80
                  targetPort: 8080
            YAML);
        $step = new CustomResourceStep();
        $step->startDeployCommand($deployment);

        $this->assertSame(['loadBalancer' => []], $step->getKubernetesStatus($deployment));
    }

    /**
     * The events panel, field by field. The step reshapes the api server's Event into six
     * keys the page reads, and `lastTimestamp` is reformatted on the way - nothing else
     * in the file would notice if a key were renamed or a value read off the wrong one.
     *
     * The event is written by hand because the test cluster has the definitions and none of
     * the controllers, so nothing ever produces one on its own.
     */
    public function testTheEventsPanelReshapesWhatTheApiServerReported(): void {
        $deployment = $this->deploymentWithCustomResource($this->aConfigMap());
        $step = new CustomResourceStep();
        $step->startDeployCommand($deployment);

        $this->emitEvent('ConfigMap', 'settings', [
            'reason' => 'Updated',
            'message' => 'the key was changed',
            'type' => 'Warning',
            'count' => 3,
            'lastTimestamp' => '2026-09-17T08:30:00Z',
        ]);

        $events = $step->getKubernetesEvents($deployment);

        $this->assertCount(1, $events);
        $this->assertSame(
            [
                'count' => 3,
                'type' => 'Warning',
                'reason' => 'Updated',
                // Kubernetes timestamps are UTC and the panel shows them in the
                // application's own timezone, so the string is not the one that went in.
                'date' => date('Y-m-d H:i:s', strtotime('2026-09-17T08:30:00Z')),
                'from' => 'kso-test',
                'message' => 'the key was changed',
            ],
            $events[0]
        );
        $this->assertNotSame('2026-09-17T08:30:00Z', $events[0]['date'], 'and it is reformatted, not passed through');
    }

    /**
     * Today's behaviour: the panel shows the events of **every resource of that kind in the
     * namespace**, not the ones belonging to this deployment.
     *
     * php-k8s builds the right field selector - `involvedObject.kind` and
     * `involvedObject.name` - and then loses half of it. `MakesHttpCalls::getCallableUrl()`
     * runs the whole query through `http_build_query()` and `urldecode()`s the result, so
     * the `&` inside the selector's own value stops being an escaped character and becomes
     * a separator. The api server is asked for `fieldSelector=involvedObject.kind=ConfigMap`
     * plus a query parameter called `involvedObject.name`, which it does not know and
     * ignores. No error, from either side.
     *
     * A workspace namespace holds one resource per deployment, so an operator looking at one
     * deployment's events is shown the neighbouring deployments' as well. It is the same
     * shape as the `->where()` that was silently nothing, one layer further down.
     *
     * Reported, not fixed. The kind half does still work, which is what the Secret asserts.
     */
    public function testTheEventsPanelAlsoShowsEveryOtherResourceOfTheSameKind(): void {
        $deployment = $this->deploymentWithCustomResource($this->aConfigMap());
        $step = new CustomResourceStep();
        $step->startDeployCommand($deployment);

        $this->emitEvent('ConfigMap', 'settings', ['reason' => 'Ours', 'message' => 'about our resource']);
        $this->emitEvent('ConfigMap', 'a-neighbours-configmap', ['reason' => 'Theirs', 'message' => 'nothing to do with us']);
        $this->emitEvent('Secret', 'settings', ['reason' => 'AnotherKind', 'message' => 'same name, other kind']);

        $reasons = array_column($step->getKubernetesEvents($deployment), 'reason');
        sort($reasons);

        $this->assertSame(['Ours', 'Theirs'], $reasons, 'the name half of the selector never reaches the api server');
    }

    public function testTheEventsPanelIsEmptyWhenNothingHasHappened(): void {
        $deployment = $this->deploymentWithCustomResource($this->aConfigMap());
        $step = new CustomResourceStep();
        $step->startDeployCommand($deployment);

        $this->assertSame([], $step->getKubernetesEvents($deployment));
    }

    // </editor-fold>

    // <editor-fold desc="When the cluster will not answer">

    /**
     * A cluster that refuses the credentials is reported as `not-found` rather than thrown.
     *
     * That is deliberate - `getStatus()` is polled for every step of every deployment on a
     * page, and one unreachable cluster should not take the page down - but it is worth
     * knowing what it costs: a workspace whose resource is sitting there perfectly healthy
     * reads as missing the moment kso's own credentials are wrong, and a redeploy from that
     * screen looks like the obvious thing to do. Nothing on the page says the difference.
     */
    public function testARefusedClusterIsReportedAsNotFoundRatherThanAsAnError(): void {
        $deployment = $this->deploymentWithCustomResource($this->aConfigMap());
        $step = new CustomResourceStep();
        $step->startDeployCommand($deployment);

        $this->assertSame(DeploymentStepHelper::CustomResource_Found, $step->getStatus($deployment));

        $this->withCredentialsTheClusterRejects(function () use ($step, $deployment) {
            $this->assertSame(DeploymentStepHelper::CustomResource_NotFound, $step->getStatus($deployment));
        });
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    private function aConfigMap(string $greeting = 'hello'): string {
        return <<<YAML
            apiVersion: v1
            kind: ConfigMap
            metadata:
              name: settings
              namespace: \${workspace.name}
            data:
              greeting: $greeting
            YAML;
    }

    private function aVirtualService(): string {
        return <<<YAML
            apiVersion: networking.istio.io/v1
            kind: VirtualService
            metadata:
              name: pasted-by-hand
              namespace: \${workspace.name}
            spec:
              hosts:
                - example.test
              http:
                - route:
                    - destination:
                        host: api.svc.cluster.local
            YAML;
    }

    /**
     * One event in this test's namespace, pointing at a resource by kind and name.
     *
     * `getEvents()` finds it with a field selector on `involvedObject.kind` and
     * `involvedObject.name`, so those two are what decide whether the step sees it. `source`
     * is set because the step reads `source.component` without checking, and `lastTimestamp`
     * because it formats it as a date.
     *
     * @param array<string, mixed> $event
     */
    private function emitEvent(string $kind, string $name, array $event): void {
        $this->cluster()->call(
            'POST',
            "/api/v1/namespaces/{$this->testNamespace}/events",
            json_encode(array_merge([
                'apiVersion' => 'v1',
                'kind' => 'Event',
                'metadata' => [
                    'name' => $name . '.' . bin2hex(random_bytes(8)),
                    'namespace' => $this->testNamespace,
                ],
                'involvedObject' => [
                    'apiVersion' => 'v1',
                    'kind' => $kind,
                    'name' => $name,
                    'namespace' => $this->testNamespace,
                ],
                'type' => 'Normal',
                'count' => 1,
                'source' => ['component' => 'kso-test'],
                'lastTimestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ], $event))
        );
    }

    private function deploymentWithCustomResource(string $yaml): Deployment {
        $deployment = $this->deploymentInTheTestNamespace();
        $specification = $deployment->findDeploymentSpecification();
        $specification->custom_resource = $yaml;
        $specification->save();
        (new NamespaceStep())->startDeployCommand($deployment);

        return $deployment;
    }

    /**
     * Every namespace is given a `kube-root-ca.crt` by Kubernetes itself, so the question
     * is always whether a particular name is there - never whether the list is empty.
     *
     * @return string[]
     */
    private function configMapNamesIn(string $namespace): array {
        return array_column(
            array_column($this->get("/api/v1/namespaces/{$namespace}/configmaps")['items'], 'metadata'),
            'name'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array {
        return json_decode($this->cluster()->call('GET', $path)->getBody()->getContents(), true);
    }

    // </editor-fold>

}
