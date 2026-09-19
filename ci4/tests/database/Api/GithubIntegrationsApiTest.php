<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use App\Tests\Fakes\FakeIntegrations;

/**
 * The GitHub integration over the API: one App per organisation.
 *
 * Only the name and the organisation are the operator's to write. The rest comes from
 * GitHub through the setup flow, and the secrets never leave the server.
 */
class GithubIntegrationsApiTest extends ControllerTestCase {

    private FakeIntegrations $fakes;

    public function setUp(): void {
        parent::setUp();
        $this->fakes = FakeIntegrations::install();
    }

    public function tearDown(): void {
        FakeIntegrations::uninstall();
        parent::tearDown();
    }

    // <editor-fold desc="What the API reads and writes">

    public function testTheSecretsAreNeverReturned(): void {
        $integration = Fixtures::githubIntegration(['setup_state' => 'a-state']);

        $body = $this->decode($this->signedIn()->get("github_integrations/{$integration->id}"));
        $text = json_encode($body);

        foreach (['client_secret', 'private_key', 'webhook_secret'] as $secret) {
            $this->assertArrayNotHasKey($secret, $body['resource']);
            $this->assertTrue($body['resource']["has_{$secret}"], $secret);
        }
        $this->assertArrayNotHasKey('setup_state', $body['resource']);
        $this->assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $text);
        $this->assertStringNotContainsString('a-state', $text);
        $this->assertSame(815, $body['resource']['installation_id']);
    }

    /**
     * The flags are what the page shows in place of the secrets, so one that is always true
     * would hide an App that was never created.
     */
    public function testAnIntegrationWithoutAnAppSaysSo(): void {
        $integration = Fixtures::githubIntegration(['client_secret' => '', 'private_key' => '', 'webhook_secret' => '']);

        $body = $this->decode($this->signedIn()->get("github_integrations/{$integration->id}"));

        $this->assertFalse($body['resource']['has_client_secret']);
        $this->assertFalse($body['resource']['has_private_key']);
        $this->assertFalse($body['resource']['has_webhook_secret']);
    }

    /**
     * An App's keys come from GitHub. Taking them from a request would let a token holder
     * point kso at an App they made, which is what the state nonce keeps out of the callback.
     */
    public function testWhatGithubWritesCannotBeWrittenThroughTheApi(): void {
        $integration = Fixtures::githubIntegration();

        $this->withBodyFormat('json')->signedIn()->patch("github_integrations/{$integration->id}", [
            'name' => 'renamed',
            'app_id' => 1,
            'private_key' => 'mine',
            'installation_id' => 1,
            'setup_state' => 'chosen',
        ]);

        $this->assertSame('renamed', $this->stored($integration->id, 'name'));
        $this->assertSame('4711', $this->stored($integration->id, 'app_id'));
        $this->assertSame('-----BEGIN RSA PRIVATE KEY-----', $this->stored($integration->id, 'private_key'));
        $this->assertSame('815', $this->stored($integration->id, 'installation_id'));
        $this->assertSame('', $this->stored($integration->id, 'setup_state'));
    }

    public function testCreatingOneTakesOnlyTheNameAndTheOrganisation(): void {
        $body = $this->decode($this->withBodyFormat('json')->signedIn()->post('github_integrations', [
            'name' => 'acme',
            'organization' => 'acme',
            'app_id' => 1,
            'setup_state' => 'chosen',
        ]));

        $this->assertSame('OK', $body['status']);
        $this->assertSame('acme', $this->stored($body['resource']['id'], 'organization'));
        $this->assertSame('0', $this->stored($body['resource']['id'], 'app_id'));
        $this->assertSame('', $this->stored($body['resource']['id'], 'setup_state'));
    }

    public function testAnIntegrationInUseCannotBeDeleted(): void {
        $integration = Fixtures::githubIntegration();
        Fixtures::containerImage(['github_integration_id' => $integration->id]);

        $response = $this->signedIn()->delete("github_integrations/{$integration->id}");

        $this->assertSame(403, $response->response()->getStatusCode());
    }

    /**
     * The delete dialog lists the images in the way with this filter, so it has to narrow.
     */
    public function testTheImagesUsingAnIntegrationCanBeListed(): void {
        $integration = Fixtures::githubIntegration();
        Fixtures::containerImage(['name' => 'uses-it', 'github_integration_id' => $integration->id]);
        Fixtures::containerImage(['name' => 'uses-another', 'github_integration_id' => Fixtures::githubIntegration()->id]);
        Fixtures::containerImage(['name' => 'uses-none']);

        $body = $this->decode($this->signedIn()->get("container_images?filter=github_integration_id:{$integration->id}"));

        $this->assertSame(['uses-it'], array_column($body['resources'], 'name'));
    }

    public function testAnUnusedIntegrationCanBeDeleted(): void {
        $integration = Fixtures::githubIntegration();

        $response = $this->signedIn()->delete("github_integrations/{$integration->id}");

        $this->assertSame(200, $response->response()->getStatusCode());
        $this->assertSame(0, $this->db->table('github_integrations')->where('id', $integration->id)->where('deletion_id', null)->countAllResults());
    }

    // </editor-fold>

    // <editor-fold desc="Creating the App">

    /**
     * The manifest is the permission request GitHub shows the operator, and the only chance
     * to ask for less. Private, because a public App can be installed by anyone who finds it.
     */
    public function testTheManifestAsksForLittleAndKeepsTheAppPrivate(): void {
        $resource = $this->createApp(Fixtures::githubIntegration()->id);

        $this->assertSame(['contents' => 'read', 'metadata' => 'read'], $resource['manifest']['default_permissions']);
        $this->assertFalse($resource['manifest']['public']);
        $this->assertStringStartsWith('KSO - ', $resource['manifest']['name']);
    }

    /**
     * The existing Apps have these urls stored at GitHub, so they cannot move.
     */
    public function testTheManifestSendsGithubBackToThisInstallation(): void {
        $manifest = $this->createApp(Fixtures::githubIntegration()->id)['manifest'];

        $this->assertSame($manifest['url'] . '/githubapp/callback', $manifest['redirect_url']);
        $this->assertSame($manifest['url'] . '/githubapp/post-install', $manifest['setup_url']);
    }

    public function testTheAppIsMadeInTheOrganisationWithAStateForTheCallback(): void {
        $integration = Fixtures::githubIntegration(['organization' => 'acme-inc']);

        $url = $this->createApp($integration->id)['url'];

        $state = $this->stored($integration->id, 'setup_state');
        $this->assertSame(48, strlen($state));
        $this->assertSame("https://github.com/organizations/acme-inc/settings/apps/new?state={$state}", $url);
    }

    public function testWithoutAnOrganisationTheAppIsMadeOnThePersonalAccount(): void {
        $integration = Fixtures::githubIntegration(['organization' => '']);

        $url = $this->createApp($integration->id)['url'];

        $this->assertStringStartsWith('https://github.com/settings/apps/new?state=', $url);
    }

    /**
     * The organisation goes into a url, so it has to be a name GitHub would accept.
     */
    public function testAnOrganisationThatIsNotAGithubNameIsRefused(): void {
        $integration = Fixtures::githubIntegration(['organization' => 'acme/../../evil?x=']);

        $body = $this->decode($this->signedIn()->post("github-integrations/{$integration->id}/create-app"));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('', $this->stored($integration->id, 'setup_state'));
    }

    public function testAnUnknownIntegrationIsReported(): void {
        foreach (['create-app', 'install'] as $action) {
            $body = $this->decode($this->signedIn()->post("github-integrations/999999/{$action}"));
            $this->assertSame('unknown github integration', $body['error'], $action);
        }
        $body = $this->decode($this->signedIn()->get('github-integrations/999999/repositories'));
        $this->assertSame('unknown github integration', $body['error']);
    }

    // </editor-fold>

    // <editor-fold desc="Installing it">

    public function testInstallingSendsTheOperatorToTheAppWithAFreshState(): void {
        $integration = Fixtures::githubIntegration(['setup_state' => 'an-older-one']);

        $body = $this->decode($this->signedIn()->post("github-integrations/{$integration->id}/install"));

        $state = $this->stored($integration->id, 'setup_state');
        $this->assertNotSame('an-older-one', $state);
        $this->assertSame("https://github.com/apps/kso-test/installations/new?state={$state}", $body['resource']['url']);
    }

    public function testThereIsNothingToInstallBeforeTheAppIsCreated(): void {
        $integration = Fixtures::githubIntegration(['app_id' => 0, 'private_key' => '']);

        $body = $this->decode($this->signedIn()->post("github-integrations/{$integration->id}/install"));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('', $this->stored($integration->id, 'setup_state'));
    }

    // </editor-fold>

    // <editor-fold desc="Listing repositories">

    public function testRepositoriesAreListedByNameWithoutTheArchivedOnes(): void {
        $this->fakes->githubRepositories = [
            ['id' => 1, 'full_name' => 'acme/zeta', 'name' => 'zeta', 'archived' => false],
            ['id' => 2, 'full_name' => 'acme/old', 'name' => 'old', 'archived' => true],
            ['id' => 3, 'full_name' => 'acme/alpha', 'name' => 'alpha', 'archived' => false],
        ];
        $integration = Fixtures::githubIntegration();

        $body = $this->decode($this->signedIn()->get("github-integrations/{$integration->id}/repositories"));

        $this->assertSame([
            ['id' => 3, 'full_name' => 'acme/alpha', 'name' => 'alpha'],
            ['id' => 1, 'full_name' => 'acme/zeta', 'name' => 'zeta'],
        ], $body['resources']);
    }

    /**
     * Half a configuration is refused here rather than at GitHub.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('integrationsThatAreNotInstalled')]
    public function testAnIntegrationThatIsNotInstalledHasNoRepositories(array $overrides): void {
        $integration = Fixtures::githubIntegration($overrides);

        $body = $this->decode($this->signedIn()->get("github-integrations/{$integration->id}/repositories"));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('the GitHub App is not installed yet', $body['error']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function integrationsThatAreNotInstalled(): array {
        return [
            'no installation' => [['installation_id' => 0]],
            'no app id' => [['app_id' => 0]],
            'no key' => [['private_key' => '']],
        ];
    }

    // </editor-fold>

    /**
     * @return array<string, mixed>
     */
    private function createApp(int $id): array {
        $body = $this->decode($this->signedIn()->post("github-integrations/{$id}/create-app"));
        $this->assertSame('OK', $body['status'], json_encode($body));
        $body['resource']['manifest'] = json_decode($body['resource']['manifest'], true);
        return $body['resource'];
    }

    private function stored(int $id, string $column): string {
        return (string) $this->db->table('github_integrations')->where('id', $id)->get()->getRowArray()[$column];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

}
