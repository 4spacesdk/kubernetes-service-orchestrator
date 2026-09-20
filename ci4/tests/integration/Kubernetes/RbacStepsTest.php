<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterOutages;
use App\ClusterTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\BaseDeploymentStep;
use App\Libraries\DeploymentSteps\ClusterRoleBindingStep;
use App\Libraries\DeploymentSteps\ClusterRoleStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\RoleBindingStep;
use App\Libraries\DeploymentSteps\RoleStep;
use App\Libraries\DeploymentSteps\ServiceAccountStep;

/**
 * The five steps that decide what a customer's workspace is allowed to do.
 *
 * These matter more than most: the manifest is what stands between one tenant's pods and
 * every other tenant's. A manifest test can say the rules were written down; only the api
 * server can say they were accepted and are in force.
 *
 * Two of the five are **not namespaced**. A cluster role outlives the namespace it was
 * made for, so `ClusterTestCase` deletes them by the `<name>.<namespace>` suffix kso gives
 * them - see there.
 */
class RbacStepsTest extends ClusterTestCase {

    use ClusterOutages;

    // <editor-fold desc="Service account">

    public function testAServiceAccountIsCreatedAndFound(): void {
        $deployment = $this->deploymentInANamespace();
        $step = new ServiceAccountStep();

        $this->assertSame(DeploymentStepHelper::ServiceAccount_NotFound, $step->getStatus($deployment));

        $step->startDeployCommand($deployment);

        $this->assertSame(DeploymentStepHelper::ServiceAccount_Found, $step->getStatus($deployment));
        $this->assertSame(
            $deployment->name,
            $this->cluster()->getServiceAccountByName($deployment->name, $this->testNamespace)->getName()
        );
    }

