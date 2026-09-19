<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\ClusterRoleBindingStep;
use App\Libraries\DeploymentSteps\ClusterRoleStep;
use App\Libraries\DeploymentSteps\RoleBindingStep;
use App\Libraries\DeploymentSteps\RoleStep;
use App\Libraries\DeploymentSteps\ServiceAccountStep;
use App\ManifestTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The five RBAC steps, tested together because that is the only way the interesting part
 * is visible.
 *
 * Each manifest is a handful of lines, and each one is correct on its own. What matters is
 * that the names line up: a binding names a role and a service account by string, and
 * Kubernetes accepts a binding that points at nothing. The permissions then silently do
 * not exist, and the workload fails on its first API call instead of at deploy time.
 *
 * Namespaced and cluster-scoped objects are named differently on purpose - a cluster role
 * is shared across the whole cluster, so two workspaces with the same deployment name
 * would collide without the namespace in the name.
 */
class RbacStepsTest extends ManifestTestCase {

    // <editor-fold desc="The objects themselves">

    public function testServiceAccountIsNamedAndNamespacedAfterTheDeployment(): void {
        $deployment = Fixtures::deployableDeployment();

        $manifest = $this->manifest(ServiceAccountStep::class, $deployment);

        $this->assertSame($deployment->name, $manifest['metadata']['name']);
        $this->assertSame($deployment->namespace, $manifest['metadata']['namespace']);
    }

    public function testRoleCarriesTheSpecificationsRules(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::roleRule([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'api_group' => 'apps',
            'resource' => 'deployments',
            'verbs' => 'get,list,watch',
        ]);

        $rules = $this->manifest(RoleStep::class, $deployment)['rules'];

        $this->assertCount(1, $rules);
        $this->assertSame(['apps'], $rules[0]['apiGroups']);
        $this->assertSame(['deployments'], $rules[0]['resources']);
        $this->assertSame(['get', 'list', 'watch'], $rules[0]['verbs']);
    }

    /**
     * The core API group is the empty string, so a rule for pods or secrets leaves the
     * field blank rather than filling something in.
     */
    public function testEmptyApiGroupMeansTheCoreApi(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::roleRule([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'api_group' => '',
            'resource' => 'pods',
        ]);

        $this->assertSame([''], $this->manifest(RoleStep::class, $deployment)['rules'][0]['apiGroups']);
    }

    /**
     * Today's behaviour, and a trap. The verbs are split on commas and nothing is trimmed,
     * so a specification written as "get, list" - which is how anyone would type it -
     * produces a verb of " list" with a leading space. Kubernetes rejects it, and the whole
     * Role fails to apply.
     */
    public function testVerbsAreSplitOnCommasWithoutTrimming(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::roleRule([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'verbs' => 'get, list',
        ]);

        $this->assertSame(['get', ' list'], $this->manifest(RoleStep::class, $deployment)['rules'][0]['verbs']);
    }

    public function testSeveralRulesAreAllCarriedOver(): void {
        $deployment = Fixtures::deployableDeployment();
        foreach ([['pods', 'get'], ['secrets', 'get,list']] as [$resource, $verbs]) {
            Fixtures::roleRule([
                'deployment_specification_id' => $deployment->deployment_specification_id,
                'resource' => $resource,
                'verbs' => $verbs,
            ]);
        }

        $rules = $this->manifest(RoleStep::class, $deployment)['rules'];

        $this->assertCount(2, $rules);
        $this->assertSame([['pods'], ['secrets']], array_column($rules, 'resources'));
    }

    public function testRoleWithoutRulesGrantsNothing(): void {
        $deployment = Fixtures::deployableDeployment();

        $this->assertSame([], $this->manifest(RoleStep::class, $deployment)['rules']);
    }

    /**
     * A ClusterRole is not namespaced, so the namespace goes into the name instead - two
     * workspaces running a deployment called `api` would otherwise share one object.
     */
    public function testClusterRoleCarriesTheNamespaceInItsNameAndIsNotNamespaced(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::clusterRoleRule([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'api_group' => 'gateway.networking.k8s.io',
            'resource' => 'gateways',
            'verbs' => 'list',
        ]);

        $manifest = $this->manifest(ClusterRoleStep::class, $deployment);

        $this->assertSame("{$deployment->name}.{$deployment->namespace}", $manifest['metadata']['name']);
        $this->assertArrayNotHasKey('namespace', $manifest['metadata']);
        $this->assertSame(['gateways'], $manifest['rules'][0]['resources']);
    }

