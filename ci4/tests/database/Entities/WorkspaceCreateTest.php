<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\Workspace;
use App\Entities\WorkspaceTemplate;
use App\Exceptions\ValidationException;
use App\Fixtures;

/**
 * Creating a workspace from a workspace template: which project it lands in, and the status it
 * starts with.
 *
 * Created from a project's list, it used to land in the project its template is for - somewhere
 * else than where it was made. And it had no status, so no status filter let it in, and it was
 * gone from the list it was just created on.
 */
class WorkspaceCreateTest extends DatabaseTestCase {

    // <editor-fold desc="Project">

    public function testItLandsInTheProjectItIsCreatedFrom(): void {
        $template = Fixtures::workspaceTemplate(['project_id' => Fixtures::project(['name' => 'the-templates'])->id]);
        $here = Fixtures::project(['name' => 'here']);

        $workspace = $this->create($template, projectId: (int) $here->id);

        $this->assertSame((int) $here->id, (int) $this->stored($workspace)->project_id);
    }

    /** From the list of those in no project. */
    public function testWithNoProjectItLandsInNone(): void {
        $template = Fixtures::workspaceTemplate(['project_id' => Fixtures::project()->id]);

        $workspace = $this->create($template, projectId: 0);

        $this->assertNull($this->stored($workspace)->project_id);
    }

    /** From the list of every workspace, which is no project's. */
    public function testLeftOutItLandsInTheTemplatesProject(): void {
        $project = Fixtures::project();
        $template = Fixtures::workspaceTemplate(['project_id' => $project->id]);

        $workspace = $this->create($template);

        $this->assertSame((int) $project->id, (int) $this->stored($workspace)->project_id);
    }

    public function testAProjectThatIsNotThereIsRefused(): void {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Project not found');

        $this->create(Fixtures::workspaceTemplate(), projectId: 999999);
    }

    // </editor-fold>

    // <editor-fold desc="Status">

    /** Its deployments are drafts, and so is it - not without a status. */
    public function testItStartsAsADraft(): void {
        $template = Fixtures::workspaceTemplate();
        $specification = Fixtures::deploymentSpecification(['name' => 'api']);
        Fixtures::templateSpecification([
            'workspace_template_id' => $template->id,
            'deployment_specification_id' => $specification->id,
        ]);

        $workspace = $this->create($template);

        $this->assertSame(\WorkspaceStatusTypes::Draft, $this->stored($workspace)->status);
    }

    public function testWithNoDeploymentsItStartsAsADraftToo(): void {
        $workspace = $this->create(Fixtures::workspaceTemplate());

        $this->assertSame(\WorkspaceStatusTypes::Draft, $this->stored($workspace)->status);
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    private function create(WorkspaceTemplate $template, ?int $projectId = null): Workspace {
        return Workspace::Create($template, 'Acme', 'test-acme', (int) Fixtures::domain()->id, 'acme', $projectId);
    }

    private function stored(Workspace $workspace): Workspace {
        $stored = new Workspace();
        $stored->find($workspace->id);
        return $stored;
    }

    // </editor-fold>

}
