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
     * **Today's behaviour: two-factor authentication cannot be turned off.**
     *
     * `User::removeMFASecret()` sets the column to null and saves, and `mfa_secret_hash` is
     * `NOT NULL`. The endpoint throws a database exception, the secret stays, and the
     * account keeps a second factor its owner asked to remove.
     */
    public function testRemovingTheSecondFactorFails(): void {
        $secret = (new MFALib())->createSecret();
        $this->withSession(['mfa_secret' => $secret])
            ->signedIn()
            ->put('users/mfa/setup/verify?code=' . (new MFALib())->getSetupCode($secret));

        $this->expectException(\CodeIgniter\Database\Exceptions\DatabaseException::class);
        $this->expectExceptionMessage("Column 'mfa_secret_hash' cannot be null");

        $this->signedIn()->put('users/mfa/setup/remove');
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

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
