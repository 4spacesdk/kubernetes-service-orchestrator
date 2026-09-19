<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;

/**
 * What `/settings` adds once the caller is signed in.
 *
 * The endpoint is public - the frontend reads it before anyone has a token - and what it
 * gives away to an anonymous caller is pinned in `PublicSurfaceTest`, because that is where
 * the GitHub App private key once leaked. This file covers the other half: the branch that
 * only runs when the request carries a token, and which puts the caller's own user on the response so the
 * frontend does not need a second round trip to find out who it is talking to.
 *
 * That branch is the reason `ControllerTestCase` resets the `RestRequest` singleton between
 * tests. It remembers who the last request was made by, so without the reset an
 * unauthenticated request following an authenticated one would take this branch too - and
 * a public endpoint would answer a stranger with the previous caller's user.
 */
class SettingsApiTest extends ControllerTestCase {

    public function testASignedInCallerIsToldWhoTheyAre(): void {
        $body = $this->decode($this->signedIn()->get('settings'));

        $this->assertArrayHasKey('user', $body);
        $this->assertSame($this->signedInUserId(), (int) $body['user']['id']);
        $this->assertSame('PHP', $body['user']['first_name']);
    }

    /**
     * The user block is built from the same entity as everything else, so it is subject to
     * the same hidden-field list - and this endpoint is the one that is served publicly,
     * which makes the question worth asking here rather than trusting the entity.
     */
    public function testTheUserBlockCarriesNoCredentials(): void {
        $user = $this->decode($this->signedIn()->get('settings'))['user'];

        $this->assertArrayNotHasKey('password', $user);
        $this->assertArrayNotHasKey('mfa_secret_hash', $user);
    }

    /**
     * An anonymous caller gets the same document without the user block. Held next to the
     * test above so that the difference between the two branches is one assertion apart:
     * the key is either there or it is not, and nothing else changes.
     */
    public function testAnAnonymousCallerIsToldNothingAboutAnyUser(): void {
        $body = $this->decode($this->get('settings'));

        $this->assertArrayNotHasKey('user', $body);
    }

    /**
     * The declaration and the column, held against each other as everywhere else. This is
     * the endpoint where they matter most: `requireAuth()` has no call sites at all,
     * so the `false` below documents an intention and the column is what actually
     * lets an anonymous caller in.
     */
    public function testTheEndpointIsPublicInBothTheCodeAndTheTable(): void {
        $this->assertFalse((new \App\Controllers\Settings())->requireAuth('index'));

        // `from` is a reserved word, so the column is quoted by hand.
        $rows = $this->db->table('api_routes')
            ->select('`from`, is_public', false)
            ->where('method', 'get')
            ->get()
            ->getResultArray();

        $this->assertSame(1, (int) array_column($rows, 'is_public', 'from')['settings']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

}
