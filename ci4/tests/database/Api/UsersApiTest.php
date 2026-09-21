<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\RbacPermission;
use App\Entities\RbacRole;
use App\Entities\User;
use App\Libraries\MFALib;
use App\Models\UserModel;

/**
 * Who am I, and the three endpoints that turn two-factor authentication on and off.
 *
 * The MFA secret is the whole of the second factor: anyone holding it can generate valid
 * codes for ever. These endpoints create it, store it and remove it, and until now none of
 * that was under test.
 *
 * **`mfa/setup/prepare` is not covered here on purpose** - it renders the QR code through
 * `api.qrserver.com`, so exercising it would send a real secret to a third party from the
 * test suite. Its `hasMFA` branch could not be reached even if the network call
 * were gone, for the reason `testMeAlwaysSaysTheUserHasNoSecondFactor` pins: the flag it
 * branches on is always false.
 *
 * What these hold is the decision: does this code verify, and what is written when it does
 * not. Whether the stored secret is visible to a *later* request is not asserted - the
 * auth extension writes on its own connection, and this test's transaction took its
 * snapshot before that write existed.
 */
class UsersApiTest extends ControllerTestCase {

    public function testMeReturnsTheSignedInUserWithTheirRoles(): void {
        $body = $this->decode($this->signedIn()->get('users/me'));

        $this->assertSame('OK', $body['status']);
        $this->assertSame($this->signedInUserId(), (int) $body['resource']['id']);
    }

    /**
     * `me` is what the frontend builds its menu from, so the roles and their permissions
     * have to come with it - they are loaded one level at a time, and the inner `find()` is
     * the one that is easy to lose: without it every role comes back with an empty
     * permission list, and a user who may do everything is shown a page where they may do
     * nothing.
     */
    public function testMeCarriesTheUsersRolesAndEachRolesPermissions(): void {
        $this->giveTheSignedInUserARole('test-role', 'Read everything');

        $resource = $this->decode($this->signedIn()->get('users/me'))['resource'];

        $roles = array_column($resource['rbac_roles'], null, 'identifier');
        $this->assertArrayHasKey('test-role', $roles);
        $this->assertSame(
            ['Read everything'],
            array_column($roles['test-role']['rbac_permissions'], 'name'),
            'the role arrived without its permissions'
        );
    }

    /**
     * **Today's behaviour, and it is a bug.** `has_mfa_secret_hash` is computed from
     * `mfa_secret_hash`, and `mfa_secret_hash` is in `User::$hiddenFields` - so it is
     * stripped by the `toArray()` that RestExtension builds `user_data` from, and the
     * `User` the controller then constructs from that array has no secret in it whatever is
     * stored. The flag is therefore false for every user on every request.
     *
     * Two things follow: the frontend can never tell that two-factor authentication is on,
     * and `Users::mfaSetupPrepare()` always takes its `else` branch - so a user who already
     * has a second factor is handed a fresh secret to scan instead of being told they are
     * done, and verifying it overwrites the one they were using.
     *
     * The write is asserted first, so that this test says *the read path is wrong* rather
     * than merely *nothing was stored*.
     */
    public function testMeAlwaysSaysTheUserHasNoSecondFactor(): void {
        /** @var User $user */
        $user = (new UserModel())->find($this->signedInUserId());
        $user->updateMFASecret((new MFALib())->createSecret());

        $stored = $this->db->table('users')->where('id', $this->signedInUserId())->get()->getRowArray();
        $this->assertNotSame('', $stored['mfa_secret_hash'], 'nothing was stored, so this test proves nothing');

        $resource = $this->decode($this->signedIn()->get('users/me'))['resource'];

        $this->assertFalse(
            $resource['has_mfa_secret_hash'],
            'the flag survives the hidden-field list now - the bug above is fixed'
        );
    }

    public function testTheRightCodeStoresTheSecretOnTheUser(): void {
        $secret = (new MFALib())->createSecret();
        $code = (new MFALib())->getSetupCode($secret);

        $body = $this->decode(
            $this->withSession(['mfa_secret' => $secret])->signedIn()->put("users/mfa/setup/verify?code={$code}")
        );

        $this->assertTrue($body['resource']['value']);
    }

    /**
     * A wrong code must not store anything. Storing on a failed verification would leave
     * the account with a second factor its owner cannot produce codes for.
     */
    public function testAWrongCodeStoresNothing(): void {
        $secret = (new MFALib())->createSecret();

        $body = $this->decode(
            $this->withSession(['mfa_secret' => $secret])->signedIn()->put('users/mfa/setup/verify?code=000000')
        );

        $this->assertFalse($body['resource']['value']);
    }

    /**
     * Two-factor authentication can be turned off again.
     *
     * `User::removeMFASecret()` sets the column to null and saves, and the column was
     * `NOT NULL`: the endpoint threw `Column 'mfa_secret_hash' cannot be null`, the secret
     * stayed, and the account kept a second factor its owner had asked to remove. The column
     * is nullable now - it was widened and made nullable along with every other credential
     * column when they were encrypted at rest, and "no second factor" is a state it has to
     * be able to hold.
     *
     * The other half of that fault is still there: `has_mfa_secret_hash` is always false, so
     * the page offering this does not know the user has one. See
     * `testMeAlwaysSaysTheUserHasNoSecondFactor`.
     */
    public function testRemovingTheSecondFactorClearsIt(): void {
        $secret = (new MFALib())->createSecret();
        $this->withSession(['mfa_secret' => $secret])
            ->signedIn()
            ->put('users/mfa/setup/verify?code=' . (new MFALib())->getSetupCode($secret));

        $this->assertNotSame('', (string) $this->storedSecondFactor());

        $this->forgetTheLastRequest();
        $this->signedIn()->put('users/mfa/setup/remove');

        $this->assertSame('', (string) $this->storedSecondFactor());
    }

