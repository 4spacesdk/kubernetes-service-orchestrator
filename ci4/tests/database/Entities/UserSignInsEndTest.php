<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\User;
use AuthExtension\OAuth2\ServerLib;

/**
 * A changed password ends every way the user is signed in - tokens and sessions - except the
 * session that changed it. A reset or a renewal used to leave them all, so a stolen session or
 * refresh token outlived the password it was got with.
 *
 * The tokens go through the OAuth storage, on its own connection outside this test's transaction,
 * and are removed again at the end.
 */
class UserSignInsEndTest extends DatabaseTestCase {

    public function testTheUsersTokensAndOtherSessionsGoAndTheKeptSessionStays(): void {
        $storage = ServerLib::getInstance()->storage;
        $refresh = 'refresh-' . bin2hex(random_bytes(8));
        $storage->setRefreshToken($refresh, 'webclient', '424242', time() + 3600);
        $this->aSession('ci_session:other-browser', 424242);
        $this->aSession('ci_session:this-browser', 424242);
        $this->aSession('ci_session:someone-else', 4242);

        try {
            User::EndEverySignIn(424242, 'ci_session:this-browser');

            $this->assertFalse($storage->getRefreshToken($refresh));
            $this->assertSame(['ci_session:someone-else', 'ci_session:this-browser'], $this->sessionsLeft());
        } finally {
            $storage->unsetRefreshToken($refresh);
        }
    }

    /**
     * Both ways PHP writes the id into the session: as a string and as an int.
     */
    public function testASessionWithTheIdAsAnIntegerGoesToo(): void {
        $this->db->table('ci_sessions')->insert([
            'id' => 'ci_session:int-id', 'ip_address' => '127.0.0.1', 'timestamp' => time(),
            'data' => '__ci_last_regenerate|i:1;user_id|i:424242;',
        ]);

        User::EndEverySignIn(424242);

        $this->assertNotContains('ci_session:int-id', $this->sessionsLeft());
    }

    private function aSession(string $id, int $userId): void {
        $this->db->table('ci_sessions')->insert([
            'id' => $id, 'ip_address' => '127.0.0.1', 'timestamp' => time(),
            'data' => '__ci_last_regenerate|i:1;user_id|s:' . strlen((string) $userId) . ':"' . $userId . '";',
        ]);
    }

    /**
     * @return list<string>
     */
    private function sessionsLeft(): array {
        $ids = array_column($this->db->table('ci_sessions')->select('id')->like('id', 'ci_session:', 'after')->get()->getResultArray(), 'id');
        sort($ids);
        return $ids;
    }

}
