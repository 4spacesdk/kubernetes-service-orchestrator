<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\Workspace;
use App\Fixtures;
use App\Models\DeploymentModel;

/**
 * The Workspaces endpoints - onboarding, and everything that happens to a customer after.
 *
 * `workspaces/create` is the only endpoint in the API that validates its input properly:
 * six separate rejections before anything is written, including two uniqueness checks. It
 * is also the one that builds the most - a workspace, its labels, and a deployment per
 * specification in the package. Most of this file is about that.
 *
 * **Deploy and terminate are not tested here.** Both walk every deployment's steps and
 * talk to a cluster. They belong in the integration suite, behind its own switch, against
 * a cluster that may be thrown away - see `IntegrationTestCase`. The same goes for the
 * whole `Kubernetes` controller: pods, logs, exec and node-info are cluster calls from the
 * first line, and `exec` writes to a running container.
 */
class WorkspacesApiTest extends ControllerTestCase {

    // <editor-fold desc="Creating a workspace">

    public function testAWorkspaceIsCreatedFromAPackage(): void {
        $domain = Fixtures::domain(['name' => 'example.org']);
        $package = Fixtures::deploymentPackage();

        $body = $this->create($package->id, [
            'name' => 'Acme Industries',
            'namespace' => 'acme',
            'domainId' => $domain->id,
            'subdomain' => 'acme',
        ]);

        $this->assertSame('OK', $body['status']);
        $this->assertSame('Acme Industries', $body['resource']['name_readable']);
        $this->assertSame('acme', $body['resource']['namespace']);
    }

    /**
     * The readable name is kept as typed, and a second, system name is derived from it:
     * lowercased, punctuation removed, spaces turned into dashes. That one becomes part of
     * Kubernetes object names, so it has to survive being used as a DNS label.
     */
    public function testTheSystemNameIsDerivedFromTheReadableOne(): void {
        $domain = Fixtures::domain();
        $package = Fixtures::deploymentPackage();

        $body = $this->create($package->id, [
            'name' => 'Acme Industries, Inc.',
            'namespace' => 'acme',
            'domainId' => $domain->id,
            'subdomain' => 'acme',
        ]);

        $this->assertSame('Acme Industries, Inc.', $body['resource']['name_readable']);
        $this->assertSame('acme-industries-inc', $body['resource']['name_system']);
    }

    /**
     * A name that starts with Ø is a name. The dialog refused it, saying it started with a
     * space, and the system name lost the letter: "Øster" became "ster".
     */
    public function testANameWithDanishLettersIsKeptAndSpelledOutInTheSystemName(): void {
        $body = $this->create(Fixtures::deploymentPackage()->id, [
            'name' => 'Øster Ås',
            'namespace' => 'kb-oester-aas',
            'domainId' => Fixtures::domain()->id,
            'subdomain' => 'oester',
        ]);

        $this->assertSame('OK', $body['status'], $body['error'] ?? '');
        $this->assertSame('Øster Ås', $body['resource']['name_readable']);
        $this->assertSame('oester-aas', $body['resource']['name_system']);
    }

    /**
     * The namespace becomes a Kubernetes namespace, so what Kubernetes would refuse is
     * refused here. It used to be taken as given - hiding the field in the dialog was a way
     * past its (wrong) rule.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('namespacesKubernetesRefuses')]
    public function testANamespaceKubernetesWouldRefuseIsRefused(string $namespace): void {
        $error = $this->createExpectingFailure(Fixtures::deploymentPackage()->id, [
            'name' => 'Acme',
            'namespace' => $namespace,
            'domainId' => Fixtures::domain()->id,
            'subdomain' => 'acme',
        ]);

        $this->assertStringStartsWith('Invalid namespace', $error);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function namespacesKubernetesRefuses(): array {
        return [
            'ends in a hyphen' => ['kb-'],
            'uppercase' => ['Acme'],
            'a Danish letter' => ['kb-øster'],
            '64 characters' => [str_repeat('a', 64)],
        ];
    }

    /**
     * The rule the dialog had allowed 15 characters. A namespace of 63 is fine.
     */
    public function testANamespaceOf63CharactersIsAccepted(): void {
        $body = $this->create(Fixtures::deploymentPackage()->id, [
            'name' => 'Acme',
            'namespace' => str_repeat('a', 63),
            'domainId' => Fixtures::domain()->id,
            'subdomain' => 'acme',
        ]);

        $this->assertSame('OK', $body['status'], $body['error'] ?? '');
    }

