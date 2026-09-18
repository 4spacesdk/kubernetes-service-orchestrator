<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\ManifestTestCase;

/**
 * The namespace every other resource is created in.
 *
 * The shortest manifest in the system and the first step in every list, which is what
 * makes the one decision in it worth pinning: the name comes from the workspace, not from
 * the deployment, although both entities carry a namespace column.
 */
class NamespaceStepTest extends ManifestTestCase {

    /**
     * A namespace is cluster-scoped, so it has a name and nothing else.
     */
    public function testNamespaceIsNamedAfterTheWorkspace(): void {
        $deployment = $this->deploymentInWorkspaceNamespace('customer-a');

        $manifest = $this->manifest(NamespaceStep::class, $deployment);

        $this->assertSame('customer-a', $manifest['metadata']['name']);
        $this->assertArrayNotHasKey('namespace', $manifest['metadata']);
    }

    /**
     * The two columns can disagree, and the workspace wins. Every other step reads
     * `$deployment->namespace`, so a deployment whose namespace has drifted from its
     * workspace would be created somewhere other than the namespace built here.
     */
    public function testWorkspaceWinsOverTheDeploymentsOwnNamespace(): void {
        $deployment = $this->deploymentInWorkspaceNamespace('from-workspace', 'from-deployment');

        $this->assertSame(
            'from-workspace',
            $this->manifest(NamespaceStep::class, $deployment)['metadata']['name']
        );
    }

    /**
     * The only step at workspace level, and the only one that refuses to terminate: every
     * customer resource lives inside the namespace, so removing it is done by hand.
     */
    public function testTheStepIsWiredInAtTheWorkspaceLevel(): void {
        $step = new NamespaceStep();
        $deployment = $this->deploymentInWorkspaceNamespace('customer-a');

        $this->assertSame(DeploymentSteps::Namespace, $step->getIdentifier());
        $this->assertSame(DeploymentStepLevels::Workspace, $step->getLevel());
        $this->assertSame('Namespace', $step->getName());
        $this->assertSame([], $step->getTriggers());
        $this->assertSame(DeploymentStepHelper::Namespace_Found, $step->getSuccessStatus($deployment));
        $this->assertFalse($step->hasTerminateCommand());

        $this->assertFalse($step->hasKubernetesEvents());
        $this->assertSame([], $step->getKubernetesEvents($deployment));
        $this->assertTrue($step->hasKubernetesStatus());
    }

    /**
     * A workspace with no namespace column would have every later step apply into the
     * cluster's `default` namespace, which is the one place nothing of ours belongs.
     */
    public function testDeployIsRefusedWhenTheWorkspaceHasNoNamespace(): void {
        $deployment = $this->deploymentInWorkspaceNamespace('');

        $this->assertSame(
            'Missing workspace namespace',
            (new NamespaceStep())->validateDeployCommand($deployment)
        );
    }

    public function testDeployIsAllowedForAWorkspaceWithANamespace(): void {
        $deployment = $this->deploymentInWorkspaceNamespace('customer-a');

        $this->assertNull((new NamespaceStep())->validateDeployCommand($deployment));
    }

    public function testDeploymentWithoutAWorkspaceIsRejected(): void {
        $deployment = Fixtures::deployment(['workspace_id' => 0]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This step require workspace');

        $this->manifest(NamespaceStep::class, $deployment);
    }

    public function testDeployIsRefusedWithoutAWorkspace(): void {
        $deployment = Fixtures::deployment(['workspace_id' => 0]);

        $this->assertSame('Missing workspace', (new NamespaceStep())->validateDeployCommand($deployment));
    }

    /**
     * The only step whose `getStatus()` answers a third thing. Everything that can go wrong
     * before the cluster is even asked - no workspace, no credentials configured - comes
     * back as Error rather than as NotFound, because NotFound would read as "deploy this"
     * and the deploy would fail for the same reason.
     */
    public function testAStatusThatCannotBeAskedForIsAnErrorRatherThanNotFound(): void {
        $deployment = Fixtures::deployment(['workspace_id' => 0]);

        $this->assertSame(
            DeploymentStepHelper::Namespace_Error,
            (new NamespaceStep())->getStatus($deployment)
        );
    }

    private function deploymentInWorkspaceNamespace(string $workspaceNamespace, ?string $deploymentNamespace = null): Deployment {
        $workspace = Fixtures::workspace(['namespace' => $workspaceNamespace]);

        return Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'namespace' => $deploymentNamespace ?? $workspaceNamespace,
        ]);
    }

}
