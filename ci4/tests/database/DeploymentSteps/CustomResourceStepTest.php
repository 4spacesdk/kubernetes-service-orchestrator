<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\CustomResourceStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use App\ManifestTestCase;

/**
 * The escape hatch: a manifest typed by hand into the specification.
 *
 * Every other step builds its resource from named fields, so the shape is ours to get
 * right. Here the shape is the user's, and the step only runs variables through it and
 * parses the YAML. That makes the failure modes the interesting part - what a workspace
 * sees when the text is wrong.
 */
class CustomResourceStepTest extends ManifestTestCase {

    public function testYamlBecomesTheManifest(): void {
        $deployment = $this->deploymentWithCustomResource(
            "apiVersion: example.org/v1\n" .
            "kind: Thing\n" .
            "metadata:\n" .
            "  name: a-thing\n" .
            "spec:\n" .
            "  size: 3\n"
        );

        $manifest = $this->build($deployment);

        $this->assertSame('example.org/v1', $manifest['apiVersion']);
        $this->assertSame('Thing', $manifest['kind']);
        $this->assertSame('a-thing', $manifest['metadata']['name']);
        $this->assertSame(3, $manifest['spec']['size']);
    }

    /**
     * Variables are applied to the whole document before it is parsed, which is how one
     * specification can produce a resource per workspace.
     */
    public function testVariablesAreAppliedBeforeTheYamlIsParsed(): void {
        $deployment = $this->deploymentWithCustomResource(
            "apiVersion: example.org/v1\n" .
            "kind: Thing\n" .
            "metadata:\n" .
            '  name: ${deployment.name}' . "\n" .
            '  namespace: ${namespace}' . "\n"
        );

        $manifest = $this->build($deployment);

        $this->assertSame($deployment->name, $manifest['metadata']['name']);
        $this->assertSame($deployment->namespace, $manifest['metadata']['namespace']);
    }

    /**
     * The resource gets the passwords - it is what the manifest asks for - but the preview,
     * shown in the UI, does not.
     */
    public function testThePreviewHidesThePasswordsTheResourceGets(): void {
        $deployment = $this->deploymentWithCustomResource(
            "apiVersion: example.org/v1\n" .
            "kind: Thing\n" .
            "metadata:\n" .
            "  name: thing\n" .
            "spec:\n" .
            '  dsn: mysql://${database.user}:${database.pass}@${database.host}/app' . "\n" .
            '  mail: ${emailService.pass}' . "\n"
        );
        $deployment->database_pass = 'db-secret';
        $deployment->save();

        $parse = new \ReflectionMethod(CustomResourceStep::class, 'parseManifest');
        $applied = $parse->invoke(new CustomResourceStep(), $deployment);
        $previewed = $parse->invoke(new CustomResourceStep(), $deployment, true);

        $this->assertStringContainsString('db-secret', $applied['spec']['dsn']);
        $this->assertStringNotContainsString('db-secret', json_encode($previewed));
        $this->assertStringContainsString(':(hidden)@', $previewed['spec']['dsn']);
        $this->assertSame('(hidden)', $previewed['spec']['mail']);
    }

    /**
     * Nothing anchors name or namespace to the deployment, so a hand-written resource can
     * name itself anything and live anywhere - including in another workspace's namespace.
     * That is the cost of the escape hatch, and it is worth stating out loud.
     */
    public function testNothingForcesTheResourceIntoTheDeploymentsNamespace(): void {
        $deployment = $this->deploymentWithCustomResource(
            "apiVersion: example.org/v1\n" .
            "kind: Thing\n" .
            "metadata:\n" .
            "  name: somewhere-else\n" .
            "  namespace: another-workspace\n"
        );

        $this->assertSame('another-workspace', $this->build($deployment)['metadata']['namespace']);
    }

    /**
     * An empty field used to parse to null and die in the constructor on a raw TypeError,
     * with nothing pointing at the specification. Now the deploy is refused with a message,
     * and a field of only spaces counts as empty.
     */
    public function testAnEmptyCustomResourceIsRefusedWithAMessage(): void {
        $step = new CustomResourceStep();

        $this->assertSame('Missing custom resource', $step->validateDeployCommand($this->deploymentWithCustomResource('')));
        $this->assertSame('Missing custom resource', $step->validateDeployCommand($this->deploymentWithCustomResource("  \n ")));
    }