    /**
     * Every rejection, one per rule. They run before anything is written, which is what
     * separates this endpoint from the rest of the API.
     */
    public function testCreatingIsRejectedWithAReasonForEachMissingPiece(): void {
        $domain = Fixtures::domain();
        $package = Fixtures::deploymentPackage();
        $complete = [
            'name' => 'Acme',
            'namespace' => 'acme',
            'domainId' => $domain->id,
            'subdomain' => 'acme',
        ];

        $this->assertSame('Invalid deployment package', $this->createExpectingFailure(999999, $complete));
        $this->assertSame('Name missing', $this->createExpectingFailure($package->id, ['name' => ''] + $complete));
        $this->assertSame('Domain not found', $this->createExpectingFailure($package->id, ['domainId' => 999999] + $complete));
        $this->assertSame('Subdomain missing', $this->createExpectingFailure($package->id, ['subdomain' => ''] + $complete));
    }

    /**
     * A subdomain is what a customer is reached on, so two workspaces cannot share one on
     * the same domain. Caught before the row is written rather than by a unique index.
     */
    public function testASubdomainCannotBeUsedTwiceOnTheSameDomain(): void {
        $domain = Fixtures::domain();
        $package = Fixtures::deploymentPackage();
        Fixtures::workspace(['domain_id' => $domain->id, 'subdomain' => 'taken']);

        $error = $this->createExpectingFailure($package->id, [
            'name' => 'Second', 'namespace' => 'second',
            'domainId' => $domain->id, 'subdomain' => 'taken',
        ]);

        $this->assertSame('Domain and subdomain already used', $error);
    }

    public function testTheSameNameCannotBeUsedTwiceInOneNamespace(): void {
        $domain = Fixtures::domain();
        $package = Fixtures::deploymentPackage();
        Fixtures::workspace(['namespace' => 'shared', 'name_system' => 'acme', 'subdomain' => 'first']);

        $error = $this->createExpectingFailure($package->id, [
            'name' => 'Acme', 'namespace' => 'shared',
            'domainId' => $domain->id, 'subdomain' => 'second',
        ]);

        $this->assertSame('Name already used', $error);
    }

    /**
     * The package is a template: every specification in it becomes a deployment, carrying
     * the package's defaults. This is the whole point of onboarding from a package.
     */
    public function testEverySpecificationInThePackageBecomesADeployment(): void {
        $domain = Fixtures::domain();
        $package = Fixtures::deploymentPackage();
        foreach (['api', 'worker'] as $name) {
            $specification = Fixtures::deploymentSpecification(['name' => $name]);
            Fixtures::packageSpecification([
                'deployment_package_id' => $package->id,
                'deployment_specification_id' => $specification->id,
                'default_version' => '3.1.4',
                'default_replicas' => 2,
            ]);
        }

        $body = $this->create($package->id, [
            'name' => 'Acme', 'namespace' => 'acme',
            'domainId' => $domain->id, 'subdomain' => 'acme',
        ]);

        $deployments = $this->deploymentsOf((int) $body['resource']['id']);

        $this->assertCount(2, $deployments);
        $this->assertSame(['3.1.4', '3.1.4'], array_map(static fn ($d) => $d->version, $deployments));
        $this->assertSame([2, 2], array_map(static fn ($d) => (int) $d->replicas, $deployments));
    }

    // </editor-fold>

    // <editor-fold desc="Adding a deployment later">

