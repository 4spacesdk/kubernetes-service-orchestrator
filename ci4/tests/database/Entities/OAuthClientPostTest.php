<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\OAuthClient;
use App\Entities\User;

/**
 * How an OAuth client comes into being.
 *
 * `OAuthClient::post()` is the override `ResourceControllerTrait::post()` calls for
 * `POST /o_auth_clients`, and it is the only place in kso that mints a credential pair. It
 * does three things the generic create does not: it can create the user in the same call,
 * it refuses to make a second client for a user that already has one with the same grant
 * type, and it carries the client id around the insert by hand.
 *
 * That last one is dead weight today - see `testTheEntityStillKnowsItsIdAfterTheInsert` -
 * but the behaviour it was written to protect is real enough to hold on to:
 * `oauth_clients` has a string primary key, and a caller handed back a client without its
 * id has just created a credential nobody can use.
 *
 * **No credential in this file is a real one.** Every id and secret is generated here, and
 * nothing is read from the environment. That `client_secret` comes back to the caller in
 * cleartext is pinned in `OAuthClientsApiTest`, not here.
 */
class OAuthClientPostTest extends DatabaseTestCase {

    // <editor-fold desc="The credentials">

    /**
     * Nothing given, so both halves are minted: sixteen random bytes each, hex encoded.
     * The other two columns are written empty rather than left out - `oauth_clients` has no
     * nullable column in it.
     */
    public function testAClientWithNothingGivenGetsGeneratedCredentials(): void {
        $client = OAuthClient::post([]);

        $row = $this->row($client->client_id);
        $this->assertNotNull($row, 'nothing was written');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $row['client_id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $row['client_secret']);
        $this->assertSame('', $row['grant_types']);
        $this->assertSame('', $row['redirect_uri']);
    }

    /**
     * Two clients created the same way are two different credentials. A generator that
     * handed out the same pair twice would pass every other test in this file.
     */
    public function testTwoGeneratedClientsShareNothing(): void {
        $first = OAuthClient::post([]);
        $second = OAuthClient::post([]);

        $this->assertNotSame($first->client_id, $second->client_id);
        $this->assertNotSame($first->client_secret, $second->client_secret);
    }

    /**
     * What is given is what is written, all four fields.
     */
    public function testWhatIsGivenIsWrittenAsItIs(): void {
        $given = $this->credentials();

        $client = OAuthClient::post([
            'client_id' => $given['client_id'],
            'client_secret' => $given['client_secret'],
            'grant_types' => 'authorization_code',
            'redirect_uri' => 'https://example.org/callback',
        ]);

        $this->assertSame($given['client_id'], $client->client_id);
        $this->assertSame([
            'client_id' => $given['client_id'],
            'client_secret' => $given['client_secret'],
            'grant_types' => 'authorization_code',
            'redirect_uri' => 'https://example.org/callback',
        ], $this->row($given['client_id']));
    }

    /**
     * The id survives the insert, which is what the response is built from.
     *
     * `post()` saves `client_id` into a local before `insert()` and writes it back after -
     * guarding against `EntityTrait::insert()` overwriting a string primary key with an
     * insert id. It does not: the ORM only assigns the key when the model returns a
     * non-bool, and `Model::insert()` returns a bool. Removing both lines changes nothing
     * measurable, which is written up rather than tidied away here. The behaviour this
     * asserts is the one the caller depends on either way.
     */
    public function testTheEntityStillKnowsItsIdAfterTheInsert(): void {
        $given = $this->credentials();

        $client = OAuthClient::post(['client_id' => $given['client_id']]);

        $this->assertSame($given['client_id'], $client->client_id);
        $this->assertSame($given['client_id'], $client->toArray()['client_id']);
    }

    // </editor-fold>

    // <editor-fold desc="The user">

    /**
     * A user id given is written on the client. No grant type here, so the uniqueness check
     * below is not reached at all.
     */
    public function testAGivenUserIdIsWrittenOnTheClient(): void {
        $user = $this->aUser();

        $client = OAuthClient::post(['user_id' => (string) $user->id]);

        $this->assertSame((string) $user->id, $this->rowWithUser($client->client_id)['user_id']);
    }

    /**
     * A user can be created in the same call, which is how the portal creates a login: one
     * request, a user and the client it authenticates with.
     */
    public function testANestedUserIsCreatedAndTheClientPointsAtIt(): void {
        $username = $this->aUsername();

        $client = OAuthClient::post(['user' => ['username' => $username, 'first_name' => 'Sweep']]);

        $userId = (int) $this->db->table('users')->where('username', $username)->get()->getRowArray()['id'];
        $this->assertGreaterThan(0, $userId);
        $this->assertSame((string) $userId, $this->rowWithUser($client->client_id)['user_id']);
    }

    /**
     * A nested user wins over a user id given alongside it - the nested one is read second
     * and overwrites. Worth holding because both keys reaching the method is not far
     * fetched: the portal sends the user object, and a caller copying an existing client
     * sends the id.
     */
    public function testANestedUserWinsOverAUserIdGivenBesideIt(): void {
        $other = $this->aUser();
        $username = $this->aUsername();

        $client = OAuthClient::post([
            'user_id' => (string) $other->id,
            'user' => ['username' => $username, 'first_name' => 'Sweep'],
        ]);

        $userId = (int) $this->db->table('users')->where('username', $username)->get()->getRowArray()['id'];
        $this->assertSame((string) $userId, $this->rowWithUser($client->client_id)['user_id']);
    }

    // </editor-fold>

    // <editor-fold desc="One client per user and grant type">

    /**
     * The check the comment in the method calls "unique portal oauth clients": a user who
     * already has a client for this grant type is handed that one back, and nothing is
     * written. Without it every sign-in through the portal would mint another credential
     * against the same user, and each one would keep working.
     */
    public function testAUserWhoAlreadyHasAClientForThisGrantTypeGetsThatOneBack(): void {
        $user = $this->aUser();
        $first = OAuthClient::post(['user_id' => (string) $user->id, 'grant_types' => 'client_credentials']);

        $second = OAuthClient::post(['user_id' => (string) $user->id, 'grant_types' => 'client_credentials']);

        $this->assertSame($first->client_id, $second->client_id);
        $this->assertSame($first->client_secret, $second->client_secret, 'the secret was minted again');
        $this->assertSame(1, $this->countClientsOf((string) $user->id));
    }

    /**
     * It is one client per grant type, not one per user. The same user asking for a
     * different grant type gets a client of their own for it.
     */
    public function testTheSameUserWithADifferentGrantTypeGetsASecondClient(): void {
        $user = $this->aUser();
        $first = OAuthClient::post(['user_id' => (string) $user->id, 'grant_types' => 'client_credentials']);

        $second = OAuthClient::post(['user_id' => (string) $user->id, 'grant_types' => 'authorization_code']);

        $this->assertNotSame($first->client_id, $second->client_id);
        $this->assertSame(2, $this->countClientsOf((string) $user->id));
    }

    /**
     * And it is per user: the same grant type for somebody else is somebody else's client.
     */
    public function testAnotherUserWithTheSameGrantTypeGetsTheirOwnClient(): void {
        $one = $this->aUser();
        $another = $this->aUser();
        $first = OAuthClient::post(['user_id' => (string) $one->id, 'grant_types' => 'client_credentials']);

        $second = OAuthClient::post(['user_id' => (string) $another->id, 'grant_types' => 'client_credentials']);

        $this->assertNotSame($first->client_id, $second->client_id);
        $this->assertSame(1, $this->countClientsOf((string) $another->id));
    }

    /**
     * Without a grant type there is nothing to be unique on, so a second call writes a
     * second client for the same user. That is today's behaviour and it is what the
     * condition says - both halves have to be there before anything is looked up.
     */
    public function testAUserWithoutAGrantTypeGetsAnotherClientEveryTime(): void {
        $user = $this->aUser();
        $first = OAuthClient::post(['user_id' => (string) $user->id]);

        $second = OAuthClient::post(['user_id' => (string) $user->id]);

        $this->assertNotSame($first->client_id, $second->client_id);
        $this->assertSame(2, $this->countClientsOf((string) $user->id));
    }

    /**
     * The whole of it, through the nested-user door: the portal creating the same login
     * twice ends up with one user and one client, because `User::post()` is keyed on the
     * username and this method is keyed on the user and the grant type.
     */
    public function testCreatingTheSamePortalLoginTwiceLeavesOneUserAndOneClient(): void {
        $username = $this->aUsername();
        $payload = ['user' => ['username' => $username, 'first_name' => 'Sweep'], 'grant_types' => 'client_credentials'];
        $first = OAuthClient::post($payload);

        $second = OAuthClient::post($payload);

        $this->assertSame($first->client_id, $second->client_id);
        $this->assertSame(1, $this->db->table('users')->where('username', $username)->countAllResults());
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * A credential pair made here, never one from the environment or from a fixture file.
     *
     * @return array{client_id: string, client_secret: string}
     */
    private function credentials(): array {
        return [
            'client_id' => 'test-client-' . bin2hex(random_bytes(8)),
            'client_secret' => bin2hex(random_bytes(16)),
        ];
    }

    private function aUsername(): string {
        return 'oauth-post-' . bin2hex(random_bytes(6));
    }

    /**
     * No password: `User::post()` hashes one with bcrypt, and none of this needs a user
     * that can sign in.
     */
    private function aUser(): User {
        return User::post(['username' => $this->aUsername(), 'first_name' => 'Sweep', 'last_name' => 'Er']);
    }

    /**
     * @return array<string, string>|null
     */
    private function row(string $clientId): ?array {
        return $this->db->table('oauth_clients')
            ->select('client_id, client_secret, grant_types, redirect_uri')
            ->where('client_id', $clientId)
            ->get()
            ->getRowArray();
    }

    /**
     * @return array<string, string>
     */
    private function rowWithUser(string $clientId): array {
        $row = $this->db->table('oauth_clients')->where('client_id', $clientId)->get()->getRowArray();

        return $row ?? [];
    }

    private function countClientsOf(string $userId): int {
        return $this->db->table('oauth_clients')->where('user_id', $userId)->countAllResults();
    }

    // </editor-fold>

}
