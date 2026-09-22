<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\Workspace;
use App\Fixtures;
use App\Tests\Fakes\FakeIntegrations;

/**
 * Picking a version for a deployment the template did not pin.
 *
 * A template entry usually names a default version. When it does not, the version is
 * whatever the container registry reports last - which is a network call from inside
 * `createDeploymentFromTemplate()`, and the reason every other fixture in this suite sets
 * a default version. With the registry faked, the branch can finally be looked at.
 *
 * It is worth looking at: this is the version a customer's workspace starts on.
 */
class CreateDeploymentFromTemplateTest extends DatabaseTestCase {

    public function tearDown(): void {
        FakeIntegrations::uninstall();

        parent::tearDown();
    }

    public function testTheTemplateDefaultWinsWhenThereIsOne(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = ['9.9.9'];

        $deployment = $this->deploymentFromTemplate(['default_version' => '1.2.3']);

        $this->assertSame('1.2.3', $deployment->version);
    }

    /**
     * No default, so the registry decides. Note which one it picks: the **last** entry the
     * registry returned, not the highest version number. A registry that lists tags oldest
     * first gives the newest; one that does not gives whatever happens to be last.
     */
    public function testWithoutADefaultTheLastTagFromTheRegistryIsUsed(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = ['1.0.0', '1.1.0', '2.0.0'];

        $deployment = $this->deploymentFromTemplate(['default_version' => '']);

        $this->assertSame('2.0.0', $deployment->version);
    }

    /**
     * Order in, order out - there is no sorting. Pinned because the method reads as though
     * it finds the newest, and it does not.
     */
    public function testTheTagsAreNotSorted(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = ['2.0.0', '1.1.0', '1.0.0'];

        $deployment = $this->deploymentFromTemplate(['default_version' => '']);

        $this->assertSame('1.0.0', $deployment->version);
    }

    /**
     * A moving tag is not a version. `latest` would pin the workspace to whatever that
     * points at today, so anything containing the word is dropped first.
     */
    public function testAnythingCalledLatestIsIgnored(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = ['1.0.0', '2.0.0', 'latest'];

        $deployment = $this->deploymentFromTemplate(['default_version' => '']);

        $this->assertSame('2.0.0', $deployment->version);
    }

    public function testTheMatchIsOnTheWordAnywhereInTheTag(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = ['1.0.0', 'latest-stable', 'not-latest'];

        $deployment = $this->deploymentFromTemplate(['default_version' => '']);

        $this->assertSame('1.0.0', $deployment->version);
    }

    /**
     * An explicit version beats both the template default and the registry, which is how a
     * workspace is onboarded onto something other than the newest thing.
     */
    public function testAnExplicitVersionBeatsTheRegistry(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = ['9.9.9'];

        $deployment = $this->deploymentFromTemplate(['default_version' => ''], '4.5.6');

        $this->assertSame('4.5.6', $deployment->version);
    }

    /**
     * A registry that refuses gives no version, as it did before a refusal started to
     * throw. The deployment is still made, so the rest of the template is not left half
     * created.
     */
    public function testARegistryThatRefusesGivesNoVersion(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = [];
        $fakes->failTagsWith = new \Exception('Harbor answered 401: unauthorized');

        $deployment = $this->deploymentFromTemplate(['default_version' => '']);

        $this->assertTrue($deployment->exists());
        $this->assertEmpty($deployment->version);
    }

    /**
     * @param array<string, mixed> $templateSpecification
     */
    /**
     * The template's variables are copied onto the deployment, and a secret one stays secret.
     */
    public function testTheTemplatesVariablesComeAlongAndASecretStaysSecret(): void {
        FakeIntegrations::install();
        $this->templateVariables = [['API_TOKEN', 'token-value', true], ['LOG_LEVEL', 'debug', false]];

        $deployment = $this->deploymentFromTemplate([]);

        $variables = [];
        foreach ((new \App\Models\EnvironmentVariableModel())->where('deployment_id', $deployment->id)->find() as $variable) {
            $variables[$variable->name] = [$variable->value, (bool) $variable->is_secret];
        }
        $this->assertSame(['API_TOKEN' => ['token-value', true], 'LOG_LEVEL' => ['debug', false]], $variables);
    }

    /** @var list<array{0: string, 1: string, 2: bool}> the template's variables, as name, value, secret */
    private array $templateVariables = [];

    private function deploymentFromTemplate(array $templateSpecification, ?string $version = null): \App\Entities\Deployment {
        $image = Fixtures::containerImage(['container_registry_id' => Fixtures::containerRegistry()->id]);
        $specification = Fixtures::deploymentSpecification(['name' => 'api', 'container_image_id' => $image->id]);
        $template = Fixtures::workspaceTemplate();
        foreach ($this->templateVariables as [$name, $value, $isSecret]) {
            $variable = \App\Entities\WorkspaceTemplateEnvironmentVariable::Create($name, $value, $isSecret);
            $variable->workspace_template_id = $template->id;
            $variable->save();
        }
        Fixtures::templateSpecification(array_merge([
            'workspace_template_id' => $template->id,
            'deployment_specification_id' => $specification->id,
        ], $templateSpecification));

        $workspace = Fixtures::workspace(['workspace_template_id' => $template->id, 'namespace' => 'test']);
        $reloaded = new Workspace();
        $reloaded->find($workspace->id);

        return $reloaded->addDeployment($specification, null, $version);
    }

}
