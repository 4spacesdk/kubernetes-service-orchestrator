<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\System;
use App\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use RestExtension\Exceptions\UnauthorizedException;

/**
 * How an operator connects this installation to a GitHub App, and what it accepts on the
 * way.
 *
 * Four endpoints, three of them public. `callback` and `post-install` have to be: GitHub
 * redirects a browser to them once the app is created and installed, and a browser
 * arriving from GitHub carries no token. `repositories` does not have to be, and should
 * not be - see SEC-10. `manifest` is the only one that asks for a token.
 *
 * The three steps are a chain, and each one writes to the single `System` row every other
 * part of kso reads its GitHub credentials from. That is what makes the refusals worth
 * this much attention: the endpoints are reachable by anyone who can get the operator's
 * browser to follow a link, so whatever they accept is what an attacker gets to choose.
 *
 * Only what can be decided without a network is covered. The manifest conversion and the
 * repository listing both build their own HTTP client rather than going through
 * `service('integrations')`, so there is nothing a test can put in their place - they are
 * marked in the controller and reported as a missing seam.
 */
class GithubAppApiTest extends ControllerTestCase {

    /**
     * What the controller declares about itself, held next to what is actually enforced.
     *
     * `requireAuth()` is the controller author's statement of which methods may be reached
     * without a token, and it names two: the callback and the post-install redirect that
     * GitHub sends a browser to. Nothing calls it at runtime - authorization is the
     * `is_public` column, written once by the migration - and the migration marked
     * `repositories` public as well.
     *
     * So the controller and the table disagree, and the table wins. That disagreement is
     * where SEC-10 came from, and both halves of it are asserted here so that closing the
     * gap from either side is visible: fix the row and the last assertion fails, fix the
     * declaration and the third one does.
     */
    public function testTheControllerAsksForATokenOnRepositoriesAndTheTableOverridesIt(): void {
        $controller = new \App\Controllers\GithubApp();

        $this->assertFalse($controller->requireAuth('callback'), 'GitHub redirects a browser here with no token');
        $this->assertFalse($controller->requireAuth('post_install'), 'so does the install redirect');
        $this->assertTrue($controller->requireAuth('repositories'), 'the controller no longer asks for a token');
        $this->assertTrue($controller->requireAuth('manifest'));

        // `from` is a reserved word, so the column is quoted by hand and the row is picked
        // out here rather than in a where clause.
        $rows = $this->db->table('api_routes')
            ->select('`from`, is_public', false)
            ->where('method', 'get')
            ->get()
            ->getResultArray();

        $public = array_column($rows, 'is_public', 'from');

        $this->assertSame(
            1,
            (int) $public['githubapp/repositories'],
            'the route is closed now - SEC-10 is fixed and this test has done its job'
        );
    }

    // <editor-fold desc="The manifest an operator hands to GitHub">

    /**
     * The manifest is the permission request GitHub shows the operator, and it is the only
     * chance to ask for less. Read on contents and metadata is enough to look up a commit
     * message and a SHA, which is all kso does with GitHub; anything writable here would be
     * granted across every repository the app is installed on, for ever, with nobody asked
     * again.
     */
    public function testTheManifestAsksForNothingItCanWriteWith(): void {
        $manifest = $this->decode($this->signedIn()->get('githubapp/manifest'));

        $this->assertSame([
            'contents' => 'read',
            'metadata' => 'read',
        ], $manifest['default_permissions']);
    }

    /**
     * A public GitHub App can be installed on any account that finds it, and every
     * installation of it would be one this instance holds a private key for. The app
     * describes one customer's orchestrator, so it stays private to the organisation that
     * created it.
     */
    public function testTheManifestKeepsTheAppPrivate(): void {
        $manifest = $this->decode($this->signedIn()->get('githubapp/manifest'));

        $this->assertFalse($manifest['public'], 'the app would be installable by strangers');
    }

    /**
     * The two urls in the manifest are where GitHub sends the operator's browser next, and
     * they are the only thing tying the app it creates back to this installation. Pointing
     * them at another host would hand that host the client secret and the private key, so
     * they are asserted against the same base the manifest reports rather than against a
     * literal - which is also what keeps this test out of the developer's own environment.
     */
    public function testTheManifestSendsGithubBackToThisInstallation(): void {
        $manifest = $this->decode($this->signedIn()->get('githubapp/manifest'));

        $this->assertSame($manifest['url'] . '/githubapp/callback', $manifest['redirect_url']);
        $this->assertSame($manifest['url'] . '/githubapp/post-install', $manifest['setup_url']);
        $this->assertStringStartsWith('KSO - ', $manifest['name']);
    }