    /**
     * The verbs are split the same way on both sides. The two steps hold a copy of the
     * loop each, so a rule that lists several verbs has to be asked of the cluster role as
     * well - a cluster role granted `get,list` as one verb called "get,list" grants
     * nothing at all, cluster-wide and silently.
     */
    public function testClusterRoleVerbsAreSplitOnCommasToo(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::clusterRoleRule([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'verbs' => 'get,list,watch',
        ]);

        $this->assertSame(
            ['get', 'list', 'watch'],
            $this->manifest(ClusterRoleStep::class, $deployment)['rules'][0]['verbs']
        );
    }

    /**
     * The two rule sets live in separate tables and must not leak into each other: a rule
     * meant for one namespace would otherwise be granted across the whole cluster.
     */
    public function testRoleAndClusterRoleReadTheirOwnRules(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::roleRule([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'resource' => 'only-on-the-role',
        ]);
        Fixtures::clusterRoleRule([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'resource' => 'only-on-the-cluster-role',
        ]);

        $role = $this->manifest(RoleStep::class, $deployment)['rules'];
        $clusterRole = $this->manifest(ClusterRoleStep::class, $deployment)['rules'];

        $this->assertSame([['only-on-the-role']], array_column($role, 'resources'));
        $this->assertSame([['only-on-the-cluster-role']], array_column($clusterRole, 'resources'));
    }

    // </editor-fold>

    // <editor-fold desc="The names that have to line up">

    /**
     * The binding is the join, and it is made of strings: subject, role and namespace all
     * have to match objects the other steps produce.
     */
    public function testRoleBindingTiesTheServiceAccountToTheRole(): void {
        $deployment = Fixtures::deployableDeployment();

        $binding = $this->manifest(RoleBindingStep::class, $deployment);
        $serviceAccount = $this->manifest(ServiceAccountStep::class, $deployment);
        $role = $this->manifest(RoleStep::class, $deployment);

        $this->assertSame($deployment->namespace, $binding['metadata']['namespace']);

        $this->assertSame('Role', $binding['roleRef']['kind']);
        $this->assertSame($role['metadata']['name'], $binding['roleRef']['name']);

        $subject = $binding['subjects'][0];
        $this->assertSame('ServiceAccount', $subject['kind']);
        $this->assertSame($serviceAccount['metadata']['name'], $subject['name']);
        $this->assertSame($serviceAccount['metadata']['namespace'], $subject['namespace']);
    }

    /**
     * Same join, one level up: the binding is cluster-scoped and so is the role it names,
     * but the subject still points into the deployment's own namespace.
     */
    public function testClusterRoleBindingTiesTheServiceAccountToTheClusterRole(): void {
        $deployment = Fixtures::deployableDeployment();

        $binding = $this->manifest(ClusterRoleBindingStep::class, $deployment);
        $clusterRole = $this->manifest(ClusterRoleStep::class, $deployment);
        $serviceAccount = $this->manifest(ServiceAccountStep::class, $deployment);

        $this->assertArrayNotHasKey('namespace', $binding['metadata']);
        $this->assertSame("{$deployment->name}.{$deployment->namespace}", $binding['metadata']['name']);

        $this->assertSame('ClusterRole', $binding['roleRef']['kind']);
        $this->assertSame($clusterRole['metadata']['name'], $binding['roleRef']['name']);

        $subject = $binding['subjects'][0];
        $this->assertSame($serviceAccount['metadata']['name'], $subject['name']);
        $this->assertSame($serviceAccount['metadata']['namespace'], $subject['namespace']);
    }

    /**
     * Both bindings point at the same service account, so a deployment gets one identity
     * carrying both its namespaced and its cluster-wide permissions.
     */
    public function testBothBindingsUseTheOneServiceAccount(): void {
        $deployment = Fixtures::deployableDeployment();

        $this->assertSame(
            $this->subject($deployment, RoleBindingStep::class),
            $this->subject($deployment, ClusterRoleBindingStep::class)
        );
    }