    public function testADeploymentCanBeAddedToAnExistingWorkspace(): void {
        $domain = Fixtures::domain();
        $package = Fixtures::deploymentPackage();
        $specification = Fixtures::deploymentSpecification(['name' => 'extra']);
        Fixtures::packageSpecification([
            'deployment_package_id' => $package->id,
            'deployment_specification_id' => $specification->id,
        ]);
        $workspace = $this->createWorkspace($package->id, $domain->id);

        $body = $this->decode($this->signedIn()->post(
            "workspaces/{$workspace->id}/deployments?" . http_build_query([
                'deploymentSpecificationId' => $specification->id,
                'name' => 'added-later',
                'version' => '9.9.9',
            ])
        ));

        $this->assertSame('OK', $body['status']);
        $this->assertSame('added-later', $body['resource']['name']);
        $this->assertSame('9.9.9', $body['resource']['version']);
    }

    public function testAddingADeploymentToAnUnknownWorkspaceIsRefused(): void {
        $body = $this->decode($this->signedIn()->post('workspaces/999999/deployments?deploymentSpecificationId=1'));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('unknown workspace', $body['error']);
    }

    public function testAddingAnUnknownSpecificationIsRefused(): void {
        $workspace = Fixtures::workspace();

        $body = $this->decode($this->signedIn()->post(
            "workspaces/{$workspace->id}/deployments?deploymentSpecificationId=999999"
        ));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('unknown deployment specification', $body['error']);
    }

    /**
     * Past the two lookups, everything `addDeployment` rejects arrives as an exception and
     * is turned into the same failure shape. The name is the cheapest of those rules to
     * reach: it is taken from the query string, falls back to the specification's name, and
     * an empty string is neither missing nor usable.
     */
    public function testADeploymentTheWorkspaceRefusesIsReportedWithItsReason(): void {
        $package = Fixtures::deploymentPackage();
        $workspace = Fixtures::workspace([
            'deployment_package_id' => $package->id,
            'namespace' => 'acme',
        ]);
        $specification = Fixtures::deploymentSpecification(['name' => 'extra']);

        $body = $this->decode($this->signedIn()->post(
            "workspaces/{$workspace->id}/deployments?" . http_build_query([
                'deploymentSpecificationId' => $specification->id,
                'name' => '',
            ])
        ));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('Name missing', $body['error']);
    }

    // </editor-fold>

    // <editor-fold desc="Changing and reading">

    public function testTheNameIsUpdated(): void {
        $workspace = Fixtures::workspace(['name_readable' => 'Before']);

        $body = $this->decode($this->signedIn()->put("workspaces/{$workspace->id}/name?value=After"));

        $this->assertSame('After', $body['resource']['name_readable']);
    }

    public function testTheDefaultServicesAreUpdated(): void {
        $workspace = Fixtures::workspace();
        $database = Fixtures::databaseService(['name' => 'shared-db']);

        $this->signedIn()->put("workspaces/{$workspace->id}/databaseServiceId?value={$database->id}");

        $reloaded = new Workspace();
        $reloaded->find($workspace->id);
        $this->assertSame((int) $database->id, (int) $reloaded->database_service_id);
    }

    /**
     * The email service is the sibling of the one above with a tail on it: changing it
     * re-runs the Deployment step for every deployment in the workspace, because the
     * credentials are handed to the workload through its environment. With no deployments
     * there is nothing to re-run, which is what keeps this one out of the cluster.
     */
    public function testTheEmailServiceIsUpdated(): void {
        $workspace = Fixtures::workspace();
        $email = Fixtures::emailService(['name' => 'shared-mail']);

        $body = $this->decode($this->signedIn()->put(
            "workspaces/{$workspace->id}/emailServiceId?value={$email->id}"
        ));

        $this->assertSame('OK', $body['status']);
        $this->assertSame((int) $email->id, (int) $this->reload($workspace)->email_service_id);
    }

    /**
     * Labels are shared rows joined to the workspace, and the endpoint replaces the whole
     * set rather than merging into it - the same rule as on a deployment.
     */
    public function testLabelsReplaceTheWholeSet(): void {
        $workspace = Fixtures::workspace();
        $this->putValues("workspaces/{$workspace->id}/labels", [
            ['name' => 'team', 'value' => 'platform'],
            ['name' => 'tier', 'value' => 'backend'],
        ]);
        $this->assertCount(2, $this->labelsOf($workspace));

        $this->putValues("workspaces/{$workspace->id}/labels", [['name' => 'team', 'value' => 'infra']]);

        $rows = $this->labelsOf($workspace);
        $this->assertCount(1, $rows);
        $this->assertSame('infra', $rows[0]['value']);
    }

