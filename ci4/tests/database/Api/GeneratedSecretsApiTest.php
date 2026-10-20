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

    public function testAnEarlierVersionCanBeShownAndIsRecordedWithItsNumber(): void {
        $deployment = Fixtures::deployment();
        $first = GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'ci4_key');
        GeneratedSecrets::Rotate(GeneratedSecrets::Deployment, (int) $deployment->id, 'ci4_key');

        $body = $this->decode($this->signedIn()->put("deployments/{$deployment->id}/secrets/reveal?name=ci4_key&version=1"));

        $this->assertSame($first, $body['resource']['value']);
        $details = db_connect()->table('audit_events')->where('action', 'deployment.secret_reveal')->get()->getRowArray()['details'];
        $this->assertSame(['name' => 'ci4_key', 'version' => 1], json_decode($details, true));
    }

    public function testRestoringAVersionMakesItCurrentAndIsRecorded(): void {
        $workspace = Fixtures::workspace();
        $first = GeneratedSecrets::Value(GeneratedSecrets::Workspace, (int) $workspace->id, 'signing');
        GeneratedSecrets::Rotate(GeneratedSecrets::Workspace, (int) $workspace->id, 'signing');
        GeneratedSecrets::Value(GeneratedSecrets::Workspace, (int) $workspace->id, 'signing');

        $listed = $this->decode($this->signedIn()->put("workspaces/{$workspace->id}/secrets/restore?name=signing&version=1"))['resource']['secrets'][0];

        $this->assertSame('restored from version 1', $listed['origin']);
        $this->assertSame($first, GeneratedSecrets::Reveal(GeneratedSecrets::Workspace, (int) $workspace->id, 'signing'));
        $this->assertSame(1, $this->audited('Workspace', (int) $workspace->id, 'workspace.secret_restore'));
    }

    public function testRestoringAVersionThatIsNotThereIsRefusedAndNotRecorded(): void {
        $deployment = Fixtures::deployment();
        GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'key');

        $body = $this->decode($this->signedIn()->put("deployments/{$deployment->id}/secrets/restore?name=key&version=4"));

        $this->assertSame('key has no version 4 to restore', $body['error'] ?? null);
        $this->assertSame(0, $this->audited('Deployment', (int) $deployment->id, 'deployment.secret_restore'));
    }

    /**
     * Put back by hand: recorded, and the value is not in the record.
     */
    public function testAValueSetByHandIsCurrentAndRecordedWithoutTheValue(): void {
        $deployment = Fixtures::deployment();
        GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'key');

        $listed = $this->decode($this->withBodyFormat('json')->signedIn()->put("deployments/{$deployment->id}/secrets/set?name=key", ['value' => 'from-the-backup']))['resource']['secrets'][0];

        $this->assertSame('set by hand', $listed['origin']);
        $this->assertSame('from-the-backup', GeneratedSecrets::Reveal(GeneratedSecrets::Deployment, (int) $deployment->id, 'key'));
        $this->assertSame(1, $this->audited('Deployment', (int) $deployment->id, 'deployment.secret_set'));
        $this->assertSame(0, db_connect()->table('audit_events')->like('details', 'from-the-backup')->countAllResults());
    }

    /**
     * A deleted deployment's secrets are listed by who it was, shown on request and recorded,
     * and purged on request and recorded - and the list has no value.
     */
    public function testADeletedDeploymentsSecretsAreListedShownAndPurged(): void {
        $deployment = Fixtures::deployment(['name' => 'kunde-api']);
        $value = GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'ci4_key');
        $deployment->delete();
        $query = "owner=deployment&ownerId={$deployment->id}";

        $response = (string) $this->signedIn()->get('deleted-secrets')->response()->getBody();
        $shown = $this->decode($this->signedIn()->put("deleted-secrets/reveal?{$query}&name=ci4_key"));
        $purged = $this->decode($this->signedIn()->put("deleted-secrets/purge?{$query}"));

        $owners = json_decode($response, true)['resource']['owners'];
        $this->assertSame(['kunde-api', ['ci4_key']], [$owners[0]['owner_name'], array_column($owners[0]['secrets'], 'name')]);
        $this->assertStringNotContainsString($value, $response);
        $this->assertSame($value, $shown['resource']['value']);
        $this->assertSame([], $purged['resource']['owners']);
        $this->assertSame(1, $this->audited('Deployment', (int) $deployment->id, 'deleted_secret.reveal'));
        $this->assertSame(1, $this->audited('Deployment', (int) $deployment->id, 'deleted_secret.purge'));
    }

    /**
     * Only a deleted owner's: a live deployment's secrets are not shown or purged this way.
     */
    public function testALiveDeploymentsSecretsAreNotReachedAsDeletedOnes(): void {
        $deployment = Fixtures::deployment();
        GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'key');
        $query = "owner=deployment&ownerId={$deployment->id}";

        $shown = $this->decode($this->signedIn()->put("deleted-secrets/reveal?{$query}&name=key"));
        $purged = $this->decode($this->signedIn()->put("deleted-secrets/purge?{$query}"));

        $this->assertSame('unknown deleted owner', $shown['error'] ?? null);
        $this->assertSame('unknown deleted owner', $purged['error'] ?? null);
        $this->assertCount(1, GeneratedSecrets::Of(GeneratedSecrets::Deployment, (int) $deployment->id));
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