    /**
     * Malformed YAML failed loudly, but as the parser's own warning about `yaml_parse`
     * rather than as something about the field. The parser's reason is kept - it says which
     * line - behind a sentence that names what is wrong.
     */
    public function testMalformedYamlIsRefusedWithTheParsersReason(): void {
        $deployment = $this->deploymentWithCustomResource("foo: [1, 2\n  bar: :::");

        $message = (new CustomResourceStep())->validateDeployCommand($deployment);

        $this->assertStringStartsWith('The custom resource is not valid YAML', $message);
        $this->assertStringContainsString('yaml_parse', $message);
    }

    /**
     * YAML that parses to something other than a mapping - a bare string, say - would reach
     * the constructor, which wants an array.
     */
    public function testYamlThatIsNotAMappingIsRefused(): void {
        $message = (new CustomResourceStep())->validateDeployCommand($this->deploymentWithCustomResource('just a string'));

        $this->assertSame('The custom resource is not valid YAML', $message);
    }

    /**
     * Building it still throws rather than answering with something half-made: the deploy
     * is refused before this point, and preview and status have their own guards.
     */
    public function testBuildingAnEmptyCustomResourceThrows(): void {
        $deployment = $this->deploymentWithCustomResource('');

        $this->expectExceptionMessage('Missing custom resource');

        $this->build($deployment);
    }

    /**
     * What the step tells the deployment engine about itself.
     *
     * Unlike the policy steps this one has a trigger: editing the pasted manifest on the
     * specification has to redeploy it, or the text in the database and the resource in the
     * cluster drift apart with nothing saying so. It is also the only step here that offers
     * the events and status panels, because the resource is a real one the api server
     * reports on.
     */
    public function testTheStepDeclaresItselfToTheDeploymentEngine(): void {
        $step = new CustomResourceStep();

        $this->assertSame(DeploymentSteps::CustomResource, $step->getIdentifier());
        $this->assertSame(DeploymentStepLevels::Deployment, $step->getLevel());
        $this->assertSame('Custom Resource', $step->getName());
        $this->assertSame(
            [DeploymentStepTriggers::Deployment_CustomResource_Updated],
            $step->getTriggers(),
            'editing the manifest is what redeploys it'
        );

        $this->assertTrue($step->hasPreviewCommand());
        $this->assertTrue($step->hasStatusCommand());
        $this->assertTrue($step->hasDeployCommand());
        $this->assertTrue($step->hasTerminateCommand());
        $this->assertTrue($step->hasKubernetesEvents());
        $this->assertTrue($step->hasKubernetesStatus());
    }

    /**
     * One value, and no "not expected" branch: a specification that uses this step always
     * expects its resource to be there. A specification that does not use it never reaches
     * the step, because `validateDeployCommand()` refuses an empty manifest first.
     */
    public function testSuccessMeansTheResourceIsFound(): void {
        $this->assertSame(
            DeploymentStepHelper::CustomResource_Found,
            (new CustomResourceStep())->getSuccessStatus($this->deploymentWithCustomResource('kind: Thing'))
        );
    }

    /**
     * The namespace is checked before the manifest is, even though a manifest is free to
     * name a namespace of its own - the deployment's namespace is only the fallback. A
     * deployment without one is not deployable at all, which is the state it is refused in.
     */
    public function testADeploymentWithoutANamespaceIsRefused(): void {
        $deployment = $this->deploymentWithCustomResource("apiVersion: v1\nkind: ConfigMap\n");
        $deployment->namespace = '';

        $this->assertSame('Missing namespace', (new CustomResourceStep())->validateDeployCommand($deployment));
    }

    public function testADeploymentWithBothIsAccepted(): void {
        $deployment = $this->deploymentWithCustomResource("apiVersion: v1\nkind: ConfigMap\n");

        $this->assertNull((new CustomResourceStep())->validateDeployCommand($deployment));
    }

    private function deploymentWithCustomResource(string $yaml): Deployment {
        return Fixtures::deployableDeployment(
            [],
            ['workload_type' => \WorkloadTypes::CustomResource, 'custom_resource' => $yaml]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Deployment $deployment): array {
        return $this->manifest(CustomResourceStep::class, $deployment);
    }

}