    /**
     * The status endpoint is not a read - it recomputes every deployment's status first,
     * then derives the workspace's from them. Calling it is how the UI refreshes a
     * workspace that looks stale.
     */
    public function testTheStatusEndpointRecomputesFromTheDeployments(): void {
        $workspace = Fixtures::workspace(['status' => \WorkspaceStatusTypes::Active]);
        Fixtures::deployment(['workspace_id' => $workspace->id, 'status' => \DeploymentStatusTypes::Draft]);

        $body = $this->decode($this->signedIn()->get("workspaces/{$workspace->id}/status"));

        $this->assertSame('OK', $body['status']);

        // Which status it lands on is `checkStatus()`'s business, and
        // WorkspaceCheckStatusTest covers the rules. What matters here is that the
        // endpoint recomputed rather than handing back the stored value.
        $this->assertNotSame(\WorkspaceStatusTypes::Active, $body['resource']['status']);

        $stored = new Workspace();
        $stored->find($workspace->id);
        $this->assertSame($stored->status, $body['resource']['status']);
    }

    /**
     * Filtered through a relation - `deployment.workspace.id` - rather than a column, which
     * is a different query path than the one on the deployments controller.
     */
    public function testMigrationJobsAreListedForTheWholeWorkspace(): void {
        $workspace = Fixtures::workspace();
        $first = Fixtures::deployment(['workspace_id' => $workspace->id, 'name' => 'api']);
        $second = Fixtures::deployment(['workspace_id' => $workspace->id, 'name' => 'worker']);
        $elsewhere = Fixtures::deployment(['name' => 'other-workspace']);
        foreach ([[$first->id, 'api-log'], [$second->id, 'worker-log'], [$elsewhere->id, 'not-mine']] as [$id, $log]) {
            $this->db->table('migration_jobs')->insert([
                'deployment_id' => $id, 'status' => 'completed', 'log' => $log,
                'command' => 'php spark migrate', 'image' => 'registry/app:1.0',
                'created' => date('Y-m-d H:i:s'),
            ]);
        }

        $body = $this->decode($this->signedIn()->get("workspaces/{$workspace->id}/migration-jobs"));

        $logs = array_column($body['resources'], 'log');
        sort($logs);
        $this->assertSame(['api-log', 'worker-log'], $logs);
    }

    // </editor-fold>

    // <editor-fold desc="Ingress">

    /**
     * Moving a workspace to another hostname. The aliases are stored as the caller wrote
     * them - `www`, not `www.example.org` - and the domain is what they are validated
     * against, so the two have to be changed in one call.
     */
    public function testTheIngressIsMovedToAnotherSubdomain(): void {
        $domain = Fixtures::domain(['name' => 'example.org']);
        $workspace = Fixtures::workspace(['domain_id' => $domain->id, 'subdomain' => 'before']);

        $body = $this->decode($this->signedIn()->put(
            "workspaces/{$workspace->id}/ingress?" . http_build_query([
                'domainId' => $domain->id,
                'subdomain' => 'after',
                'aliases' => 'www',
            ])
        ));

        $this->assertSame('OK', $body['status']);

        $stored = $this->reload($workspace);
        $this->assertSame('after', $stored->subdomain);
        $this->assertSame('www', $stored->aliases);
    }

    /**
     * The one endpoint pair on this controller that answers an invalid change with an
     * error rather than a silent success: `updateIngress` throws and the controller turns
     * the message into the failure the caller sees.
     */
    public function testAnIngressWithoutASubdomainIsRefused(): void {
        $domain = Fixtures::domain();
        $workspace = Fixtures::workspace(['domain_id' => $domain->id, 'subdomain' => 'before']);

        $body = $this->decode($this->signedIn()->put(
            "workspaces/{$workspace->id}/ingress?domainId={$domain->id}&subdomain=&aliases="
        ));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('Subdomain missing', $body['error']);
        $this->assertSame('before', $this->reload($workspace)->subdomain, 'nothing was written');
    }