    /**
     * The one GitHub App endpoint that is not public, and the contrast is the whole of
     * SEC-10: the endpoint that only describes an app is closed, while the endpoint that
     * mints an installation token and lists the organisation's repositories is open.
     *
     * Pinned as today's behaviour, not endorsed. The day `repositories` starts asking for a
     * token this test goes red, and that failure is the fix landing.
     */
    public function testTheHarmlessEndpointNeedsATokenAndTheDangerousOneDoesNot(): void {
        Fixtures::system([
            'github_app_installation_id' => 0,
            'github_app_id' => 0,
            'github_app_private_key' => '',
        ]);

        $refused = false;
        try {
            $this->get('githubapp/manifest');
        } catch (UnauthorizedException) {
            $refused = true;
        }
        $this->assertTrue($refused, 'the manifest, which gives nothing away, answered without a token');

        $body = $this->decode($this->get('githubapp/repositories'));
        $this->assertSame(
            'No installation_id provided',
            $body['error'],
            'repositories was answered by the controller, so it is still open - SEC-10'
        );
    }

    // </editor-fold>

    // <editor-fold desc="The callback GitHub redirects the operator's browser to">

    public function testTheCallbackRefusesToRunWithoutACode(): void {
        $body = $this->decode($this->get('githubapp/callback'));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('No code provided', $body['error']);
    }

    /**
     * An empty `code` is a different input from a missing one and has to be refused for a
     * different reason: it is truthy enough to look like a parameter and falsy enough to
     * be caught, and if it were not caught the conversion would be posted to
     * `/app-manifests//conversions` - a url with a hole in it, whose response nobody has
     * thought about.
     *
     * The refusal is answered with HTTP 200 carrying `status: ERROR`, because `fail()`
     * defaults to 200. That matters here more than elsewhere: the reader is a browser that
     * GitHub redirected, and it is shown a success status line for a failure.
     */
    public function testACodeThatIsThereButEmptyIsRefusedToo(): void {
        $response = $this->get('githubapp/callback?code=');

        $this->assertSame(200, $response->response()->getStatusCode());

        $body = $this->decode($response);
        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('No code provided', $body['error']);
    }

    /**
     * The refusal that used to be decorative. `fail()` ended the process, so the method
     * stopped there by accident; now it returns, and this is what proves it still stops -
     * without it the next line would post an empty code to GitHub. See FEAT-9.
     */
    public function testTheCallbackDoesNotCarryOnAfterRefusing(): void {
        $system = Fixtures::system(['github_app_id' => 4242]);

        $this->get('githubapp/callback');

        $stored = \App\Entities\System::Get();
        $this->assertSame(4242, (int) $stored->github_app_id, 'the callback wrote over the stored app');
    }

    /**
     * There is nothing in the callback that knows an app is already connected, and nothing
     * carrying a nonce between the manifest and the code coming back. The refusal here
     * names the missing code and not the app already on file, which is how you can tell:
     * an operator who follows a prepared link with a valid code on it would have the
     * working app's id, client secret and private key replaced, and every deployment that
     * reads a commit message would start asking a stranger's app instead.
     *
     * Every credential is asserted, not just the id, because they are written one after
     * another and a partial overwrite leaves an installation that cannot be signed for.
     */
    public function testTheCallbackHasNoIdeaThatAnAppIsAlreadyConnected(): void {
        Fixtures::system([
            'github_app_id' => 4242,
            'github_app_client_id' => 'Iv1.connected',
            'github_app_client_secret' => 'the-one-in-use',
            'github_app_private_key' => 'the-key-in-use',
            'github_app_slug' => 'kso-connected',
        ]);

        $body = $this->decode($this->get('githubapp/callback'));

        $this->assertSame('No code provided', $body['error'], 'the refusal was about something other than the code');

        $stored = System::Get();
        $this->assertSame('Iv1.connected', $stored->github_app_client_id);
        $this->assertSame('the-one-in-use', $stored->github_app_client_secret);
        $this->assertSame('the-key-in-use', $stored->github_app_private_key);
        $this->assertSame('kso-connected', $stored->github_app_slug);
    }

    // </editor-fold>

    // <editor-fold desc="Where GitHub sends the operator after installing">

