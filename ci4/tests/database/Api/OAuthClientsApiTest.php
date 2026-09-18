<?php namespace App\Tests\Database\Api;

use App\Entities\OAuthClient;
use App\ControllerTestCase;
use RestExtension\Exceptions\UnauthorizedException;

/**
 * The OAuth clients themselves, managed over the API.
 *
 * Every row in `oauth_clients` is something that can be exchanged for an access token, so
 * this endpoint is the one that hands out and takes away the right to call everything else.
 * The controller adds nothing to `ResourceController` but three overrides that exist only
 * to widen the route from `{id}` to a string - the primary key here is `client_id`, not an
 * integer - and that widening is what these hold: an id that is not a number has to reach
 * the controller rather than fall off the route.
 *
 * **What is not asserted is the secret.** `client_secret` comes back in the response, which
 * is written up below rather than fixed here.
 */
class OAuthClientsApiTest extends ControllerTestCase {

    /**
     * Not public, in the code and in the table. Worth holding for this controller above all
     * others: an unauthenticated `delete` here would let a stranger take away the client
     * every signed-in session is issued against.
     */
    public function testNoRouteToAClientIsPublic(): void {
        $controller = new \App\Controllers\OAuthClients();

        $this->assertTrue($controller->requireAuth('get'));
        $this->assertTrue($controller->requireAuth('patch'));
        $this->assertTrue($controller->requireAuth('delete'));

        // `from` is a reserved word, so the column is quoted by hand and the rows are
        // picked out here rather than in a where clause.
        $rows = $this->db->table('api_routes')
            ->select('`from`, is_public', false)
            ->get()
            ->getResultArray();

        $clientRoutes = array_filter(
            $rows,
            static fn (array $row) => str_starts_with($row['from'], 'o_auth_clients')
        );

        $this->assertNotEmpty($clientRoutes, 'the routes are gone, not closed');
        foreach ($clientRoutes as $row) {
            $this->assertSame(0, (int) $row['is_public'], "{$row['from']} is public");
        }
    }

    public function testAnUnauthenticatedCallerIsRefused(): void {
        $this->expectException(UnauthorizedException::class);

        $this->get('o_auth_clients');
    }

    /**
     * The whole point of the three overrides: `client_id` is a string, and the generated
     * route for it is `o_auth_clients/(.*)` rather than `([0-9]+)`. A client id built from
     * random bytes contains letters, so without the widening the request would not route at
     * all - and the endpoint would appear to work for as long as every test used a numeric
     * id.
     */
    public function testAClientIsFetchedByItsNonNumericId(): void {
        $client = $this->client();

        $body = $this->decode($this->signedIn()->get("o_auth_clients/{$client->client_id}"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame($client->client_id, $body['resource']['client_id']);
        $this->assertSame('https://example.org/callback', $body['resource']['redirect_uri']);
    }

    /**
     * **Today's behaviour, not an endorsement.** The client secret is returned in full to
     * anyone who may list clients. It is not hidden the way `User::$hiddenFields` hides a
     * password hash, and a listing is a GET - so it lands in whatever logs and caches sit
     * between the API and the browser. Pinned so that hiding it is a visible change.
     */
    public function testTheClientSecretIsHandedBackInFull(): void {
        $client = $this->client();

        $body = $this->decode($this->signedIn()->get("o_auth_clients/{$client->client_id}"));

        $this->assertSame(
            $client->client_secret,
            $body['resource']['client_secret'],
            'the secret is hidden now - this test has done its job'
        );
    }

    public function testPatchingChangesTheRedirectUri(): void {
        $client = $this->client();

        $body = $this->decode(
            $this->withBodyFormat('json')->signedIn()->patch("o_auth_clients/{$client->client_id}", [
                'redirect_uri' => 'https://example.org/somewhere-else',
            ])
        );

        $this->assertSame('OK', $body['status']);
        $this->assertSame('https://example.org/somewhere-else', $this->row($client->client_id)['redirect_uri']);
    }

    /**
     * `oauth_clients` has no `deletion_id` column, so this is a real delete rather than the
     * soft delete the rest of kso uses - the row is gone, and every token issued against it
     * stops verifying. Asserted against the table rather than against the response, because
     * the response carries the entity as it was before it was removed.
     */
    public function testDeletingRemovesTheRowOutright(): void {
        $client = $this->client();

        $body = $this->decode($this->signedIn()->delete("o_auth_clients/{$client->client_id}"));

        $this->assertSame('OK', $body['status']);
        $this->assertNull($this->row($client->client_id));
    }

    /**
     * Deleting something that is not there is answered with success and an empty resource,
     * as everywhere else in `ResourceController`. Held because the id is a string here: a
     * typo in a client id is not a 404, it is a cheerful nothing.
     */
    public function testDeletingAClientThatIsNotThereSaysNothingWentWrong(): void {
        $body = $this->decode($this->signedIn()->delete('o_auth_clients/no-such-client'));

        $this->assertSame('OK', $body['status']);
    }

    // <editor-fold desc="Fixtures">

    /**
     * A client of this test's own.
     *
     * Never the one `ControllerTestCase` signs in with: these tests delete what they make,
     * and taking that one away would unauthenticate the run itself.
     */
    private function client(): OAuthClient {
        $client = new OAuthClient();
        $client->client_id = 'test-client-' . bin2hex(random_bytes(4));
        $client->client_secret = 'test-secret';
        $client->redirect_uri = 'https://example.org/callback';
        $client->grant_types = 'authorization_code';
        $client->scope = '';
        $client->user_id = (string) $this->signedInUserId();
        $client->insert();

        return $client;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(string $clientId): ?array {
        return $this->db->table('oauth_clients')->where('client_id', $clientId)->get()->getRowArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
