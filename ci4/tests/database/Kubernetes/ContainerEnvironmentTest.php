<?php namespace App\Tests\Database\Kubernetes;

use App\DatabaseTestCase;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\Kubernetes\ContainerEnvironment;
use App\Libraries\Kubernetes\WorkloadSecret;
use RenokiCo\PhpK8s\Instances\Container;

/**
 * Where a container's environment variables come from, and which one wins.
 */
class ContainerEnvironmentTest extends DatabaseTestCase {

    public function testTheSpecificationsVariablesHaveTheirPlaceholdersFilledIn(): void {
        $deployment = $this->aDeployment();
        Fixtures::specificationEnvironmentVariable([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'name' => 'DB_DSN',
            'value' => 'mysql://${database.user}:${database.pass}@${database.host}',
        ]);

        $this->assertSame(
            ['DB_DSN' => 'mysql://app:db-secret@db.test'],
            ContainerEnvironment::ofDeployment($deployment)->toArray()
        );
    }

    /**
     * The deployment's own are used as written - no placeholders.
     */
    public function testTheDeploymentsVariablesReplaceTheSpecificationsAsWritten(): void {
        $deployment = $this->aDeployment();
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $deployment->deployment_specification_id, 'name' => 'SHARED', 'value' => 'from-specification']);
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $deployment->deployment_specification_id, 'name' => 'ONLY_SPEC', 'value' => 'spec']);
        Fixtures::deploymentEnvironmentVariable(['deployment_id' => $deployment->id, 'name' => 'SHARED', 'value' => '${database.pass}']);

        $this->assertSame(
            ['SHARED' => '${database.pass}', 'ONLY_SPEC' => 'spec'],
            ContainerEnvironment::ofDeployment($deployment)->toArray()
        );
    }

    public function testAnInitContainerGetsTheDeploymentsVariablesOnlyWhenAskedAndItsOwnWin(): void {
        $deployment = $this->aDeployment();
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $deployment->deployment_specification_id, 'name' => 'SHARED', 'value' => 'from-specification']);
        Fixtures::deploymentEnvironmentVariable(['deployment_id' => $deployment->id, 'name' => 'FROM_DEPLOYMENT', 'value' => 'deployment']);

        $without = Fixtures::initContainer(['container_image_id' => Fixtures::containerImage()->id]);
        Fixtures::initContainerEnvironmentVariable(['init_container_id' => $without->id, 'name' => 'OWN', 'value' => '${database.host}']);
        $with = Fixtures::initContainer(['container_image_id' => Fixtures::containerImage()->id, 'include_deployment_environment_variables' => true]);
        Fixtures::initContainerEnvironmentVariable(['init_container_id' => $with->id, 'name' => 'SHARED', 'value' => 'from-init-container']);

        // Its image comes along with it, as the steps read it.
        $without->container_image->find();
        $with->container_image->find();

        $this->assertSame(['OWN' => 'db.test'], $this->environmentOf($without->toKubernetesResource($deployment, WorkloadSecret::For('test', 'deployment'))));
        $this->assertSame(
            ['SHARED' => 'from-init-container', 'FROM_DEPLOYMENT' => 'deployment'],
            $this->environmentOf($with->toKubernetesResource($deployment, WorkloadSecret::For('test', 'deployment')))
        );
    }

    public function testLaterValuesReplaceEarlierOnes(): void {
        $environment = (new ContainerEnvironment())
            ->set('A', '1')
            ->set('B', '2')
            ->merge(['A' => '3']);

        $this->assertSame(['A' => '3', 'B' => '2'], $environment->toArray());
    }

    // <editor-fold desc="Secret">

    public function testASecretGoesIntoTheWorkloadsSecretAndThePodOnlyNamesIt(): void {
        $deployment = $this->aDeployment();
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $deployment->deployment_specification_id, 'name' => 'API_TOKEN', 'value' => 'token-value', 'is_secret' => true]);
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $deployment->deployment_specification_id, 'name' => 'LOG_LEVEL', 'value' => 'debug']);
        $secret = WorkloadSecret::For('api', 'deployment');

        $env = $this->appliedTo('api', ContainerEnvironment::ofDeployment($deployment), $secret);

        $this->assertSame(['name' => 'LOG_LEVEL', 'value' => 'debug'], $env['LOG_LEVEL']);
        $this->assertSame(
            ['name' => 'API_TOKEN', 'valueFrom' => ['secretKeyRef' => ['name' => 'api-deployment-env', 'key' => 'api.API_TOKEN']]],
            $env['API_TOKEN']
        );
        $this->assertSame(['api.API_TOKEN' => 'token-value'], $secret->data());
    }

    /**
     * An override cannot make a secret public.
     */
    public function testASecretReplacedWithoutTheMarkStaysSecret(): void {
        $deployment = $this->aDeployment();
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $deployment->deployment_specification_id, 'name' => 'API_TOKEN', 'value' => 'default', 'is_secret' => true]);
        Fixtures::deploymentEnvironmentVariable(['deployment_id' => $deployment->id, 'name' => 'API_TOKEN', 'value' => 'overridden']);
        $secret = WorkloadSecret::For('api', 'deployment');

        $env = $this->appliedTo('api', ContainerEnvironment::ofDeployment($deployment), $secret);

        $this->assertArrayHasKey('valueFrom', $env['API_TOKEN']);
        $this->assertSame(['api.API_TOKEN' => 'overridden'], $secret->data());
    }

    /**
     * A value that takes a password from kso is secret without being marked - a DSN too.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('passwords')]
    public function testAValueThatTakesAPasswordIsSecret(string $written): void {
        $deployment = $this->aDeployment();
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $deployment->deployment_specification_id, 'name' => 'DSN', 'value' => $written]);

        $this->assertTrue(ContainerEnvironment::ofDeployment($deployment)->isSecret('DSN'));
    }

    public static function passwords(): array {
        return [
            'the database password' => ['${database.pass}'],
            'in a DSN' => ['mysql://${database.user}:${database.pass}@${database.host}/app'],
            'the mail password' => ['${emailService.pass}'],
        ];
    }

    public function testTheRestOfThePlaceholdersAreNot(): void {
        $deployment = $this->aDeployment();
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $deployment->deployment_specification_id, 'name' => 'DB_HOST', 'value' => '${database.host}']);

        $this->assertFalse(ContainerEnvironment::ofDeployment($deployment)->isSecret('DB_HOST'));
    }

    /**
     * The init container is in the workload's pod: its secrets go into the workload's Secret,
     * under its own name.
     */
    public function testAnInitContainersSecretGoesIntoTheWorkloadsSecret(): void {
        $deployment = $this->aDeployment();
        $initContainer = Fixtures::initContainer(['name' => 'wait-for-db', 'container_image_id' => Fixtures::containerImage()->id]);
        Fixtures::initContainerEnvironmentVariable(['init_container_id' => $initContainer->id, 'name' => 'DB_PASS', 'value' => '${database.pass}']);
        $initContainer->container_image->find();
        $secret = WorkloadSecret::For('api', 'deployment');

        $initContainer->toKubernetesResource($deployment, $secret);

        $this->assertSame(['wait-for-db.DB_PASS' => 'db-secret'], $secret->data());
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    private function aDeployment(): Deployment {
        $database = Fixtures::databaseService(['host' => 'db.test', 'user' => 'app']);

        return Fixtures::deployment([
            'deployment_specification_id' => Fixtures::deploymentSpecification()->id,
            'database_service_id' => $database->id,
            'database_user' => 'app',
            'database_pass' => 'db-secret',
            'version' => '1.0.0',
        ]);
    }

    /**
     * @return array<string, array<string, mixed>> the env entries, by name
     */
    private function appliedTo(string $containerName, ContainerEnvironment $environment, WorkloadSecret $secret): array {
        $container = (new Container())->setAttribute('name', $containerName);
        $environment->applyTo($container, $secret);

        return array_column($container->getAttribute('env', []), null, 'name');
    }

    /**
     * @return array<string, string>
     */
    private function environmentOf(\RenokiCo\PhpK8s\Instances\Container $container): array {
        return array_column($container->getAttribute('env', []), 'value', 'name');
    }

    // </editor-fold>

}
