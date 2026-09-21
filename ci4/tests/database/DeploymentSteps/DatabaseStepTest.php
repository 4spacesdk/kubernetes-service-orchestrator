<?php namespace App\Tests\Database\DeploymentSteps;

use App\DatabaseTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\DatabaseStep;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use CodeIgniter\Database\Exceptions\DatabaseException;

/**
 * The one step that creates nothing in Kubernetes.
 *
 * It provisions a database on an external server, so `startDeployCommand()` needs a live
 * connection and is out of reach here. What can be tested is everything around it: how the
 * step reads its own state, what it refuses to run on, and that it will not undo itself.
 */
class DatabaseStepTest extends DatabaseTestCase {

    /**
     * How the step announces itself to the rest of the system. The identifier is what a
     * specification stores and what the UI addresses the step by, so it is a name that
     * cannot be changed without changing stored rows.
     */
    public function testTheStepIsWiredInAtTheDeploymentLevel(): void {
        $step = new DatabaseStep();

        $this->assertSame(DeploymentSteps::Database, $step->getIdentifier());
        $this->assertSame(DeploymentStepLevels::Deployment, $step->getLevel());
        $this->assertSame('Database', $step->getName());
        $this->assertSame([], $step->getTriggers());
        $this->assertSame(
            DeploymentStepHelper::DatabaseStatus_Success,
            $step->getSuccessStatus(Fixtures::deployment())
        );
    }

    /**
     * Every other step answers these with something the cluster holds. This one has nothing
     * in the cluster at all, and the flags are what stop the UI asking for a preview, an
     * event list or a status it could never fill in.
     */
    public function testTheStepOffersNothingKubernetesCanShow(): void {
        $step = new DatabaseStep();
        $deployment = Fixtures::deployment();

        $this->assertFalse($step->hasPreviewCommand());
        $this->assertSame('', $step->getPreview($deployment));

        $this->assertFalse($step->hasKubernetesEvents());
        $this->assertSame([], $step->getKubernetesEvents($deployment));

        $this->assertFalse($step->hasKubernetesStatus());
        $this->assertSame([], $step->getKubernetesStatus($deployment));

        $this->assertFalse($step->hasTerminateCommand());
        $this->assertTrue($step->hasStatusCommand());
        $this->assertTrue($step->hasDeployCommand());
    }

    /**
     * Status is read back off the deployment rather than from the database server, so the
     * three credential fields are the whole record that the work was done.
     */
    public function testAllThreeCredentialsMeanTheDatabaseIsReady(): void {
        $deployment = Fixtures::deployment([
            'database_name' => 'tenant_api',
            'database_user' => 'tenant_api',
            'database_pass' => 'secret',
        ]);

        $this->assertSame(
            DeploymentStepHelper::DatabaseStatus_Success,
            (new DatabaseStep())->getStatus($deployment)
        );
    }

    public function testNoCredentialsMeansNothingHasBeenDone(): void {
        $deployment = Fixtures::deployment([
            'database_name' => '',
            'database_user' => '',
            'database_pass' => '',
        ]);

        $this->assertSame(
            DeploymentStepHelper::DatabaseStatus_NotPerformed,
            (new DatabaseStep())->getStatus($deployment)
        );
    }

    /**
     * A partial set is the interesting case: it means provisioning started and stopped
     * somewhere in the middle. The step reports Failed rather than trying again, because
     * a second run would create a second database and leave the first orphaned.
     */
    public function testAPartialSetOfCredentialsIsAFailure(): void {
        $step = new DatabaseStep();

        foreach (['database_name', 'database_user', 'database_pass'] as $field) {
            $deployment = Fixtures::deployment([
                'database_name' => '',
                'database_user' => '',
                'database_pass' => '',
                $field => 'set',
            ]);

            $this->assertSame(
                DeploymentStepHelper::DatabaseStatus_Failed,
                $step->getStatus($deployment),
                "only {$field} set"
            );
        }
    }

