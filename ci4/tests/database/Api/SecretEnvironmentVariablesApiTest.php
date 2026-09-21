<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use App\Libraries\Crypt;
use App\Libraries\Kubernetes\ContainerEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Environment variables marked secret, on each of the four things that have them: stored
 * encrypted, never read back, and kept when a form saves without them.
 */
class SecretEnvironmentVariablesApiTest extends ControllerTestCase {

    /**
     * Where each is written, read and stored: the parent's resource, the include that reads
     * its variables, and the table.
     */
    private const array Parents = [
        'specification' => ['deployment-specifications', 'deployment_specifications', 'deployment_specification_environment_variable', 'deployment_specification_environment_variables', 'deployment_specification_id'],
        'template' => ['deployment-packages', 'deployment_packages', 'deployment_package_environment_variable', 'deployment_package_environment_variables', 'deployment_package_id'],
        'deployment' => ['deployments', 'deployments', 'environment_variable', 'environment_variables', 'deployment_id'],
        'init container' => ['init-containers', 'init_containers', 'init_container_environment_variable', 'init_container_environment_variables', 'init_container_id'],
    ];

    public static function parents(): array {
        return array_map(fn (array $parent) => [...$parent], self::Parents);
    }

    #[DataProvider('parents')]
    public function testASecretIsNeverReadBack(string $write, string $read, string $include, string $table, string $column): void {
        $id = $this->aParent($read);

        $this->putValues("{$write}/{$id}/environment-variables", [
            ['name' => 'API_TOKEN', 'value' => 'token-value', 'is_secret' => true],
            ['name' => 'LOG_LEVEL', 'value' => 'debug'],
        ]);

        $variables = $this->readVariables($read, $id, $include, $table);
        $this->assertArrayNotHasKey('value', $variables['API_TOKEN']);
        $this->assertTrue($variables['API_TOKEN']['has_value']);
        $this->assertTrue((bool) $variables['API_TOKEN']['is_secret']);
        $this->assertSame('debug', $variables['LOG_LEVEL']['value']);
    }

    /**
     * Every value, secret or not.
     */
    #[DataProvider('parents')]
    public function testEveryValueIsStoredEncrypted(string $write, string $read, string $include, string $table, string $column): void {
        $id = $this->aParent($read);

        $this->putValues("{$write}/{$id}/environment-variables", [
            ['name' => 'API_TOKEN', 'value' => 'token-value', 'is_secret' => true],
            ['name' => 'LOG_LEVEL', 'value' => 'debug'],
        ]);

        $stored = array_column($this->db->table($table)->where($column, $id)->get()->getResultArray(), 'value', 'name');
        $this->assertTrue(Crypt::IsEncrypted($stored['API_TOKEN']));
        $this->assertTrue(Crypt::IsEncrypted($stored['LOG_LEVEL']));
        $this->assertSame('token-value', Crypt::Decrypt($stored['API_TOKEN']));
    }

    /**
     * The form cannot send back a value it was not shown.
     */
    #[DataProvider('parents')]
    public function testASecretSavedWithoutItsValueKeepsIt(string $write, string $read, string $include, string $table, string $column): void {
        $id = $this->aParent($read);
        $this->putValues("{$write}/{$id}/environment-variables", [
            ['name' => 'API_TOKEN', 'value' => 'token-value', 'is_secret' => true],
        ]);

        $this->putValues("{$write}/{$id}/environment-variables", [
            ['name' => 'API_TOKEN', 'value' => '', 'is_secret' => true],
            ['name' => 'ADDED', 'value' => 'new'],
        ]);

        $this->assertSame(['API_TOKEN' => 'token-value', 'ADDED' => 'new'], $this->storedValues($table, $column, $id));
    }

    /**
     * Unmarking one takes a new value - or it would be a way to read it back.
     */
    #[DataProvider('parents')]
    public function testUnmarkingASecretWithoutANewValueLeavesItSecret(string $write, string $read, string $include, string $table, string $column): void {
        $id = $this->aParent($read);
        $this->putValues("{$write}/{$id}/environment-variables", [
            ['name' => 'API_TOKEN', 'value' => 'token-value', 'is_secret' => true],
        ]);

        $this->putValues("{$write}/{$id}/environment-variables", [
            ['name' => 'API_TOKEN', 'value' => '', 'is_secret' => false],
        ]);

        $variables = $this->readVariables($read, $id, $include, $table);
        $this->assertArrayNotHasKey('value', $variables['API_TOKEN']);
        $this->assertSame(['API_TOKEN' => 'token-value'], $this->storedValues($table, $column, $id));
    }

    #[DataProvider('parents')]
    public function testASecretIsUnmarkedWithANewValue(string $write, string $read, string $include, string $table, string $column): void {
        $id = $this->aParent($read);
        $this->putValues("{$write}/{$id}/environment-variables", [
            ['name' => 'API_TOKEN', 'value' => 'token-value', 'is_secret' => true],
        ]);

        $this->putValues("{$write}/{$id}/environment-variables", [
            ['name' => 'API_TOKEN', 'value' => 'public-now', 'is_secret' => false],
        ]);

        $this->assertSame('public-now', $this->readVariables($read, $id, $include, $table)['API_TOKEN']['value']);
    }

    /**
     * The pod still gets the value. Until the variables go into a Secret of their own, it is
     * there in the clear, as before.
     */
    public function testThePodGetsTheSecretsValue(): void {
        $deployment = Fixtures::deployment(['deployment_specification_id' => Fixtures::deploymentSpecification()->id]);
        $this->putValues("deployments/{$deployment->id}/environment-variables", [
            ['name' => 'API_TOKEN', 'value' => 'token-value', 'is_secret' => true],
        ]);

        $this->assertSame(['API_TOKEN' => 'token-value'], ContainerEnvironment::ofDeployment($deployment)->toArray());
    }

    // <editor-fold desc="Helpers">

    private function aParent(string $read): int {
        return match ($read) {
            'deployment_specifications' => Fixtures::deploymentSpecification()->id,
            'deployment_packages' => Fixtures::deploymentPackage()->id,
            'deployments' => Fixtures::deployment()->id,
            'init_containers' => Fixtures::initContainer(['container_image_id' => Fixtures::containerImage()->id])->id,
        };
    }

    /**
     * @param array<array<string, mixed>> $values
     */
    private function putValues(string $path, array $values): void {
        $response = $this->withBodyFormat('json')->signedIn()->put($path, ['values' => $values]);
        $body = json_decode((string) $response->response()->getBody(), true);
        $this->assertSame('OK', $body['status'], $path);
    }

    /**
     * As the dialogs read them: the parent with its variables included.
     *
     * @return array<string, array<string, mixed>> by name
     */
    private function readVariables(string $resource, int $id, string $include, string $table): array {
        $response = $this->signedIn()->get("{$resource}?filter=id:{$id}&include={$include}");
        $body = json_decode((string) $response->response()->getBody(), true);

        return array_column($body['resources'][0][$table], null, 'name');
    }

    /**
     * @return array<string, string>
     */
    private function storedValues(string $table, string $column, int $id): array {
        return array_map(
            fn (string $value) => Crypt::Decrypt($value),
            array_column($this->db->table($table)->where($column, $id)->orderBy('id')->get()->getResultArray(), 'value', 'name')
        );
    }

    // </editor-fold>

}
