<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\GithubIntegration;
use App\Fixtures;
use App\Tests\Fakes\FakeIntegrations;

/**
 * The two places GitHub sends the operator's browser while a GitHub integration is set up.
 *
 * Both are public, because a browser arriving from GitHub carries no token, so whatever they
 * accept is what anyone who can get the operator to open a link gets to choose. Before GitHub
 * became an integration of its own, that was everything: the callback replaced the
 * connected App's keys, and post-install repointed the installation, on the strength of a
 * query string.
 *
 * Now each acts only on the integration whose state nonce it is handed, and uses it up.
 */
class GithubAppApiTest extends ControllerTestCase {

    private FakeIntegrations $fakes;

    public function setUp(): void {
        parent::setUp();
        $this->fakes = FakeIntegrations::install();
    }

    public function tearDown(): void {
        FakeIntegrations::uninstall();
        parent::tearDown();
    }

    /**
     * The controller and the route table agree that both are public, and the two endpoints
     * that did not need to be - the manifest and the repository listing - are gone from it.
     * They live on the integration now, behind a token.
     */
    public function testOnlyTheTwoRedirectsAreLeftAndBothArePublic(): void {
        $controller = new \App\Controllers\GithubApp();
        $this->assertFalse($controller->requireAuth('callback'));
        $this->assertFalse($controller->requireAuth('post_install'));

        $rows = $this->db->table('api_routes')
            ->select('`from`, is_public', false)
            ->like('from', 'githubapp/', 'after')
            ->get()
            ->getResultArray();

        $this->assertEqualsCanonicalizing([
            'githubapp/callback' => '1',
            'githubapp/post-install' => '1',
        ], array_column($rows, 'is_public', 'from'));
    }

    // <editor-fold desc="The callback, after GitHub made the App">

    public function testTheCallbackRefusesToRunWithoutACode(): void {
        $body = $this->decode($this->get('githubapp/callback'));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('No code provided', $body['error']);
    }

    /**
     * An empty `code` would otherwise be posted to `/app-manifests//conversions`.
     */
    public function testACodeThatIsThereButEmptyIsRefusedToo(): void {
        $body = $this->decode($this->get('githubapp/callback?code='));

        $this->assertSame('No code provided', $body['error']);
        $this->assertSame([], $this->fakes->manifestCodes);
    }

    /**
     * A code with no state kso issued is somebody else's App. GitHub is not asked,
     * and the App already connected is left alone - including by an empty state, which is
     * what every integration not in the middle of a setup has.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('statesKsoDidNotIssue')]
    public function testACodeWithoutAStateKsoIssuedChangesNothing(string $query): void {
        $integration = Fixtures::githubIntegration();
        Fixtures::githubIntegration(['name' => 'mid-setup', 'setup_state' => 'the-real-state']);
        $this->fakes->githubApp = $this->anotherApp();

        $body = $this->decode($this->get("githubapp/callback?code=abc{$query}"));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame([], $this->fakes->manifestCodes, 'GitHub was asked to swap a code nobody started');
        $this->assertSame('the-client-secret', $this->stored($integration->id, 'client_secret'));
        $this->assertSame(815, (int) $this->stored($integration->id, 'installation_id'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function statesKsoDidNotIssue(): array {
        return [
            'no state' => [''],
            'an empty state' => ['&state='],
            'a guessed state' => ['&state=guessed'],
        ];
    }

    /**
     * The whole of the first half: the App GitHub made is stored on the integration that
     * issued the state, any earlier installation is forgotten - it belonged to the App being
     * replaced - and the operator goes straight on to install it, with a new state.
     */
    public function testTheAppIsStoredAndTheOperatorSentOnToInstallIt(): void {
        $integration = Fixtures::githubIntegration(['setup_state' => 'the-state']);
        $this->fakes->githubApp = $this->anotherApp();

        $response = $this->get('githubapp/callback?code=the-code&state=the-state');

        $this->assertSame(['the-code'], $this->fakes->manifestCodes);
        $stored = $this->reload($integration->id);
        $this->assertSame(9999, (int) $stored->app_id);
        $this->assertSame('new-secret', $stored->client_secret);
        $this->assertSame('-----BEGIN NEW KEY-----', $stored->private_key);
        $this->assertSame('kso-new', $stored->slug);
        $this->assertSame(0, (int) $stored->installation_id);

        $this->assertSame(302, $response->response()->getStatusCode());
        $this->assertSame(
            "https://github.com/apps/kso-new/installations/new?state={$stored->setup_state}",
            $response->response()->getHeaderLine('Location')
        );
        $this->assertNotSame('the-state', $stored->setup_state);
    }

    public function testAStateCanOnlyBeUsedOnce(): void {
        Fixtures::githubIntegration(['setup_state' => 'the-state']);
        $this->fakes->githubApp = $this->anotherApp();

        $this->get('githubapp/callback?code=first&state=the-state');
        $body = $this->decode($this->get('githubapp/callback?code=second&state=the-state'));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame(['first'], $this->fakes->manifestCodes);
    }

