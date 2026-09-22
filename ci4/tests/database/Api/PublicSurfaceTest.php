<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use RestExtension\Exceptions\UnauthorizedException;

/**
 * What the API answers without a token.
 *
 * `GET /settings` was once reachable without authentication and handed back the
 * GitHub App private key. Nothing in the code said it was public, and nothing failed when
 * it was - so this is the guard that would have caught it.
 *
 * **Authorization is data, not code.** The runtime check is `api_routes.is_public`, a
 * column, written by migrations. The controller's own `requireAuth()` has **no callers at
 * all** - not in `app/`, not in `vendor/`, and not in `SyncModelsAndApi` or `ApiParser`,
 * which is what this comment used to claim. Fourteen controllers implement it and nothing
 * reads it, so it is documentation, not a gate: fixing a public endpoint by editing
 * `requireAuth()` changes nothing. Reading a controller therefore does not tell you whether
 * its endpoint is open; the table does. That is also why the list below is pinned rather
 * than derived - a route that turns public has to be a deliberate edit here.
 */
class PublicSurfaceTest extends ControllerTestCase {

    /**
     * Every route that answers without a token, exactly.
     *
     * Adding one is a security decision. If this test fails because you added an endpoint,
     * the question to answer first is what it gives away to someone who is not logged in.
     */
    private const PUBLIC_ROUTES = [
        // The image registries call these when a new tag is pushed. Public, but each call
        // has to carry its connection's webhook secret - see AutoUpdateWebhooksApiTest.
        'post auto-updates/webhooks/azure-container-registry/([0-9]+)',
        'post auto-updates/webhooks/harbor/([0-9]+)',

        // GitHub redirects a browser back to these while an app is set up. Each acts only
        // with the state nonce kso issued - see GithubAppApiTest.
        'get githubapp/callback',
        'get githubapp/post-install',

        'get home',

        // The cron runner, called once a minute by a CronJob the chart installs. Public in
        // the table because the caller has no access token - it is a curl container inside
        // the cluster - but every call has to carry `CRON_TOKEN`, see JobbyApiTest. The
        // by-id route next to it is gone: nothing reached it over HTTP.
        'get jobby',

        // Signing in, which by definition happens without a token.
        'get login',
        'post login',
        'get login/forgotPassword',
        'post login/forgotPassword',
        'get login/renewPassword',
        'post login/renewPassword',
        'get login/resetPassword',
        'post login/resetPassword',
        'get login/success',
        'get login/twoFactor',
        'post login/twoFactor',

        // A migration job reports its own progress from inside the cluster, with no sign-in -
        // it sends a token of its own, issued for that job (MigrationJobs::TokenHeader).
        'put migration-jobs/([0-9]+)/ended',
        'put migration-jobs/([0-9]+)/started',

        // Acts on the refresh token cookie the browser sends, not on a sign-in.
        'post oauth-agent/logout',
        'post oauth-agent/refresh',
        'post oauth-agent/token',

        // Read by the frontend before anyone signs in. Allow-listed, see System::toPublicArray().
        'get settings',

        'get swagger',
        'get swagger/openapi',
    ];

    /**
     * Secret-bearing endpoints, named individually rather than derived, so that a route
     * disappearing from the table does not quietly empty this list.
     */
    private const MUST_BE_CLOSED = [
        'container_images',
        'database_services',
        'email_services',
        'podio_integrations',
        'users',
        'migration_jobs',
        'deployments',
        'workspaces',
        'deployment_specifications',
    ];

    public function testThePublicSurfaceIsExactlyWhatWeExpect(): void {
        $public = $this->db->table('api_routes')
            ->select('method, `from`', false)
            ->where('is_public', 1)
            ->get()
            ->getResultArray();

        $actual = array_map(
            static fn (array $row) => strtolower($row['method']) . ' ' . $row['from'],
            $public
        );

        sort($actual);
        $expected = self::PUBLIC_ROUTES;
        sort($expected);

        $this->assertSame($expected, $actual);
    }

    /**
     * The table says these need a token. This sends a real request to prove the hook acts
     * on it, rather than trusting the column.
     */
    public function testEndpointsWithSecretsRefuseAnUnauthenticatedRequest(): void {
        foreach (self::MUST_BE_CLOSED as $path) {
            try {
                $response = $this->get($path);
                $this->fail("{$path} answered without a token: " . $response->response()->getStatusCode());
            } catch (UnauthorizedException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * The endpoint that once leaked the GitHub App private key. It is public on purpose - the
     * frontend reads it before anyone signs in - so what it returns is the whole of its
     * security.
     */
    public function testSettingsReturnsTheAllowListAndNothingElse(): void {
        $body = json_decode((string) $this->get('settings')->response()->getBody(), true);

        $this->assertSame(
            ['system', 'pushServiceUrl', 'certManagerIssuerDefaultName', 'imagePullSecretDefaultName'],
            array_keys($body)
        );

        $this->assertSame([
            'id',
            'is_network_nginx_ingress_supported',
            'is_network_istio_supported',
            'is_network_contour_supported',
            'is_network_gateway_api_supported',
            'hosting_provider',
        ], array_keys($body['system']));
    }

    /**
     * The same check by shape rather than by name, so a credential added to the System
     * entity under a name nobody thought of is still caught.
     */
    public function testSettingsCarriesNothingThatLooksLikeACredential(): void {
        $body = (string) $this->get('settings')->response()->getBody();
        $keys = array_keys(json_decode($body, true)['system']);

        foreach ($keys as $key) {
            $this->assertDoesNotMatchRegularExpression(
                '/secret|private_key|credential|password|token/i',
                $key,
                "settings returned a field called {$key}"
            );
        }
    }

    /**
     * An error response carries its own HTTP status code, not just a status in the body.
     *
     * Most of the API reports failure as HTTP 200 with `status: ERROR` - `fail()` defaults
     * to 200 - so a client cannot read success off the status line alone. This endpoint is
     * one of the few that passes a real code, and it is the reachable one: the token
     * exchange calls back to its own host, which in a test is a name that does not resolve,
     * so it always takes the failure branch.
     */
    public function testAFailureCanCarryItsOwnHttpStatus(): void {
        $response = $this->withBodyFormat('json')->post('oauth-agent/token', ['code' => 'nope']);

        $this->assertSame(400, $response->response()->getStatusCode());

        $body = json_decode((string) $response->response()->getBody(), true);
        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('failed to fetch access token', $body['error']);
    }

    /**
     * The types matter to the caller. These come out of the database as strings, and "0"
     * is true in JavaScript - a boolean flag returned as a string would turn every one of
     * these on in the frontend.
     */
    public function testSettingsReturnsFlagsAsBooleansAndIdsAsNumbers(): void {
        $system = json_decode((string) $this->get('settings')->response()->getBody(), true)['system'];

        $this->assertIsBool($system['is_network_gateway_api_supported']);
        $this->assertIsBool($system['is_network_nginx_ingress_supported']);
        $this->assertIsInt($system['id']);
    }

}
