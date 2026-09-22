<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\DeploymentSpecification;
use App\Entities\Workspace;
use App\Exceptions\ValidationException;
use App\Fixtures;

/**
 * Adding a deployment to a workspace that already exists.
 *
 * There are two ways in, and which one is taken is decided silently: if the specification
 * belongs to the workspace's own workspace template, the deployment inherits the template's
 * defaults. If it does not, it is built from the specification alone and gets none of them.
 * Both produce a deployment and neither says which path it took, so the difference only
 * shows up later as a workspace running on one replica when it was supposed to have three.
 */
class WorkspaceAddDeploymentTest extends DatabaseTestCase {

    /**
     * In the template: the defaults come with it.
     */
    public function testASpecificationFromTheTemplateBringsTheTemplateDefaults(): void {
        $template = Fixtures::workspaceTemplate();
        $specification = Fixtures::deploymentSpecification(['name' => 'api']);
        Fixtures::templateSpecification([
            'workspace_template_id' => $template->id,
            'deployment_specification_id' => $specification->id,
            'default_version' => '3.1.4',
            'default_replicas' => 3,
            'default_cpu_limit' => 500,
        ]);
        $workspace = $this->workspaceOnTemplate($template->id);

        $deployment = $workspace->addDeployment($specification, null, null);

        $this->assertSame('3.1.4', $deployment->version);
        $this->assertSame(3, (int) $deployment->replicas);
        $this->assertSame(500, (int) $deployment->cpu_limit);
    }

    /**
     * Not in the template: the same call gives a bare deployment. No version, and the
     * replica count is the one `Deployment::Prepare()` hardcodes.
     */
    public function testASpecificationOutsideTheTemplateGetsNoDefaults(): void {
        $template = Fixtures::workspaceTemplate();
        $workspace = $this->workspaceOnTemplate($template->id);
        $specification = Fixtures::deploymentSpecification(['name' => 'not-in-the-template']);

        $deployment = $workspace->addDeployment($specification, null, null);

        $this->assertSame('', (string) $deployment->version);
        $this->assertSame(1, (int) $deployment->replicas);
        $this->assertNull($deployment->cpu_limit);
    }

    /**
     * An explicit version wins over the template's default, which is how a workspace is
     * pinned to something other than what the template says.
     */
    public function testAnExplicitVersionOverridesTheTemplateDefault(): void {
        $template = Fixtures::workspaceTemplate();
        $specification = Fixtures::deploymentSpecification(['name' => 'api']);
        Fixtures::templateSpecification([
            'workspace_template_id' => $template->id,
            'deployment_specification_id' => $specification->id,
            'default_version' => '3.1.4',
        ]);
        $workspace = $this->workspaceOnTemplate($template->id);

        $deployment = $workspace->addDeployment($specification, null, '9.9.9');

        $this->assertSame('9.9.9', $deployment->version);
    }

    public function testTheDeploymentTakesItsNamespaceAndWorkspaceFromTheWorkspace(): void {
        $workspace = $this->workspaceOnTemplate(Fixtures::workspaceTemplate()->id, ['namespace' => 'acme']);
        $specification = Fixtures::deploymentSpecification(['name' => 'api']);

        $deployment = $workspace->addDeployment($specification, null, null);

        $this->assertSame('acme', $deployment->namespace);
        $this->assertSame((int) $workspace->id, (int) $deployment->workspace_id);
        $this->assertSame(\DeploymentStatusTypes::Draft, $deployment->status);
    }

    /**
     * Without a name of its own the deployment takes the specification's, which is why
     * most deployments are called the same as the thing they run.
     */
    public function testTheNameFallsBackToTheSpecificationName(): void {
        $workspace = $this->workspaceOnTemplate(Fixtures::workspaceTemplate()->id);
        $specification = Fixtures::deploymentSpecification(['name' => 'from-the-specification']);

        $named = $workspace->addDeployment($specification, 'chosen-name', null);
        $unnamed = $workspace->addDeployment(Fixtures::deploymentSpecification(['name' => 'second-spec']), null, null);

        $this->assertSame('chosen-name', $named->name);
        $this->assertSame('second-spec', $unnamed->name);
    }