    /**
     * A refused code is reported, and the state is spent anyway: the operator starts again
     * from kso rather than retrying a link.
     */
    public function testACodeGithubRefusesIsReportedAndTheAppKept(): void {
        $integration = Fixtures::githubIntegration(['setup_state' => 'the-state']);
        $this->fakes->githubApp = null;

        $body = $this->decode($this->get('githubapp/callback?code=used&state=the-state'));

        $this->assertSame('ERROR', $body['status']);
        $this->assertStringContainsString('code already used', $body['error']);
        $this->assertSame('the-client-secret', $this->stored($integration->id, 'client_secret'));
        $this->assertSame('', $this->stored($integration->id, 'setup_state'));
    }

    /**
     * The state picks the row. With two organisations connected, a callback for one must
     * not touch the other.
     */
    public function testOnlyTheIntegrationThatIssuedTheStateIsWritten(): void {
        $other = Fixtures::githubIntegration(['name' => 'other', 'setup_state' => 'other-state']);
        Fixtures::githubIntegration(['name' => 'this', 'setup_state' => 'this-state']);
        $this->fakes->githubApp = $this->anotherApp();

        $this->get('githubapp/callback?code=the-code&state=this-state');

        $this->assertSame(4711, (int) $this->stored($other->id, 'app_id'));
        $this->assertSame('other-state', $this->stored($other->id, 'setup_state'));
    }

    // </editor-fold>

    // <editor-fold desc="Post-install, after GitHub installed the App">

    public function testTheInstallationIsStoredOnTheIntegrationThatIssuedTheState(): void {
        $integration = Fixtures::githubIntegration(['installation_id' => 0, 'setup_state' => 'the-state']);

        $response = $this->get('githubapp/post-install?installation_id=4712&state=the-state');

        $this->assertSame(4712, (int) $this->stored($integration->id, 'installation_id'));
        $this->assertSame('', $this->stored($integration->id, 'setup_state'));
        $this->assertSame(302, $response->response()->getStatusCode());
        $this->assertStringContainsString(
            '/app/integrations/github-integrations?github_install_success=1',
            $response->response()->getHeaderLine('Location')
        );
    }

    /**
     * The organisation comes from the installation, since GitHub says which account it
     * lives on - an App installed in an organisation should not read as a personal one.
     */
    public function testTheOrganisationIsTakenFromTheInstallation(): void {
        $integration = Fixtures::githubIntegration(['organization' => '', 'installation_id' => 0, 'setup_state' => 'the-state']);
        $this->fakes->githubInstallationAccount = 'acme-inc';

        $this->get('githubapp/post-install?installation_id=4712&state=the-state');

        $this->assertSame('acme-inc', $this->stored($integration->id, 'organization'));
    }

    /**
     * The organisation is only a label. GitHub not answering must not cost the installation.
     */
    public function testAGithubThatDoesNotAnswerStillLeavesTheInstallationStored(): void {
        $integration = Fixtures::githubIntegration(['organization' => 'kept', 'installation_id' => 0, 'setup_state' => 'the-state']);
        $this->fakes->githubInstallationAccount = null;

        $this->get('githubapp/post-install?installation_id=4712&state=the-state');

        $this->assertSame(4712, (int) $this->stored($integration->id, 'installation_id'));
        $this->assertSame('kept', $this->stored($integration->id, 'organization'));
    }

    /**
     * The half of the old takeover that was a single link: a public endpoint that took the
     * installation id from the query string. Without the state kso issued it now writes
     * nothing - and does not tell the browser it worked.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('statesKsoDidNotIssue')]
    public function testALinkCanNoLongerRepointTheInstallation(string $query): void {
        $integration = Fixtures::githubIntegration();
        Fixtures::githubIntegration(['name' => 'mid-setup', 'setup_state' => 'the-real-state']);

        $response = $this->get("githubapp/post-install?installation_id=1{$query}");

        $this->assertSame(815, (int) $this->stored($integration->id, 'installation_id'));
        $this->assertSame(302, $response->response()->getStatusCode());
        $this->assertStringNotContainsString('success', $response->response()->getHeaderLine('Location'));
    }

    /**
     * GitHub sends the operator here after changing which repositories an installation
     * sees, with no state. That is not a disconnect, and the operator lands back in kso.
     */
    public function testAVisitWithoutAnInstallationIdLeavesTheInstallationAlone(): void {
        $integration = Fixtures::githubIntegration(['setup_state' => 'the-state']);

        $response = $this->get('githubapp/post-install?state=the-state');

        $this->assertSame(815, (int) $this->stored($integration->id, 'installation_id'));
        $this->assertStringContainsString('/app/integrations/github-integrations', $response->response()->getHeaderLine('Location'));
    }

    // </editor-fold>

    /**
     * @return array<string, mixed>
     */
    private function anotherApp(): array {
        return [
            'id' => 9999,
            'client_id' => 'Iv1.new',
            'client_secret' => 'new-secret',
            'pem' => '-----BEGIN NEW KEY-----',
            'webhook_secret' => 'new-webhook-secret',
            'slug' => 'kso-new',
        ];
    }

    private function reload(int $id): GithubIntegration {
        $item = new GithubIntegration();
        $item->find($id);
        return $item;
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
