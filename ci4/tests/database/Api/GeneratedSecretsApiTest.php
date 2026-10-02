<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use App\Libraries\Kubernetes\GeneratedSecrets;

/**
 * The secrets kso made, as a person meets them: listed by name and never by value, shown only on
 * request and recorded, rotated and recorded. And a variable with a placeholder kso cannot make
 * is refused when it is saved, so no deploy meets it.
 */
class GeneratedSecretsApiTest extends ControllerTestCase {

    public function testTheListHasNamesAndTimesAndNeverAValue(): void {
        $deployment = Fixtures::deployment();
        $value = GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'centrifugo_api');

        $response = (string) $this->signedIn()->get("deployments/{$deployment->id}/secrets")->response()->getBody();

        $secrets = json_decode($response, true)['resource']['secrets'];
        $this->assertSame(['centrifugo_api'], array_column($secrets, 'name'));
        $this->assertNotEmpty($secrets[0]['created']);
        $this->assertNull($secrets[0]['rotated']);
        $this->assertStringNotContainsString($value, $response);
    }

    public function testShowingAValueIsRecorded(): void {
        $deployment = Fixtures::deployment();
        $value = GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'storage_url');

        $body = $this->decode($this->signedIn()->put("deployments/{$deployment->id}/secrets/reveal?name=storage_url"));

        $this->assertSame($value, $body['resource']['value']);
        $this->assertSame(1, $this->audited('Deployment', (int) $deployment->id, 'deployment.secret_reveal'));
    }

    public function testRotatingMakesANewValueAndIsRecorded(): void {
        $workspace = Fixtures::workspace();
        $old = GeneratedSecrets::Value(GeneratedSecrets::Workspace, (int) $workspace->id, 'signing');

        $body = $this->decode($this->signedIn()->put("workspaces/{$workspace->id}/secrets/rotate?name=signing"));

        $this->assertNotEmpty($body['resource']['secrets'][0]['rotated']);
        $this->assertNotSame($old, GeneratedSecrets::Value(GeneratedSecrets::Workspace, (int) $workspace->id, 'signing'));
        $this->assertSame(1, $this->audited('Workspace', (int) $workspace->id, 'workspace.secret_rotate'));
    }

    /**
     * Rotated, it has no value until the next deploy makes one - the list says so, and there is
     * nothing to show.
     */
    public function testARotatedSecretIsMadeAnewAtTheNextDeployAndHasNothingToShowUntilThen(): void {
        $deployment = Fixtures::deployment();
        GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'token');

        $listed = $this->decode($this->signedIn()->put("deployments/{$deployment->id}/secrets/rotate?name=token"))['resource']['secrets'][0];
        $shown = $this->decode($this->signedIn()->put("deployments/{$deployment->id}/secrets/reveal?name=token"));

        $this->assertTrue($listed['pending']);
        $this->assertSame('rotated - the new value is made at the next deploy', $shown['error'] ?? null);
    }

    /**
     * Shown or rotated, a secret kso never made is not made by asking - and nothing is recorded.
     */
    public function testASecretThatIsNotThereIsNotMadeByAskingForIt(): void {
        $deployment = Fixtures::deployment();

        $shown = $this->decode($this->signedIn()->put("deployments/{$deployment->id}/secrets/reveal?name=nope"));
        $rotated = $this->decode($this->signedIn()->put("deployments/{$deployment->id}/secrets/rotate?name=nope"));

        $this->assertSame('unknown secret', $shown['error'] ?? null);
        $this->assertSame('unknown secret', $rotated['error'] ?? null);
        $this->assertSame(0, db_connect()->table('deployment_secrets')->where('deployment_id', $deployment->id)->countAllResults());
    }

    /**
     * Every list of variables checks the names, so a deploy never meets one it cannot make.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('variableLists')]
    public function testAPlaceholderWithABadNameIsRefusedWhenSaved(string $path): void {
        $owners = [
            'deployment' => fn() => Fixtures::deployment()->id,
            'specification' => fn() => Fixtures::deploymentSpecification()->id,
            'init container' => fn() => Fixtures::initContainer()->id,
            'workspace template' => fn() => Fixtures::workspaceTemplate()->id,
        ];
        $id = $owners[$path]();
        $url = match ($path) {
            'deployment' => "deployments/{$id}/environment-variables",
            'specification' => "deployment-specifications/{$id}/environment-variables",
            'init container' => "init-containers/{$id}/environment-variables",
            'workspace template' => "workspace-templates/{$id}/environment-variables",
        };

        $body = $this->decode($this->withBodyFormat('json')->signedIn()->put($url, ['values' => [['name' => 'KEY', 'value' => '${secret.Api-Key}', 'is_secret' => false]]]));

        $this->assertStringContainsString('${secret.Api-Key} is not a secret kso can make', $body['error'] ?? '');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function variableLists(): array {
        return [
            'deployment' => ['deployment'],
            'specification' => ['specification'],
            'init container' => ['init container'],
            'workspace template' => ['workspace template'],
        ];
    }

    // <editor-fold desc="Helpers">

    private function audited(string $type, int $id, string $action): int {
        return db_connect()->table('audit_events')
            ->where('resource_type', $type)
            ->where('resource_id', $id)
            ->where('action', $action)
            ->countAllResults();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
