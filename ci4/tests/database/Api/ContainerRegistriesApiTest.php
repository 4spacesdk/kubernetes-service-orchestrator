<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use App\Libraries\Crypt;
use App\Tests\Fakes\FakeIntegrations;

/**
 * The registry connection over the API.
 *
 * The secrets are write-only, which only works if saving the rest of the form cannot wipe
 * them: the dialog never has them to send back. So a PATCH that leaves a secret out, or
 * sends it empty, keeps what is stored.
 */
class ContainerRegistriesApiTest extends ControllerTestCase {

    public function tearDown(): void {
        FakeIntegrations::uninstall();

        parent::tearDown();
    }

    // <editor-fold desc="Write-only secrets">

    public function testASecretIsWrittenOnCreateAndNotReturned(): void {
        $body = $this->decode($this->withBodyFormat('json')->signedIn()->post('container_registries', [
            'name' => 'harbor',
            'provider' => \ContainerRegistries::Harbor,
            'harbor_url' => 'harbor.example.org',
            'harbor_username' => 'robot',
            'harbor_password' => 'the-password',
        ]));

        $this->assertSame('OK', $body['status']);
        $this->assertArrayNotHasKey('harbor_password', $body['resource']);
        $this->assertTrue($body['resource']['has_harbor_password']);
        $this->assertSame('the-password', $this->stored($body['resource']['id'], 'harbor_password'));
    }

    public function testSavingTheFormWithoutTheSecretKeepsIt(): void {
        $registry = Fixtures::containerRegistry(['harbor_password' => 'the-password']);

        $this->patchRegistry($registry->id, ['name' => 'renamed']);

        $this->assertSame('renamed', $this->stored($registry->id, 'name'));
        $this->assertSame('the-password', $this->stored($registry->id, 'harbor_password'));
    }

    /**
     * An empty field is what a cleared input sends. Taking it as "remove the password"
     * would make the untouched form destructive.
     */
    public function testAnEmptySecretKeepsTheStoredOne(): void {
        $registry = Fixtures::containerRegistry(['harbor_password' => 'the-password']);

        $this->patchRegistry($registry->id, ['harbor_password' => '']);

        $this->assertSame('the-password', $this->stored($registry->id, 'harbor_password'));
    }

    public function testANewSecretReplacesTheStoredOne(): void {
        $registry = Fixtures::containerRegistry(['harbor_password' => 'the-password']);

        $this->patchRegistry($registry->id, ['harbor_password' => 'rotated']);

        $this->assertSame('rotated', $this->stored($registry->id, 'harbor_password'));
    }

    // </editor-fold>

    // <editor-fold desc="Deleting">

    /**
     * An image would keep pointing at a deleted connection and lose its tags and events
     * without saying why.
     */
    public function testAConnectionInUseCannotBeDeleted(): void {
        $registry = Fixtures::containerRegistry();
        Fixtures::containerImage(['container_registry_id' => $registry->id]);

        $response = $this->signedIn()->delete("container_registries/{$registry->id}");

        $this->assertSame(403, $response->response()->getStatusCode());
        $this->assertSame(1, $this->db->table('container_registries')->where('id', $registry->id)->where('deletion_id', null)->countAllResults());
    }

    public function testAnUnusedConnectionCanBeDeleted(): void {
        $registry = Fixtures::containerRegistry();

        $response = $this->signedIn()->delete("container_registries/{$registry->id}");

        $this->assertSame(200, $response->response()->getStatusCode());
        $this->assertSame(0, $this->db->table('container_registries')->where('id', $registry->id)->where('deletion_id', null)->countAllResults());
    }

    // </editor-fold>

    // <editor-fold desc="Testing the connection">