    // </editor-fold>

    // <editor-fold desc="What the steps say about themselves">

    /**
     * The description the API hands the frontend, which decides which buttons a step gets.
     * An identifier is also what `DeploymentStepHelper::GetStep()` matches on, so a typo
     * here is a step that can never be reached from outside.
     *
     * @param class-string $step
     * @param array<string, mixed> $expected
     */
    #[DataProvider('descriptions')]
    public function testEachStepDescribesItself(string $step, array $expected): void {
        $this->assertSame($expected, (new $step())->toArray());
    }

    /**
     * @return array<string, array{class-string, array<string, mixed>}>
     */
    public static function descriptions(): array {
        $commands = [
            'hasPreviewCommand' => true,
            'hasStatusCommand' => true,
            'hasDeployCommand' => true,
            'hasKubernetesEvents' => false,
            'hasKubernetesStatus' => false,
            'hasTerminateCommand' => true,
        ];

        return [
            'service account' => [ServiceAccountStep::class, array_merge(
                ['identifier' => 'service-account', 'level' => 'deployment', 'name' => 'Service Account'], $commands
            )],
            'role' => [RoleStep::class, array_merge(
                ['identifier' => 'role', 'level' => 'deployment', 'name' => 'Role'], $commands
            )],
            'role binding' => [RoleBindingStep::class, array_merge(
                ['identifier' => 'role-binding', 'level' => 'deployment', 'name' => 'Role Binding'], $commands
            )],
            'cluster role' => [ClusterRoleStep::class, array_merge(
                ['identifier' => 'cluster-role', 'level' => 'deployment', 'name' => 'Cluster Role'], $commands
            )],
            'cluster role binding' => [ClusterRoleBindingStep::class, array_merge(
                ['identifier' => 'cluster-role-binding', 'level' => 'deployment', 'name' => 'Cluster Role Binding'], $commands
            )],
        ];
    }

    /**
     * None of the five reacts to anything changing elsewhere: RBAC is written once when
     * the deployment is deployed. A trigger added here would make every edit of a
     * workspace reapply permissions, so the empty list is worth holding.
     *
     * @param class-string $step
     */
    #[DataProvider('steps')]
    public function testNoStepListensForATrigger(string $step): void {
        $this->assertSame([], (new $step())->getTriggers());
    }

    /**
     * `hasKubernetesEvents()` and `hasKubernetesStatus()` are false for all five, and these
     * are what the endpoints behind those flags would return. They answer with an empty
     * list rather than throwing, so a frontend that asks anyway gets nothing instead of a
     * 500.
     *
     * @param class-string $step
     */
    #[DataProvider('steps')]
    public function testNoStepReportsEventsOrStatusFromKubernetes(string $step): void {
        $deployment = Fixtures::deployableDeployment();
        $instance = new $step();

        $this->assertSame([], $instance->getKubernetesEvents($deployment));
        $this->assertSame([], $instance->getKubernetesStatus($deployment));
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function steps(): array {
        return [
            'service account' => [ServiceAccountStep::class],
            'role' => [RoleStep::class],
            'role binding' => [RoleBindingStep::class],
            'cluster role' => [ClusterRoleStep::class],
            'cluster role binding' => [ClusterRoleBindingStep::class],
        ];
    }

    // </editor-fold>

    // <editor-fold desc="The status a finished deploy should end on">

    /**
     * A service account is made for every deployment with RBAC on, so the only success
     * there is is that it exists.
     */
    public function testAServiceAccountIsAlwaysExpected(): void {
        $deployment = Fixtures::deployableDeployment();

        $this->assertSame('found', (new ServiceAccountStep())->getSuccessStatus($deployment));
    }

    /**
     * The other four are conditional, and this is the method that says so: with no rules
     * configured, a deployment is finished when the object is *absent*. Get this wrong and
     * every deployment without RBAC rules sits in error forever.
     *
     * @param class-string $step
     */
    #[DataProvider('conditionalSteps')]
    public function testWithoutRulesTheSuccessStatusIsThatNothingIsThere(string $step, string $fixture): void {
        $deployment = Fixtures::deployableDeployment();

        $this->assertSame('not-found-not-expected', (new $step())->getSuccessStatus($deployment));
    }

    /**
     * @param class-string $step
     */
    #[DataProvider('conditionalSteps')]
    public function testWithRulesTheSuccessStatusIsThatTheObjectIsThere(string $step, string $fixture): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::$fixture(['deployment_specification_id' => $deployment->deployment_specification_id]);

        $this->assertSame('found', (new $step())->getSuccessStatus($deployment));
    }