    // </editor-fold>

    // <editor-fold desc="Deploying and terminating">

    /**
     * Deploying walks every deployment in the workspace and collects what failed. With no
     * deployments there is nothing to fail, so this is the one path through `deploy` that
     * reaches the end without a cluster - and it is also what onboarding hits between
     * creating a workspace and adding anything to it.
     */
    public function testDeployingAWorkspaceWithoutDeploymentsSucceeds(): void {
        $workspace = Fixtures::workspace(['status' => \WorkspaceStatusTypes::Active]);

        $body = $this->decode($this->signedIn()->put("workspaces/{$workspace->id}/deploy"));

        $this->assertSame('OK', $body['status']);
        $this->assertArrayNotHasKey('error', $body);

        // It went through: deploying sets Deploying and then recomputes from the
        // deployments, and with none there is nothing to be active about.
        $this->assertSame(\WorkspaceStatusTypes::Draft, $this->reload($workspace)->status);
    }

    /**
     * With a deployment in it, every step fails to validate - there is no cluster here -
     * and the errors are handed back per deployment rather than swallowed. Which step
     * complained is not the point; that the endpoint refuses rather than reporting success
     * is, and it is the only place on this controller where that happens for a real change.
     */
    public function testDeployingReportsWhatFailedPerDeployment(): void {
        $deployment = Fixtures::deployableDeployment(['name' => 'api']);

        $body = $this->decode($this->signedIn()->put("workspaces/{$deployment->workspace_id}/deploy"));

        $this->assertSame('ERROR', $body['status']);
        $this->assertStringContainsString('api', $body['error']);
    }