    public function testAWorkingConnectionAnswersWithWhatTheRegistrySaid(): void {
        FakeIntegrations::install()->tags = [];
        $registry = Fixtures::containerRegistry();

        $body = $this->decode($this->signedIn()->get("container-registries/{$registry->id}/test"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame('fake registry', $body['resource']['message']);
    }

    public function testAnUnknownProviderIsReported(): void {
        $registry = Fixtures::containerRegistry(['provider' => 'something-else']);

        $body = $this->decode($this->signedIn()->get("container-registries/{$registry->id}/test"));

        $this->assertNotSame('OK', $body['status']);
        $this->assertStringContainsString("unsupported provider 'something-else'", json_encode($body));
    }

    public function testAnUnknownConnectionIsReported(): void {
        $body = $this->decode($this->signedIn()->get('container-registries/999999/test'));

        $this->assertNotSame('OK', $body['status']);
    }

    // </editor-fold>

    // <editor-fold desc="Creating images from the registry">

    public function testTheRepositoriesAreListedWithTheImageAlreadyMadeFromThem(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = [];
        $fakes->repositories = ['team/api', 'team/web'];
        $registry = Fixtures::containerRegistry();
        $existing = Fixtures::containerImage(['url' => 'registry.example.org/team/api']);

        $body = $this->decode($this->signedIn()->get("container-registries/{$registry->id}/repositories"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame([
            ['name' => 'team/api', 'url' => 'registry.example.org/team/api', 'container_image_id' => (int) $existing->id],
            ['name' => 'team/web', 'url' => 'registry.example.org/team/web', 'container_image_id' => null],
        ], $body['resources']);
    }

    public function testImportingMakesAnImageInTheConnection(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = [];
        $fakes->repositories = ['team/api'];
        $registry = Fixtures::containerRegistry();

        $body = $this->import($registry->id, ['team/api']);

        $this->assertCount(1, $body['resources']);
        $image = $this->db->table('container_images')->where('url', 'registry.example.org/team/api')->get()->getRow();
        $this->assertSame('team/api', $image->name);
        $this->assertSame((int) $registry->id, (int) $image->container_registry_id);
    }

    /**
     * Twice the same click is one image, not two.
     */
    public function testARepositoryWithAnImageIsNotImportedAgain(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = [];
        $fakes->repositories = ['team/api'];
        $registry = Fixtures::containerRegistry();
        Fixtures::containerImage(['url' => 'registry.example.org/team/api']);

        $body = $this->import($registry->id, ['team/api']);

        $this->assertSame([], $body['resources']);
        $this->assertSame(1, $this->db->table('container_images')->where('url', 'registry.example.org/team/api')->countAllResults());
    }

    /**
     * The url comes from the registry, never from the request. A name the registry does
     * not list makes nothing - otherwise the endpoint would create an image pointing
     * anywhere the caller liked.
     */
    public function testANameTheRegistryDoesNotListMakesNothing(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = [];
        $fakes->repositories = ['team/api'];
        $registry = Fixtures::containerRegistry();
        $before = $this->db->table('container_images')->countAllResults();

        $body = $this->import($registry->id, ['evil.example.org/backdoor']);

        $this->assertSame([], $body['resources']);
        $this->assertSame($before, $this->db->table('container_images')->countAllResults());
    }

    public function testAFailingRegistryIsReportedRatherThanListedAsEmpty(): void {
        $registry = Fixtures::containerRegistry(['provider' => 'something-else']);

        $body = $this->decode($this->signedIn()->get("container-registries/{$registry->id}/repositories"));

        $this->assertNotSame('OK', $body['status']);
    }

    // </editor-fold>

    // <editor-fold desc="Setting up events">

    public function testSettingUpEventsHandsTheRegistryAUrlAndASecretAndTurnsEventsOn(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = [];
        $registry = Fixtures::containerRegistry(['events_enabled' => false]);
        Fixtures::containerImage(['container_registry_id' => $registry->id, 'url' => 'registry.example.org/team/api']);

        $body = $this->setupEvents($registry->id);

        $this->assertSame('fake events set up', $body['resource']['message']);
        $call = $fakes->eventSetups[0];
        $this->assertStringEndsWith("/auto-updates/webhooks/harbor/{$registry->id}", $call['webhookUrl']);
        $this->assertSame($this->stored($registry->id, 'webhook_secret'), $call['secret']);
        $this->assertGreaterThanOrEqual(32, strlen($call['secret']));
        $this->assertSame(['registry.example.org/team/api'], $call['imageUrls']);
        $this->assertSame('1', $this->stored($registry->id, 'events_enabled'));
    }

    /**
     * Setting up again - after an import into a new project - must not change the secret,
     * or every webhook set up before would start being refused.
     */
    public function testSettingUpAgainKeepsTheSecret(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = [];
        $registry = Fixtures::containerRegistry();

        $this->setupEvents($registry->id);
        $this->setupEvents($registry->id);

        $this->assertSame($fakes->eventSetups[0]['secret'], $fakes->eventSetups[1]['secret']);
    }

    /**
     * The secret is kso's. A caller who could set it would know it.
     */
    public function testTheWebhookSecretCannotBeWrittenThroughTheApi(): void {
        $registry = Fixtures::containerRegistry(['webhook_secret' => 'made-by-kso']);

        $this->patchRegistry($registry->id, ['webhook_secret' => 'chosen-by-caller']);
        $body = $this->decode($this->signedIn()->get("container_registries/{$registry->id}"));

        $this->assertSame('made-by-kso', $this->stored($registry->id, 'webhook_secret'));
        $this->assertArrayNotHasKey('webhook_secret', $body['resource']);
        $this->assertTrue($body['resource']['has_webhook_secret']);
    }

    public function testARegistryThatRefusesIsReportedAndEventsStayOff(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = [];
        $fakes->failSetupEventsWith = new \Exception('Harbor answered 403: forbidden');
        $registry = Fixtures::containerRegistry(['events_enabled' => false]);

        $body = $this->decode($this->signedIn()->post("container-registries/{$registry->id}/setup-events"));

        $this->assertNotSame('OK', $body['status']);
        $this->assertSame('Harbor answered 403: forbidden', $body['error']);
        $this->assertSame('0', $this->stored($registry->id, 'events_enabled'));
    }

    // </editor-fold>

    // <editor-fold desc="Helpers">

    private function setupEvents(int $id): array {
        $body = $this->decode($this->signedIn()->post("container-registries/{$id}/setup-events"));
        $this->assertSame('OK', $body['status'], json_encode($body));
        return $body;
    }


    private function import(int $id, array $names): array {
        $body = $this->decode($this->withBodyFormat('json')->signedIn()->post("container-registries/{$id}/import", ['repositories' => $names]));
        $this->assertSame('OK', $body['status'], json_encode($body));
        return $body;
    }


    private function patchRegistry(int $id, array $fields): void {
        $body = $this->decode($this->withBodyFormat('json')->signedIn()->patch("container_registries/{$id}", $fields));
        $this->assertSame('OK', $body['status'], json_encode($body));
    }

    /**
     * The credential as it is stored, decrypted.
     *
     * Read off the column rather than through the entity on purpose: the entity decrypts on
     * the way out, so asking it would prove nothing about what is in the table. This way the
     * assertion fails both when the wrong value was written and when nothing encrypted it -
     * `Crypt::Decrypt()` hands back an unmarked value unchanged, and the raw check below
     * catches that.
     */
    private function stored(int $id, string $column): string {
        return (string) Crypt::Decrypt($this->storedRaw($id, $column));
    }

    private function storedRaw(int $id, string $column): string {
        return (string) $this->db->table('container_registries')->where('id', $id)->get()->getRow()->{$column};
    }

    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