    public function testTerminatingRemovesTheServiceAccount(): void {
        $deployment = $this->deploymentInANamespace();
        $step = new ServiceAccountStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::ServiceAccount_NotFound
        );
    }

    /**
     * A preview is what an operator reads before pressing deploy, and it is two documents:
     * what kso would send, and what is out there now. Before the first deploy there is no
     * remote half, and saying so with a null is the difference between "nothing yet" and
     * "identical".
     */
    public function testThePreviewOfAServiceAccountThatDoesNotExistYetHasNoRemoteHalf(): void {
        $deployment = $this->deploymentInANamespace();

        $preview = $this->preview(new ServiceAccountStep(), $deployment);

        $this->assertSame($deployment->name, $preview['local']['metadata']['name']);
        $this->assertNull($preview['remote']);
    }

    /**
     * Once it is out there the two halves are meant to be comparable, which is why the
     * fields the api server fills in are stripped. A uid or a resourceVersion left in
     * makes every preview show a difference, and an operator stops reading them.
     *
     * `secrets` is stripped as well, and only here, because Kubernetes used to hang a
     * generated token on every service account. It stopped in 1.24, and the test cluster
     * is 1.31: the field is never there, so that one `unset()` cannot be observed from a
     * test any more and a mutation removing it survives. The assertion stays as the
     * contract for an older cluster; it is not evidence.
     */
    public function testThePreviewOfALiveServiceAccountDropsWhatTheClusterAddedToIt(): void {
        $deployment = $this->deploymentInANamespace();
        $step = new ServiceAccountStep();
        $step->startDeployCommand($deployment);

        $preview = $this->preview($step, $deployment);

        $this->assertSame($deployment->name, $preview['remote']['metadata']['name']);
        $this->assertSame($this->testNamespace, $preview['remote']['metadata']['namespace']);
        $this->assertArrayNotHasKey('uid', $preview['remote']['metadata']);
        $this->assertArrayNotHasKey('resourceVersion', $preview['remote']['metadata']);
        $this->assertArrayNotHasKey('creationTimestamp', $preview['remote']['metadata']);
        $this->assertArrayNotHasKey('secrets', $preview['remote']);
    }

    /**
     * The namespace has to be there first - a service account applied into a namespace
     * that does not exist is refused by the api server, and the message an operator would
     * get back is a raw 404.
     */
    public function testAServiceAccountIsRefusedUntilItsNamespaceExists(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        $step = new ServiceAccountStep();

        $this->assertSame('Missing Namespace', $step->validateDeployCommand($deployment));

        (new NamespaceStep())->startDeployCommand($deployment);

        $this->assertNull($step->validateDeployCommand($deployment));
    }

    /**
     * The one step of the five that catches the cluster failing while it validates. The
     * others let a `KubernetesAPIException` out of `validateDeployCommand()`, which the
     * caller reports as a crashed step rather than a refused one.
     */
    public function testAClusterThatRefusesUsIsReportedRatherThanThrown(): void {
        $deployment = $this->deploymentInANamespace();

        $this->withCredentialsTheClusterRejects(function () use ($deployment) {
            $error = (new ServiceAccountStep())->validateDeployCommand($deployment);

            $this->assertNotNull($error);
            $this->assertStringContainsString('401', $error);
        });
    }

    // </editor-fold>

    // <editor-fold desc="Role">

    public function testARoleCarriesTheRulesItWasGiven(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::roleRule([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'api_group' => '',
            'resource' => 'configmaps',
            'verbs' => 'get,list,watch',
        ]);

        (new RoleStep())->startDeployCommand($deployment);

        $rules = $this->cluster()->getRoleByName($deployment->name, $this->testNamespace)->getAttribute('rules');
        $this->assertSame(['configmaps'], $rules[0]['resources']);
        $this->assertSame(['get', 'list', 'watch'], $rules[0]['verbs']);
    }

    /**
     * The point of trimming, seen from the cluster: a rule typed with spaces after the
     * commas used to be refused by the api server, and the whole Role failed to apply.
     */
    public function testARuleTypedWithSpacesIsAccepted(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::roleRule([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'api_group' => '',
            'resource' => 'configmaps',
            'verbs' => 'get, list, watch',
        ]);

        (new RoleStep())->startDeployCommand($deployment);

        $this->assertSame(
            ['get', 'list', 'watch'],
            $this->cluster()->getRoleByName($deployment->name, $this->testNamespace)->getAttribute('rules')[0]['verbs']
        );
    }

    /**
     * A specification with no rules gets no Role at all - the step returns before it
     * applies. That is not a detail: an empty Role would be a resource nobody asked for,
     * and the status has a value of its own for it.
     */
    public function testASpecificationWithoutRulesGetsNoRole(): void {
        $deployment = $this->deploymentInANamespace();
        $step = new RoleStep();

        $step->startDeployCommand($deployment);

        $this->assertSame(DeploymentStepHelper::Role_NotFoundNotExpected, $step->getStatus($deployment));
        $this->assertSame($step->getSuccessStatus($deployment), $step->getStatus($deployment));
        $this->assertFalse($this->cluster()->role()->whereName($deployment->name)->whereNamespace($this->testNamespace)->exists());
    }

    /**
     * The branch that cannot be reached without a cluster: a Role is out there, and the
     * specification no longer asks for one. That is what happens when the last rule is
     * removed from a live workspace, and the status has to say so rather than report
     * success - otherwise permissions that were revoked in kso stay granted in the cluster
     * and nothing anywhere says a word.
     */
    public function testARoleLeftBehindAfterItsRulesAreRemovedIsReportedNotExpected(): void {
        $deployment = $this->deploymentInANamespace();
        $rule = Fixtures::roleRule([
            'deployment_specification_id' => $deployment->deployment_specification_id,
        ]);
        $step = new RoleStep();
        $step->startDeployCommand($deployment);
        $this->assertSame(DeploymentStepHelper::Role_Found, $step->getStatus($deployment));

        $rule->delete();

        $this->assertSame(DeploymentStepHelper::Role_FoundNotExpected, $step->getStatus($deployment));
    }

    /**
     * Terminating is guarded by `exists()`, unlike the cluster role binding below. A
     * workspace whose specification never had rules is terminated all the same, and the
     * step has to sit through it without throwing.
     */
    public function testTerminatingARoleThatWasNeverCreatedIsQuiet(): void {
        $deployment = $this->deploymentInANamespace();

        (new RoleStep())->startTerminateCommand($deployment);

        $this->assertSame(
            DeploymentStepHelper::Role_NotFoundNotExpected,
            (new RoleStep())->getStatus($deployment)
        );
    }

    /**
     * The other half of the terminate: a Role that *was* created is fetched and deleted.
     * php-k8s answers `true` from `delete()` on a resource it has not fetched, without
     * sending anything, so the `synced()` in front of it is what makes this work at all.
     */
    public function testTerminatingRemovesARoleThatWasCreated(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::roleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);
        $step = new RoleStep();
        $step->startDeployCommand($deployment);
        $this->assertSame(DeploymentStepHelper::Role_Found, $step->getStatus($deployment));

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::Role_NotFound
        );
    }

    public function testThePreviewOfARoleShowsTheRulesBeforeAndAfterItIsApplied(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::roleRule([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'resource' => 'configmaps',
        ]);
        $step = new RoleStep();

        $before = $this->preview($step, $deployment);
        $this->assertSame(['configmaps'], $before['local']['rules'][0]['resources']);
        $this->assertNull($before['remote']);

        $step->startDeployCommand($deployment);

        $after = $this->preview($step, $deployment);
        $this->assertSame(['configmaps'], $after['remote']['rules'][0]['resources']);
        $this->assertArrayNotHasKey('uid', $after['remote']['metadata']);
        $this->assertArrayNotHasKey('managedFields', $after['remote']['metadata']);
    }

    // </editor-fold>

    // <editor-fold desc="Role binding">

    /**
     * The binding is the piece that actually grants anything, and it needs three things to
     * exist first. Each of the three is a separate refusal, so each is worth naming.
     */
    public function testTheBindingRefusesUntilTheRoleAndServiceAccountAreThere(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::roleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);
        $step = new RoleBindingStep();

        $this->assertSame('Missing Role', $step->validateDeployCommand($deployment));

        (new RoleStep())->startDeployCommand($deployment);
        $this->assertSame('Missing Service Account', $step->validateDeployCommand($deployment));

        (new ServiceAccountStep())->startDeployCommand($deployment);
        $this->assertNull($step->validateDeployCommand($deployment));
    }

    public function testTheBindingTiesTheServiceAccountToTheRole(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::roleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);
        (new RoleStep())->startDeployCommand($deployment);
        (new ServiceAccountStep())->startDeployCommand($deployment);

        (new RoleBindingStep())->startDeployCommand($deployment);

        $binding = $this->cluster()->getRoleBindingByName($deployment->name, $this->testNamespace);
        $this->assertSame($deployment->name, $binding->getAttribute('roleRef')['name']);
        $this->assertSame('Role', $binding->getAttribute('roleRef')['kind']);
        $subject = $binding->getAttribute('subjects')[0];
        $this->assertSame('ServiceAccount', $subject['kind']);
        $this->assertSame($deployment->name, $subject['name']);
        $this->assertSame($this->testNamespace, $subject['namespace']);
    }

    /**
     * The binding refuses before the namespace exists, and before anything else is looked
     * at. It is the first of the three refusals and the only one that does not need a
     * round trip to a second step.
     */
    public function testTheBindingRefusesBeforeItsNamespaceExists(): void {
        $deployment = $this->deploymentInTheTestNamespace();

        $this->assertSame('Missing Namespace', (new RoleBindingStep())->validateDeployCommand($deployment));
    }

    /**
     * Today's behaviour, and it stops a deploy dead. The binding demands `Role_Found`
     * literally, where the cluster role binding asks its role for `getSuccessStatus()`.
     * A specification with RBAC switched on but no role rules therefore has a Role step
     * that correctly creates nothing, and a Role Binding step that refuses for ever -
     * `deployAllSteps()` reports "Role Binding: Missing Role" on every deploy. See the
     * cluster role binding below for the shape this one should have had.
     */
    public function testTheBindingRefusesForeverWhenTheSpecificationHasNoRoleRules(): void {
        $deployment = $this->deploymentInANamespace();
        (new RoleStep())->startDeployCommand($deployment);
        (new ServiceAccountStep())->startDeployCommand($deployment);
        $step = new RoleStep();

        $this->assertSame($step->getSuccessStatus($deployment), $step->getStatus($deployment), 'the Role step is finished');
        $this->assertSame('Missing Role', (new RoleBindingStep())->validateDeployCommand($deployment));
    }

    /**
     * A binding is expected exactly when its Role is, so a specification with rules that
     * has not been deployed yet is `not-found`, and one without rules is
     * `not-found-not-expected`. The two mean different things to an operator: the first is
     * work outstanding, the second is nothing to do.
     */
    public function testTheBindingStatusSaysWhetherItWasExpected(): void {
        $withoutRules = $this->deploymentInANamespace();
        $step = new RoleBindingStep();

        $this->assertSame(DeploymentStepHelper::RoleBinding_NotFoundNotExpected, $step->getStatus($withoutRules));

        Fixtures::roleRule(['deployment_specification_id' => $withoutRules->deployment_specification_id]);

        $this->assertSame(DeploymentStepHelper::RoleBinding_NotFound, $step->getStatus($withoutRules));
    }

    /**
     * The branch no manifest test can reach: a binding is out there and the last rule has
     * been removed from the specification. The permissions are still granted in the
     * cluster, and this status is the only thing that says so.
     */
    public function testABindingLeftBehindAfterItsRulesAreRemovedIsReportedNotExpected(): void {
        $deployment = $this->deploymentInANamespace();
        $rule = Fixtures::roleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);
        (new RoleStep())->startDeployCommand($deployment);
        (new ServiceAccountStep())->startDeployCommand($deployment);
        $step = new RoleBindingStep();
        $step->startDeployCommand($deployment);
        $this->assertSame(DeploymentStepHelper::RoleBinding_Found, $step->getStatus($deployment));

        $rule->delete();

        $this->assertSame(DeploymentStepHelper::RoleBinding_FoundNotExpected, $step->getStatus($deployment));
    }

    public function testThePreviewOfABindingShowsWhatItPointsAt(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::roleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);
        (new RoleStep())->startDeployCommand($deployment);
        (new ServiceAccountStep())->startDeployCommand($deployment);
        $step = new RoleBindingStep();

        $this->assertNull($this->preview($step, $deployment)['remote']);

        $step->startDeployCommand($deployment);

        $preview = $this->preview($step, $deployment);
        $this->assertSame($deployment->name, $preview['remote']['roleRef']['name']);
        $this->assertArrayNotHasKey('resourceVersion', $preview['remote']['metadata']);
    }

    public function testTerminatingRemovesTheBinding(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::roleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);
        (new RoleStep())->startDeployCommand($deployment);
        (new ServiceAccountStep())->startDeployCommand($deployment);
        $step = new RoleBindingStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::RoleBinding_NotFound
        );
    }

    // </editor-fold>

    // <editor-fold desc="Cluster role and its binding">

    /**
     * A cluster role is not in a namespace, so its name has to be unique across the whole
     * cluster. kso qualifies it with the namespace, and that is the only thing keeping two
     * customers' cluster roles apart.
     */
    public function testAClusterRoleIsNamedAfterTheNamespaceItBelongsTo(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::clusterRoleRule([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'api_group' => '',
            'resource' => 'nodes',
            'verbs' => 'get,list',
        ]);

        (new ClusterRoleStep())->startDeployCommand($deployment);

        $role = $this->cluster()->getClusterRoleByName("{$deployment->name}.{$this->testNamespace}");
        $this->assertSame(['nodes'], $role->getAttribute('rules')[0]['resources']);
    }

    public function testTheClusterRoleBindingPointsAtTheNamespacedServiceAccount(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::clusterRoleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);
        (new ClusterRoleStep())->startDeployCommand($deployment);
        (new ServiceAccountStep())->startDeployCommand($deployment);

        (new ClusterRoleBindingStep())->startDeployCommand($deployment);

        $name = "{$deployment->name}.{$this->testNamespace}";
        $binding = $this->cluster()->getClusterRoleBindingByName($name);
        $this->assertSame($name, $binding->getAttribute('roleRef')['name']);
        $this->assertSame($this->testNamespace, $binding->getAttribute('subjects')[0]['namespace']);
    }

    /**
     * Today's behaviour, and an asymmetry worth having written down: `RoleStep` checks
     * `exists()` before deleting and this one does not, so terminating a workspace that
     * never had a cluster role throws where the namespaced one stays quiet.
     */
    public function testTerminatingAClusterRoleBindingThatWasNeverCreatedThrows(): void {
        $deployment = $this->deploymentInANamespace();

        $this->expectException(\RenokiCo\PhpK8s\Exceptions\KubernetesAPIException::class);

        (new ClusterRoleBindingStep())->startTerminateCommand($deployment);
    }

    /**
     * The three states a cluster role can be in, in the order a workspace goes through
     * them. The last one is what a revoked permission looks like from kso's side: the
     * object is still granted cluster-wide, and nothing but this status says so.
     */
    public function testTheClusterRoleStatusFollowsTheRulesAndTheObject(): void {
        $deployment = $this->deploymentInANamespace();
        $step = new ClusterRoleStep();

        $this->assertSame(DeploymentStepHelper::ClusterRole_NotFoundNotExpected, $step->getStatus($deployment));

        $rule = Fixtures::clusterRoleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);
        $this->assertSame(DeploymentStepHelper::ClusterRole_NotFound, $step->getStatus($deployment));

        $step->startDeployCommand($deployment);
        $this->assertSame(DeploymentStepHelper::ClusterRole_Found, $step->getStatus($deployment));

        $rule->delete();
        $this->assertSame(DeploymentStepHelper::ClusterRole_FoundNotExpected, $step->getStatus($deployment));
    }

    /**
     * A specification with no cluster role rules gets no cluster role, and this is the one
     * of the five where that restraint is load bearing: an empty cluster role is not
     * removed by deleting the namespace, so applying one per deployment would leave an
     * object behind in the cluster for every workspace ever created.
     */
    public function testASpecificationWithoutClusterRoleRulesGetsNoClusterRole(): void {
        $deployment = $this->deploymentInANamespace();
        $step = new ClusterRoleStep();

        $step->startDeployCommand($deployment);

        $this->assertSame(DeploymentStepHelper::ClusterRole_NotFoundNotExpected, $step->getStatus($deployment));
        $this->assertFalse(
            $this->cluster()->clusterRole()->whereName("{$deployment->name}.{$this->testNamespace}")->exists()
        );
    }

    /**
     * And terminating one that was never created stays quiet, the same way the namespaced
     * Role does - both check `exists()` first. The two bindings do not, which is the
     * asymmetry the test below this section holds in place.
     */
    public function testTerminatingAClusterRoleThatWasNeverCreatedIsQuiet(): void {
        $deployment = $this->deploymentInANamespace();

        (new ClusterRoleStep())->startTerminateCommand($deployment);

        $this->assertSame(
            DeploymentStepHelper::ClusterRole_NotFoundNotExpected,
            (new ClusterRoleStep())->getStatus($deployment)
        );
    }

    public function testThePreviewOfAClusterRoleShowsItsRulesBeforeAndAfter(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::clusterRoleRule([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'resource' => 'nodes',
        ]);
        $step = new ClusterRoleStep();

        $before = $this->preview($step, $deployment);
        $this->assertSame("{$deployment->name}.{$this->testNamespace}", $before['local']['metadata']['name']);
        $this->assertNull($before['remote']);

        $step->startDeployCommand($deployment);

        $after = $this->preview($step, $deployment);
        $this->assertSame(['nodes'], $after['remote']['rules'][0]['resources']);
        $this->assertArrayNotHasKey('managedFields', $after['remote']['metadata']);
    }

    /**
     * Terminating has to take the cluster role with it. It is the leak that matters most
     * of the five: a namespace being deleted does not remove it, so a cluster role left
     * behind is a permission still granted to a workspace that no longer exists.
     */
    public function testTerminatingRemovesTheClusterRole(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::clusterRoleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);
        $step = new ClusterRoleStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::ClusterRole_NotFound
        );
    }

    /**
     * The cluster role binding's own three states, same shape as the role's.
     */
    public function testTheClusterRoleBindingStatusFollowsTheRulesAndTheObject(): void {
        $deployment = $this->deploymentInANamespace();
        $step = new ClusterRoleBindingStep();

        $this->assertSame(DeploymentStepHelper::ClusterRoleBinding_NotFoundNotExpected, $step->getStatus($deployment));

        $rule = Fixtures::clusterRoleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);
        $this->assertSame(DeploymentStepHelper::ClusterRoleBinding_NotFound, $step->getStatus($deployment));

        (new ClusterRoleStep())->startDeployCommand($deployment);
        (new ServiceAccountStep())->startDeployCommand($deployment);
        $step->startDeployCommand($deployment);
        $this->assertSame(DeploymentStepHelper::ClusterRoleBinding_Found, $step->getStatus($deployment));

        $rule->delete();
        $this->assertSame(DeploymentStepHelper::ClusterRoleBinding_FoundNotExpected, $step->getStatus($deployment));
    }

    /**
     * Three refusals in a row, and the middle one is the interesting one: the cluster role
     * is compared with its *own* success status, so a specification without cluster role
     * rules walks past a step that created nothing. That is what `RoleBindingStep` was
     * meant to do and does not.
     */
    public function testTheClusterRoleBindingRefusesUntilTheClusterRoleAndServiceAccountAreThere(): void {
        $deployment = $this->deploymentInTheTestNamespace();
        $step = new ClusterRoleBindingStep();

        $this->assertSame('Missing Namespace', $step->validateDeployCommand($deployment));

        (new NamespaceStep())->startDeployCommand($deployment);
        Fixtures::clusterRoleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);
        $this->assertSame('Missing Cluster Role', $step->validateDeployCommand($deployment));

        (new ClusterRoleStep())->startDeployCommand($deployment);
        $this->assertSame('Missing Service Account', $step->validateDeployCommand($deployment));

        (new ServiceAccountStep())->startDeployCommand($deployment);
        $this->assertNull($step->validateDeployCommand($deployment));
    }

    /**
     * A specification with no cluster role rules at all gets past the cluster role gate,
     * because nothing was expected of it. Only the service account is still required.
     */
    public function testTheClusterRoleBindingAcceptsASpecificationWithNoClusterRoleRules(): void {
        $deployment = $this->deploymentInANamespace();
        (new ServiceAccountStep())->startDeployCommand($deployment);

        $this->assertNull((new ClusterRoleBindingStep())->validateDeployCommand($deployment));
    }

    public function testThePreviewOfAClusterRoleBindingShowsWhatItPointsAt(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::clusterRoleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);
        (new ClusterRoleStep())->startDeployCommand($deployment);
        (new ServiceAccountStep())->startDeployCommand($deployment);
        $step = new ClusterRoleBindingStep();

        $this->assertNull($this->preview($step, $deployment)['remote']);

        $step->startDeployCommand($deployment);

        $preview = $this->preview($step, $deployment);
        $name = "{$deployment->name}.{$this->testNamespace}";
        $this->assertSame($name, $preview['remote']['roleRef']['name']);
        $this->assertSame($this->testNamespace, $preview['remote']['subjects'][0]['namespace']);
        $this->assertArrayNotHasKey('uid', $preview['remote']['metadata']);
    }

    public function testTerminatingRemovesTheClusterRoleBinding(): void {
        $deployment = $this->deploymentInANamespace();
        Fixtures::clusterRoleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);
        (new ClusterRoleStep())->startDeployCommand($deployment);
        (new ServiceAccountStep())->startDeployCommand($deployment);
        $step = new ClusterRoleBindingStep();
        $step->startDeployCommand($deployment);

        $step->startTerminateCommand($deployment);

        $this->eventually(
            fn () => $step->getStatus($deployment) === DeploymentStepHelper::ClusterRoleBinding_NotFound
        );
    }

    // </editor-fold>

    /**
     * A step's preview, decoded into the two halves it is made of.
     *
     * The step hands back a json string holding two more json strings, and `remote` is
     * null until the object exists. Decoding it here keeps every assertion above about
     * the manifest rather than about the envelope.
     *
     * @return array{local: array<string, mixed>, remote: array<string, mixed>|null}
     */
    private function preview(BaseDeploymentStep $step, Deployment $deployment): array {
        $envelope = json_decode($step->getPreview($deployment), true);

        return [
            'local' => json_decode($envelope['local'], true),
            'remote' => $envelope['remote'] === null ? null : json_decode($envelope['remote'], true),
        ];
    }

    /**
     * Every step here wants the namespace to be there already.
     */
    private function deploymentInANamespace(): Deployment {
        $deployment = $this->deploymentInTheTestNamespace();
        (new NamespaceStep())->startDeployCommand($deployment);

        return $deployment;
    }

}