    /**
     * The image and its pull policy come from the specification's container image, so a
     * deployment knows what to run before anyone picks a version.
     */
    public function testTheImageComesFromTheSpecificationsContainerImage(): void {
        $workspace = $this->workspaceOnTemplate(Fixtures::workspaceTemplate()->id);
        $containerImage = Fixtures::containerImage([
            'url' => 'registry.example.org/team/api',
            'default_image_pull_policy' => \ImagePullPolicies::Always,
        ]);
        $specification = Fixtures::deploymentSpecification([
            'name' => 'api',
            'container_image_id' => $containerImage->id,
        ]);

        $deployment = $workspace->addDeployment($specification, null, null);

        $this->assertSame('registry.example.org/team/api', $deployment->image);
        $this->assertSame(\ImagePullPolicies::Always, $deployment->image_pull_policy);
    }

    /**
     * The workspace's database service is handed down only to specifications that asked
     * for a database. One that did not is left without, rather than holding a connection
     * it never uses.
     */
    public function testTheDatabaseServiceIsInheritedOnlyWhenTheSpecificationWantsOne(): void {
        $databaseService = Fixtures::databaseService();
        $workspace = $this->workspaceOnTemplate(
            Fixtures::workspaceTemplate()->id,
            ['database_service_id' => $databaseService->id]
        );

        $withDatabase = $workspace->addDeployment(
            Fixtures::deploymentSpecification(['name' => 'with-db', 'enable_database' => true]),
            null,
            null
        );
        $withoutDatabase = $workspace->addDeployment(
            Fixtures::deploymentSpecification(['name' => 'without-db', 'enable_database' => false]),
            null,
            null
        );

        $this->assertSame((int) $databaseService->id, (int) $withDatabase->database_service_id);
        $this->assertNull($withoutDatabase->database_service_id);
    }

    /**
     * Two deployments cannot share a name inside one namespace - they would fight over
     * every Kubernetes object they create.
     */
    public function testANameCannotBeUsedTwiceInTheSameNamespace(): void {
        $workspace = $this->workspaceOnTemplate(Fixtures::workspaceTemplate()->id);
        $specification = Fixtures::deploymentSpecification(['name' => 'api']);
        $workspace->addDeployment($specification, 'taken', null);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Deployment already exists');

        $workspace->addDeployment($specification, 'taken', null);
    }

    public function testADeploymentWithoutANameIsRefused(): void {
        $workspace = $this->workspaceOnTemplate(Fixtures::workspaceTemplate()->id);
        $specification = Fixtures::deploymentSpecification(['name' => '']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Name missing');

        $workspace->addDeployment($specification, null, null);
    }

    public function testAWorkspaceWithoutANamespaceIsRefused(): void {
        $workspace = $this->workspaceOnTemplate(Fixtures::workspaceTemplate()->id, ['namespace' => '']);
        $specification = Fixtures::deploymentSpecification(['name' => 'api']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Namespace missing');

        $workspace->addDeployment($specification, null, null);
    }

    /**
     * `prepareDeploymentFromSpecification()` builds the deployment without writing it -
     * the caller decides whether to keep it. `addDeployment()` is the one that saves.
     */
    public function testPreparingDoesNotWriteWhileAddingDoes(): void {
        $workspace = $this->workspaceOnTemplate(Fixtures::workspaceTemplate()->id);
        $specification = Fixtures::deploymentSpecification(['name' => 'api']);

        $prepared = $workspace->prepareDeploymentFromSpecification($specification, 'not-saved', null);
        $this->assertNull($prepared->id);

        $added = $workspace->addDeployment($specification, 'saved', null);
        $this->assertNotNull($added->id);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function workspaceOnTemplate(int $templateId, array $overrides = []): Workspace {
        return Fixtures::workspace(array_merge([
            'workspace_template_id' => $templateId,
            'namespace' => 'test',
        ], $overrides));
    }

}