    /**
     * Terminating is the mirror image, and it runs whatever the workspace status was -
     * there is no guard for a workspace that was never deployed.
     *
     * It sets Inactive first and then recomputes from the deployments, and the recompute
     * wins: an empty workspace ends up Draft, not Inactive. Worth pinning, because the
     * status the endpoint writes is not the status the caller ends up with.
     */
    public function testTerminatingAWorkspaceWithoutDeploymentsSucceeds(): void {
        $workspace = Fixtures::workspace(['status' => \WorkspaceStatusTypes::Active]);

        $body = $this->decode($this->signedIn()->put("workspaces/{$workspace->id}/terminate"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame(\WorkspaceStatusTypes::Draft, $this->reload($workspace)->status);
    }

    /**
     * And the same failure shape when a deployment's steps cannot be torn down. Unlike
     * deploying, terminating has no validation in front of it - every step is attempted and
     * whatever it throws becomes the message - so this is what an installation with expired
     * credentials sees when it tries to clean up.
     */
    public function testTerminatingReportsWhatFailedPerDeployment(): void {
        $deployment = Fixtures::deployableDeployment(['name' => 'api']);

        $body = $this->decode($this->signedIn()->put("workspaces/{$deployment->workspace_id}/terminate"));

        $this->assertSame('ERROR', $body['status']);
        $this->assertStringContainsString('api', $body['error']);
    }

    // </editor-fold>

    // <editor-fold desc="Pausing">

    /**
     * A pause is the same shutdown as Terminate, plus a decision that is remembered. The
     * status the recompute would have written - Draft, for a workspace with no deployments -
     * is exactly what used to make the pause fall off.
     */
    public function testPausingShutsTheWorkspaceDownAndSaysSo(): void {
        $workspace = Fixtures::workspace(['status' => \WorkspaceStatusTypes::Active]);

        $body = $this->decode($this->signedIn()->put("workspaces/{$workspace->id}/pause"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame(\WorkspaceStatusTypes::Paused, $this->reload($workspace)->status);
        $this->assertSame(1, (int) $this->reload($workspace)->is_paused);
    }

    /**
     * The three ways it used to fall off, all at once: the status is recomputed whenever
     * anything happens to a deployment, and a paused workspace must come out of that still
     * paused.
     */
    public function testAPauseSurvivesEveryRecompute(): void {
        $deployment = Fixtures::deployableDeployment();
        $workspace = new Workspace();
        $workspace->find($deployment->workspace_id);
        $workspace->pause();

        foreach ([\DeploymentStatusTypes::Deploying, \DeploymentStatusTypes::Error, \DeploymentStatusTypes::Active] as $status) {
            $deployment->status = $status;
            $deployment->save();
            $this->reload($workspace)->checkStatus();

            $this->assertSame(
                \WorkspaceStatusTypes::Paused,
                $this->reload($workspace)->status,
                "a deployment in {$status} took the pause off"
            );
        }
    }

    /**
     * Resuming takes the pause off and leaves the workspace shut down: bringing it back up
     * is Deploy, and that is a decision of its own.
     */
    public function testResumingTakesThePauseOffAndLeavesItTerminated(): void {
        $workspace = Fixtures::workspace(['status' => \WorkspaceStatusTypes::Active]);
        $this->signedIn()->put("workspaces/{$workspace->id}/pause");

        $body = $this->decode($this->signedIn()->put("workspaces/{$workspace->id}/resume"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame(0, (int) $this->reload($workspace)->is_paused);
        $this->assertNotSame(\WorkspaceStatusTypes::Paused, $this->reload($workspace)->status);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('thePauseEndpoints')]
    public function testAnUnknownWorkspaceIsRefused(string $path): void {
        $body = $this->decode($this->signedIn()->put("workspaces/999999/{$path}"));

        $this->assertSame('unknown workspace', $body['error'] ?? null);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function thePauseEndpoints(): array {
        return ['pause' => ['pause'], 'resume' => ['resume']];
    }

    // </editor-fold>

    /**
     * A route in the table pointing at a method that no longer exists.
     *
     * `requestSupportLogin` was registered by the initial migration in 2023 and the
     * controller method is gone, so every installation carries a route to nothing. It is a
     * small thing on its own and a clear example of authorization living in the table: the
     * routes are data, written once by a migration, and nothing keeps them in step with the
     * code.
     */
    public function testTheSupportLoginRouteStillPointsAtAMethodThatIsGone(): void {
        $this->assertFalse(
            method_exists(\App\Controllers\Workspaces::class, 'requestSupportLogin'),
            'the method is back - remove this test'
        );

        $this->assertSame(
            1,
            $this->db->table('api_routes')->like('from', 'requestSupportLogin')->countAllResults()
        );
    }

    // <editor-fold desc="Helpers">

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    /**
     * @param array<mixed> $values
     */
    private function putValues(string $path, array $values): void {
        $this->withBodyFormat('json')->signedIn()->put($path, ['values' => $values]);
    }

    private function reload(Workspace $workspace): Workspace {
        $fresh = new Workspace();
        $fresh->find($workspace->id);

        return $fresh;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function labelsOf(Workspace $workspace): array {
        return db_connect()->table('labels_workspaces')
            ->select('labels.name, labels.value')
            ->join('labels', 'labels.id = labels_workspaces.label_id')
            ->where('labels_workspaces.workspace_id', $workspace->id)
            ->get()->getResultArray();
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function create(int $packageId, array $parameters): array {
        return $this->decode($this->signedIn()->post(
            'workspaces/create?' . http_build_query(['deploymentPackageId' => $packageId] + $parameters)
        ));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function createExpectingFailure(int $packageId, array $parameters): string {
        $body = $this->create($packageId, $parameters);

        $this->assertSame('ERROR', $body['status'], 'expected this to be refused');

        return $body['error'];
    }

    private function createWorkspace(int $packageId, int $domainId): Workspace {
        $body = $this->create($packageId, [
            'name' => 'Acme', 'namespace' => 'acme',
            'domainId' => $domainId, 'subdomain' => 'acme',
        ]);

        $workspace = new Workspace();
        $workspace->find($body['resource']['id']);

        return $workspace;
    }

    /**
     * @return \App\Entities\Deployment[]
     */
    private function deploymentsOf(int $workspaceId): array {
        return (new DeploymentModel())
            ->where('workspace_id', $workspaceId)
            ->orderBy('id', 'asc')
            ->find()
            ->all ?? [];
    }

    // </editor-fold>

}