    /**
     * The role steps read their own rule table, and the binding steps read the same table
     * as the role they bind - a binding is expected exactly when its role is.
     *
     * @return array<string, array{class-string, string}>
     */
    public static function conditionalSteps(): array {
        return [
            'role' => [RoleStep::class, 'roleRule'],
            'role binding' => [RoleBindingStep::class, 'roleRule'],
            'cluster role' => [ClusterRoleStep::class, 'clusterRoleRule'],
            'cluster role binding' => [ClusterRoleBindingStep::class, 'clusterRoleRule'],
        ];
    }

    /**
     * Rules of the other kind do not make an object expected. Without this the cluster
     * role steps would be satisfied by a namespaced rule and vice versa, and a deployment
     * would report success with the wrong half of its permissions applied.
     */
    public function testEachStepReadsOnlyItsOwnRuleTable(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::roleRule(['deployment_specification_id' => $deployment->deployment_specification_id]);

        $this->assertSame('found', (new RoleStep())->getSuccessStatus($deployment));
        $this->assertSame('found', (new RoleBindingStep())->getSuccessStatus($deployment));
        $this->assertSame('not-found-not-expected', (new ClusterRoleStep())->getSuccessStatus($deployment));
        $this->assertSame('not-found-not-expected', (new ClusterRoleBindingStep())->getSuccessStatus($deployment));
    }

    // </editor-fold>

    // <editor-fold desc="What is refused before anything is sent">

    /**
     * The name is the object's name in Kubernetes, and every one of the five refuses
     * without one before it goes anywhere near the cluster. That order matters: these are
     * the only checks that still work when the cluster is unreachable.
     *
     * @param class-string $step
     */
    #[DataProvider('steps')]
    public function testEveryStepRefusesADeploymentWithoutAName(string $step): void {
        $deployment = Fixtures::deployableDeployment(['name' => '']);

        $this->assertSame('Missing name', (new $step())->validateDeployCommand($deployment));
    }

    /**
     * The three namespaced steps also need a namespace to put the object in. The two
     * cluster-scoped ones do not check - see the note on the cluster role below.
     *
     * @param class-string $step
     */
    #[DataProvider('namespacedSteps')]
    public function testTheNamespacedStepsRefuseADeploymentWithoutANamespace(string $step): void {
        $deployment = Fixtures::deployableDeployment(['namespace' => '']);

        $this->assertSame('Missing namespace', (new $step())->validateDeployCommand($deployment));
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function namespacedSteps(): array {
        return [
            'service account' => [ServiceAccountStep::class],
            'role binding' => [RoleBindingStep::class],
            'cluster role binding' => [ClusterRoleBindingStep::class],
        ];
    }

    /**
     * The two role steps validate the name and nothing else, so they accept without a
     * cluster being reachable at all - this test runs with the credentials cleared.
     *
     * For `ClusterRoleStep` that is more than tidiness: the object's name is
     * `<name>.<namespace>`, so a deployment with an empty namespace produces a cluster
     * role called `api.` and no check anywhere says a word. Reported, not fixed.
     */
    public function testTheRoleStepsValidateNothingButTheName(): void {
        $deployment = Fixtures::deployableDeployment(['namespace' => '']);

        $this->assertNull((new RoleStep())->validateDeployCommand($deployment));
        $this->assertNull((new ClusterRoleStep())->validateDeployCommand($deployment));
    }

    // </editor-fold>

    /**
     * @param class-string $step
     * @return array<string, mixed>
     */
    private function subject(Deployment $deployment, string $step): array {
        return $this->manifest($step, $deployment)['subjects'][0];
    }

}