    /**
     * The last step of connecting an app: GitHub sends the operator to `setup_url` with the
     * id of the installation it just created, and that id is what every later token is
     * minted for. Storing it is the whole of the endpoint, and the redirect afterwards is
     * what puts the operator back in front of the settings page instead of a blank JSON
     * document.
     *
     * The host is not asserted - it comes from the environment the instance runs in - only
     * the page and the flag the frontend reads to say the connection worked.
     */
    public function testTheInstallationGithubJustCreatedIsStored(): void {
        Fixtures::system(['github_app_installation_id' => 0]);

        $response = $this->get('githubapp/post-install?installation_id=4711');

        $this->assertSame(4711, (int) System::Get()->github_app_installation_id);
        $this->assertSame(302, $response->response()->getStatusCode());
        $this->assertStringContainsString(
            '/app/setup/system?github_install_success=1',
            $response->response()->getHeaderLine('Location')
        );
    }

    /**
     * GitHub sends the operator to the same url after *changing* an installation's
     * repository selection, and that visit carries no `installation_id`. Treating it as a
     * disconnect would leave a working app with installation 0, so every version lookup
     * would stop until somebody reinstalled - and the operator would have seen a success
     * page while it happened.
     */
    public function testAVisitWithoutAnInstallationIdLeavesTheConnectedOneAlone(): void {
        Fixtures::system(['github_app_installation_id' => 8080]);

        $response = $this->get('githubapp/post-install');

        $this->assertSame(8080, (int) System::Get()->github_app_installation_id);
        $this->assertSame(302, $response->response()->getStatusCode(), 'the operator was left on a JSON page');
    }

    /**
     * Pinned as today's behaviour, and it is the sharp edge next to SEC-10: the endpoint is
     * public, takes the installation id from the query string, and does not check with
     * GitHub that this installation belongs to this app. Anyone who gets the operator's
     * browser to open one link repoints the orchestrator at an installation they control,
     * and the operator is shown the success page while it happens.
     *
     * Combined with `repositories` taking the same parameter, the two together are a way to
     * read a private repository list through someone else's orchestrator.
     */
    public function testAnyoneWhoCanOpenALinkCanRepointTheInstallation(): void {
        Fixtures::system(['github_app_installation_id' => 8080]);

        $this->get('githubapp/post-install?installation_id=1');

        $this->assertSame(
            1,
            (int) System::Get()->github_app_installation_id,
            'the installation id is now verified - SEC-10 has moved'
        );
    }

    // </editor-fold>

    // <editor-fold desc="Listing the repositories of an installation">

    /**
     * Without an installation there is nothing to list, and the endpoint says so rather
     * than reaching for the network.
     */
    public function testListingRepositoriesNeedsAnInstallation(): void {
        Fixtures::system(['github_app_installation_id' => 0]);

        $body = $this->decode($this->get('githubapp/repositories'));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('No installation_id provided', $body['error']);
    }

    /**
     * With an installation but no app credentials it stops at the next check. Both
     * refusals happen before any GitHub call, which is what makes them testable offline.
     */
    public function testListingRepositoriesNeedsAConfiguredApp(): void {
        Fixtures::system([
            'github_app_installation_id' => 12345,
            'github_app_id' => 0,
            'github_app_private_key' => '',
        ]);

        $body = $this->decode($this->get('githubapp/repositories'));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('GitHub App not configured', $body['error']);
    }

    /**
     * Half a configuration is not a configuration, and both halves have to be checked
     * separately. An app id with no private key would go on to sign a JWT with an empty
     * string; a private key with no app id would sign one with no issuer. Either way the
     * refusal would happen at GitHub instead of here, which means a network call from an
     * endpoint anyone can reach.
     */
    #[DataProvider('theHalvesOfAGithubAppConfiguration')]
    public function testAnAppThatIsOnlyHalfConfiguredIsStillRefused(array $system): void {
        Fixtures::system(array_merge(['github_app_installation_id' => 12345], $system));

        $body = $this->decode($this->get('githubapp/repositories'));

        $this->assertSame('GitHub App not configured', $body['error']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function theHalvesOfAGithubAppConfiguration(): array {
        return [
            'a key but no app id' => [['github_app_id' => 0, 'github_app_private_key' => 'a-key']],
            'an app id but no key' => [['github_app_id' => 99, 'github_app_private_key' => '']],
        ];
    }

    /**
     * The installation id is read from the query string before the stored one, so this
     * endpoint will mint a token for whatever installation a caller names. Held here
     * because it is the part of SEC-10 that is easy to miss.
     */
    public function testTheInstallationIdCanBeSuppliedByTheCaller(): void {
        Fixtures::system([
            'github_app_installation_id' => 0,
            'github_app_id' => 0,
            'github_app_private_key' => '',
        ]);

        $body = $this->decode($this->get('githubapp/repositories?installation_id=999'));

        // Past the installation check it could not have reached, on to the next one.
        $this->assertSame('GitHub App not configured', $body['error']);
    }

    // </editor-fold>

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

}