    public function testDeployIsRefusedWithoutADatabaseService(): void {
        $deployment = Fixtures::deployment(['database_service_id' => 0]);

        $this->assertSame(
            'Missing database service',
            (new DatabaseStep())->validateDeployCommand($deployment)
        );
    }

    /**
     * The id can outlive the row it points at, and the message says which of the two
     * problems it is.
     */
    public function testDeployIsRefusedWhenTheServiceIsGone(): void {
        $deployment = Fixtures::deployment(['database_service_id' => 999999]);

        $this->assertSame(
            'Database service no longer exists',
            (new DatabaseStep())->validateDeployCommand($deployment)
        );
    }

    public function testDeployIsAllowedWithAnExistingService(): void {
        $service = Fixtures::databaseService();
        $deployment = Fixtures::deployment(['database_service_id' => $service->id]);

        $this->assertNull((new DatabaseStep())->validateDeployCommand($deployment));
    }

    /**
     * The guard that runs before anything is connected to, and the only part of
     * `startDeployCommand()` that can be reached without a database server.
     *
     * Running twice would create a second database and a second user, and leave the
     * deployment pointing at the new one - so the first one, with the customer's data in
     * it, would still be there and nothing would be using it.
     *
     * It is not an error either. A database outlives a terminate and a pause, so every
     * redeploy of such a workspace gets here - and it used to fail resuming one with
     * "Database already created".
     */
    public function testDeployingAgainLeavesTheDatabaseAloneAndReportsNothing(): void {
        $deployment = Fixtures::deployment([
            'database_name' => 'tenant_api',
            'database_user' => 'tenant_api',
            'database_pass' => 'secret',
        ]);
        $step = new DatabaseStep();

        // No service to connect to: reaching one would fail the test with an exception.
        $step->startDeployCommand($deployment);

        $this->assertSame('tenant_api', $deployment->database_name);
        $this->assertSame('secret', $deployment->database_pass);
        $this->assertSame(DeploymentStepHelper::DatabaseStatus_Success, $step->getStatus($deployment));
    }

    /**
     * Credentials are written to the deployment only after the server has accepted them.
     *
     * This is the closest a test gets to `startDeployCommand()`: the step builds the name,
     * the user and the password, and then asks a server that is not there. Nothing may be
     * recorded on the way past - a deployment carrying a name and a password for a database
     * that was never created reads as Success forever and no later run will fix it.
     *
     * Nothing is connected to. The service points at a `.test` host, which by RFC 6761
     * resolves nowhere, and the connection fails on its own arguments before a socket is
     * opened: the port is handed over as a string, which mysqli refuses. That is also why the
     * queries below this line cannot be reached from a test at all.
     */
    public function testNothingIsRecordedWhenTheServerCannotBeReached(): void {
        $deployment = $this->deploymentWaitingForItsDatabase();
        $step = new DatabaseStep();

        try {
            $step->startDeployCommand($deployment);
            $this->fail('a database server that is not there should not have answered');
        } catch (DatabaseException) {
            // What we came for.
        }

        $this->assertSame(DeploymentStepHelper::DatabaseStatus_NotPerformed, $step->getStatus($deployment));

        $stored = new Deployment();
        $stored->find($deployment->id);
        $this->assertSame('', $stored->database_name);
        $this->assertSame('', $stored->database_user);
        $this->assertSame('', $stored->database_pass);
    }

    /**
     * A deployment with a database service attached and no database yet.
     */
    private function deploymentWaitingForItsDatabase(): Deployment {
        $service = Fixtures::databaseService();

        return Fixtures::deployment([
            'database_service_id' => $service->id,
            'namespace' => 'customer-a',
            'name' => 'api',
            'database_name' => '',
            'database_user' => '',
            'database_pass' => '',
        ]);
    }

    /**
     * Deliberate: terminating a workspace must not drop the customer's data. This and the
     * namespace step are the two that refuse; the volume steps do not, and what survives
     * there depends on the volume's own reclaim policy.
     */
    public function testTerminateRefusesToDropTheDatabase(): void {
        $deployment = Fixtures::deployment();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database cannot be terminated');

        (new DatabaseStep())->startTerminateCommand($deployment);
    }

}
