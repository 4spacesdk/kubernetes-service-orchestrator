<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;

/**
 * Projects: workspaces divided up, users joined to the ones relevant to them, and the lists
 * narrowed to a project - "none" included, which a plain `project_id` filter cannot say.
 */
class ProjectsApiTest extends ControllerTestCase {

    // <editor-fold desc="Members">

    /**
     * The members are set as a whole: those in the list are joined, the rest are not - and
     * the same list twice is the same members, not two of each.
     */
    public function testTheMembersOfAProjectAreSetAsAWhole(): void {
        $project = Fixtures::project();
        $a = Fixtures::user(['username' => 'a@test.example.org']);
        $b = Fixtures::user(['username' => 'b@test.example.org']);

        $this->putJson("projects/{$project->id}/users", [$a->id, $b->id]);
        $this->putJson("projects/{$project->id}/users", [$b->id, $b->id]);

        $this->assertSame([(int) $b->id], $this->membersOf($project->id));
    }

    public function testAnIdThatIsNoUserIsLeftOut(): void {
        $project = Fixtures::project();

        $this->putJson("projects/{$project->id}/users", [999999]);

        $this->assertSame([], $this->membersOf($project->id));
    }

    public function testAUsersProjectsAreSetFromTheUser(): void {
        $customers = Fixtures::project(['name' => 'Customers']);
        $tools = Fixtures::project(['name' => 'Tools']);
        $user = Fixtures::user(['username' => 'c@test.example.org']);

        $this->putJson("users/{$user->id}/projects", [$customers->id, $tools->id]);
        $this->putJson("users/{$user->id}/projects", [$tools->id]);

        $this->assertSame([], $this->membersOf($customers->id));
        $this->assertSame([(int) $user->id], $this->membersOf($tools->id));
    }

    /**
     * What the project picker starts on.
     */
    public function testTheSignedInUserComesWithTheirProjects(): void {
        $project = Fixtures::project(['name' => 'Customers']);
        $this->db->table('projects_users')->insert(['project_id' => $project->id, 'user_id' => $this->signedInUserId()]);

        $body = $this->decode($this->signedIn()->get('users/me'));

        $this->assertSame(['Customers'], array_column($body['resource']['projects'], 'name'));
    }

    public function testMembersOfAnUnknownProjectAreRefused(): void {
        $body = $this->decode($this->withBodyFormat('json')->signedIn()->put('projects/999999/users', ['values' => []]));

        $this->assertSame('unknown project', $body['error'] ?? null);
    }

    // </editor-fold>

    // <editor-fold desc="Moving a workspace">

    public function testAWorkspaceIsMovedToAnotherProjectAndOutOfAny(): void {
        $project = Fixtures::project();
        $workspace = Fixtures::workspace();

        $this->signedIn()->put("workspaces/{$workspace->id}/projectId?value={$project->id}");
        $this->assertSame((int) $project->id, (int) $this->projectOf($workspace->id));

        $this->signedIn()->put("workspaces/{$workspace->id}/projectId?value=0");
        $this->assertNull($this->projectOf($workspace->id));
    }

    public function testMovingAWorkspaceToAProjectThatDoesNotExistIsRefused(): void {
        $workspace = Fixtures::workspace();

        $body = $this->decode($this->signedIn()->put("workspaces/{$workspace->id}/projectId?value=999999"));

        $this->assertSame('unknown project', $body['error'] ?? null);
        $this->assertNull($this->projectOf($workspace->id));
    }

    // </editor-fold>

    // <editor-fold desc="The lists, narrowed to a project">

    public function testWorkspacesAreNarrowedToAProjectAndToNone(): void {
        [$customers, $tools] = [Fixtures::project(['name' => 'Customers']), Fixtures::project(['name' => 'Tools'])];
        Fixtures::workspace(['name_readable' => 'acme', 'namespace' => 'acme', 'project_id' => $customers->id]);
        Fixtures::workspace(['name_readable' => 'build', 'namespace' => 'build', 'project_id' => $tools->id]);
        Fixtures::workspace(['name_readable' => 'loose', 'namespace' => 'loose']);

        $this->assertSame(['acme'], $this->namesOf("workspaces?filter=project:[{$customers->id}]"));
        $this->assertSame(['loose'], $this->namesOf('workspaces?filter=project:[none]'));
        $this->assertSame(['acme', 'loose'], $this->namesOf("workspaces?filter=project:[{$customers->id},none]"));
        $this->assertSame(['acme', 'build', 'loose'], $this->namesOf('workspaces?filter=project:[]'), 'nothing chosen is every project');
    }

    /**
     * A deployment is in the project of its workspace - and one with no workspace is in none.
     */
    public function testDeploymentsAreNarrowedThroughTheirWorkspace(): void {
        $customers = Fixtures::project(['name' => 'Customers']);
        $acme = Fixtures::workspace(['name_readable' => 'acme', 'namespace' => 'acme', 'project_id' => $customers->id]);
        Fixtures::deployment(['name' => 'in-customers', 'namespace' => 'acme', 'workspace_id' => $acme->id]);
        Fixtures::deployment(['name' => 'standalone', 'namespace' => 'infra']);

        $this->assertSame(['in-customers'], $this->namesOf("deployments?filter=project:[{$customers->id}]"));
        $this->assertSame(['standalone'], $this->namesOf('deployments?filter=project:[none]'));
    }

    public function testAutoUpdatesAreNarrowedThroughTheirDeploymentsWorkspace(): void {
        $customers = Fixtures::project(['name' => 'Customers']);
        $acme = Fixtures::workspace(['name_readable' => 'acme', 'namespace' => 'acme', 'project_id' => $customers->id]);
        $inProject = Fixtures::deployment(['name' => 'in-customers', 'namespace' => 'acme', 'workspace_id' => $acme->id]);
        $standalone = Fixtures::deployment(['name' => 'standalone', 'namespace' => 'infra']);
        foreach ([$inProject, $standalone] as $deployment) {
            $this->db->table('auto_updates')->insert([
                'deployment_id' => $deployment->id,
                'image' => 'registry.example.org/test/app',
                'previous_tag' => '1.0.0',
                'next_tag' => '1.1.0',
                'is_approved' => 0,
                'approved_date' => '',
            ]);
        }

        $body = $this->decode($this->signedIn()->get("auto_updates?filter=project:[{$customers->id}]"));

        $this->assertSame([(int) $inProject->id], array_map('intval', array_column($body['resources'], 'deployment_id')));
    }

    // </editor-fold>

    // <editor-fold desc="Helpers">

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    /**
     * @param int[] $ids
     */
    private function putJson(string $path, array $ids): void {
        $body = $this->decode($this->withBodyFormat('json')->signedIn()->put($path, ['values' => $ids]));
        $this->assertSame('OK', $body['status'], json_encode($body));
    }

    /**
     * @return int[]
     */
    private function membersOf(int $projectId): array {
        return array_map('intval', array_column(
            $this->db->table('projects_users')->where('project_id', $projectId)->orderBy('user_id')->get()->getResultArray(),
            'user_id'
        ));
    }

    private function projectOf(int $workspaceId): ?int {
        $value = $this->db->table('workspaces')->where('id', $workspaceId)->get()->getRow()->project_id;

        return $value === null ? null : (int) $value;
    }

    /**
     * @return string[]
     */
    private function namesOf(string $path): array {
        $body = $this->decode($this->signedIn()->get($path));
        $names = array_map(fn ($row) => $row['name_readable'] ?? $row['name'], $body['resources'] ?? []);
        sort($names);

        return $names;
    }

    // </editor-fold>

}