    private function storedSecondFactor(): ?string {
        return $this->db->table('users')
            ->where('id', $this->signedInUserId())
            ->get()
            ->getRowArray()['mfa_secret_hash'];
    }

    // <editor-fold desc="Fixtures">

    /**
     * A role with one permission, attached to the user `signedIn()` acts as.
     *
     * The link row is written straight to the join table rather than through the entity,
     * because what is being tested is the loading, and a fixture that used the same
     * relation machinery would pass on both sides of the bug.
     */
    private function giveTheSignedInUserARole(string $roleIdentifier, string $permissionName): void {
        $role = new RbacRole();
        $role->identifier = $roleIdentifier;
        $role->name = 'Test role';
        $role->description = '';
        $role->save();

        $permission = new RbacPermission();
        $permission->name = $permissionName;
        $permission->description = '';
        $permission->save();

        $role->save($permission);

        $this->db->table('rbac_roles_users')->insert([
            'user_id' => $this->signedInUserId(),
            'rbac_role_id' => $role->id,
        ]);
    }

    // <editor-fold desc="Passwords">

    /**
     * A password set through the API is hashed before it is written. A short one used to be
     * written as sent and never hashed, and every other one sat in the column in plain text
     * until a second save.
     */
    public function testAPasswordSetThroughTheApiIsNeverStoredAsSent(): void {
        $user = \App\Fixtures::user(['username' => 'someone@example.org']);

        $response = $this->withBodyFormat('json')->signedIn()->patch("users/{$user->id}", ['password' => 'A-good-one-1']);

        $this->assertSame(200, $response->response()->getStatusCode());
        $stored = $this->storedPassword($user->id);
        $this->assertNotSame('A-good-one-1', $stored);
        $this->assertTrue(password_verify('A-good-one-1', $stored));
    }

    /**
     * One that breaks a rule is refused with the rule, and the old one stays.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('passwordsThatBreakARule')]
    public function testAPasswordThatBreaksARuleIsRefusedAndNothingIsWritten(string $password, string $rule): void {
        $user = \App\Fixtures::user(['username' => 'someone@example.org', 'password' => 'the-old-one']);
        $before = $this->storedPassword($user->id);

        $response = $this->withBodyFormat('json')->signedIn()->patch("users/{$user->id}", ['password' => $password]);

        $this->assertSame(400, $response->response()->getStatusCode());
        $this->assertSame("Password: {$rule}", $this->decode($response)['error'] ?? null);
        $this->assertSame($before, $this->storedPassword($user->id));
    }

    public static function passwordsThatBreakARule(): array {
        return [
            'five characters' => ['abc12', 'At least eight characters'],
            'no number' => ['NoNumbersHere', 'At least one number'],
            'no capital' => ['no-capital-1', 'At least one uppercase letter'],
        ];
    }

    /**
     * A new user with a short password is not created with it.
     */
    public function testANewUserWithAShortPasswordIsRefused(): void {
        $response = $this->withBodyFormat('json')->signedIn()->post('users', ['username' => 'new@example.org', 'password' => 'abc']);

        $this->assertSame(400, $response->response()->getStatusCode());
        $this->assertSame(0, $this->db->table('users')->where('username', 'new@example.org')->countAllResults());
    }

    /**
     * The form sends the field empty when it is not filled in, and that keeps the password.
     */
    public function testAnEmptyPasswordLeavesTheOldOne(): void {
        $user = \App\Fixtures::user(['username' => 'someone@example.org', 'password' => 'the-old-one']);
        $before = $this->storedPassword($user->id);

        $this->withBodyFormat('json')->signedIn()->patch("users/{$user->id}", ['first_name' => 'Renamed', 'password' => '']);

        $this->assertSame($before, $this->storedPassword($user->id));
    }

    /**
     * What an earlier short password left behind is hashed in place - the user can still
     * sign in with it - and marked for renewal, because it is too short for the rules.
     */
    public function testTheMigrationHashesAPasswordLeftInPlainText(): void {
        $plain = \App\Fixtures::user(['username' => 'plain@example.org']);
        $hashed = \App\Fixtures::user(['username' => 'hashed@example.org', 'password' => 'the-right-one']);
        $this->db->table('users')->where('id', $plain->id)->update(['password' => 'abc', 'renew_password' => 0]);
        $hashBefore = $this->storedPassword($hashed->id);

        // Required by path: a migration's file name starts with its date, so it cannot be autoloaded.
        require_once APPPATH . 'Database/Migrations/2026-09-21-120000_HashPlainTextPasswords.php';
        (new \App\Database\Migrations\HashPlainTextPasswords())->up();

        $this->assertTrue(password_verify('abc', $this->storedPassword($plain->id)));
        $this->assertSame('1', (string) $this->db->table('users')->where('id', $plain->id)->get()->getRow('renew_password'));
        $this->assertSame($hashBefore, $this->storedPassword($hashed->id));
    }

    // </editor-fold>

    private function storedPassword(int|string $id): string {
        return (string) $this->db->table('users')->where('id', $id)->get()->getRow('password');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
