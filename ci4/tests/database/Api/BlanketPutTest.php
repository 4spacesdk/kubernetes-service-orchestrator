<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * `PUT /<resource>/{id}` on the three resources that used to have one.
 *
 * The route generator makes CRUD routes for every resource controller, and a controller
 * that declares no `put()` of its own gets the trait's. That one replaces the **whole** row:
 * `populatePut()` writes every column, and every column the request left out becomes null.
 * On these three that is a client's secret, a user's password and a gateway's name.
 *
 * Nothing in kso ever called them - every update goes through PATCH - but all six were
 * generated into the API client and published in the OpenAPI document, which is where a
 * caller would find them.
 *
 * Neither did what its shape promised, which is worth knowing when reading the migration:
 * `PUT /o_auth_clients/{id}` answered `200 OK` and wrote a *new* row with an empty client id
 * and an empty secret rather than touching the one it named, and `PUT /users/{id}` ended as
 * `Column 'last_name' cannot be null` with the row it was asked about gone from the table.
 * So the endpoints were not a way to replace a resource; they were a way to damage one.
 */
class BlanketPutTest extends ControllerTestCase {

    /**
     * The by-id route was reachable even though the resource is keyed by a string:
     * `OAuthClient::post()` takes `client_id` from the caller, so a purely numeric one is
     * there for the asking, and then `o_auth_clients/([0-9]+)` matches.
     */
    public function testAClientCannotBePutAndKeepsItsSecret(): void {
        $this->db->table('oauth_clients')->insert([
            'client_id' => '12345',
            'client_secret' => 'the-secret',
            'redirect_uri' => 'https://app.invalid/callback',
            'grant_types' => 'client_credentials',
            'scope' => '',
            'user_id' => '',
        ]);

        $this->assertPutIsNotRouted('o_auth_clients/12345', ['redirect_uri' => 'https://elsewhere.invalid/']);

        $row = $this->db->table('oauth_clients')->where('client_id', '12345')->get()->getRowArray();
        $this->assertSame('the-secret', $row['client_secret']);
        $this->assertSame('https://app.invalid/callback', $row['redirect_uri']);
    }

    public function testAUserCannotBePutAndIsStillThere(): void {
        $user = Fixtures::user(['username' => 'kept', 'first_name' => 'Kept', 'last_name' => 'User']);

        $this->assertPutIsNotRouted('users/' . $user->id, ['first_name' => 'Taken']);

        $row = $this->db->table('users')->where('id', $user->id)->get()->getRowArray();
        $this->assertSame('Kept', $row['first_name']);
        $this->assertNotSame('', (string) $row['password']);
    }

    public function testAGatewayCannotBePut(): void {
        $gateway = Fixtures::gateway(['name' => 'kept']);

        $this->assertPutIsNotRouted('gateways/' . $gateway->id, ['namespace' => 'elsewhere']);

        $this->assertSame('kept', $this->db->table('gateways')->where('id', $gateway->id)->get()->getRowArray()['name']);
    }

    /**
     * A route the table no longer carries falls through to CodeIgniter's auto routing, which
     * finds no controller method of that name and raises `PageNotFoundException` - a 404.
     *
     * Asserted rather than left to `expectException()` so the test can go on and look at the
     * row afterwards, which is the half that matters: a refusal that still wrote would pass
     * the status check.
     *
     * @param array<string, mixed> $body
     */
    private function assertPutIsNotRouted(string $path, array $body): void {
        try {
            $response = $this->signedIn()->withBody(json_encode($body))->call('put', $path);
        } catch (PageNotFoundException $e) {
            return;
        }

        $this->fail("PUT {$path} was answered with {$response->response()->getStatusCode()}; it should not be routed");
    }

}
