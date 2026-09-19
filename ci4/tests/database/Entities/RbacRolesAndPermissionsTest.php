<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\RbacPermission;
use App\Entities\RbacRole;

/**
 * The two builders behind the permission tables.
 *
 * Nothing reads these tables yet - no request is refused because a role lacks a permission,
 * and `rbac_roles_users` is written once by the migration that created it. So what is held
 * here is what the two `Create()` methods and `updatePermissions()` *do*, not what an
 * enforced RBAC would need them to do. The difference matters: the gap
 * `testReplacingPermissionsUnlinksOnlyTheFirstOfThem` pins down is harmless while nothing
 * reads a role's permissions, and is a privilege leak the day something does.
 *
 * Both `Create()` methods are upserts keyed on a natural identifier - `name` for a
 * permission, `identifier` for a role - which is what lets the migration call them and a
 * later migration call them again without duplicating a row.
 */
class RbacRolesAndPermissionsTest extends DatabaseTestCase {

    // <editor-fold desc="RbacPermission::Create">

    public function testAPermissionThatIsNotThereIsInserted(): void {
        $permission = RbacPermission::Create('sweep.read', 'Read a sweep');

        $this->assertGreaterThan(0, (int) $permission->id);
        $this->assertSame([
            'name' => 'sweep.read',
            'description' => 'Read a sweep',
        ], $this->permissionRow((int) $permission->id));
    }

    /**
     * Called a second time with the same name it updates the row it found rather than
     * adding another. That is what makes it safe in a migration: the name is the key.
     */
    public function testAPermissionWithATakenNameIsUpdatedInPlace(): void {
        $first = RbacPermission::Create('sweep.read', 'Read a sweep');

        $second = RbacPermission::Create('sweep.read', 'Read a sweep, better described');

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(1, $this->countPermissionsNamed('sweep.read'));
        $this->assertSame('Read a sweep, better described', $this->permissionRow((int) $first->id)['description']);
    }

    /**
     * Two names are two rows. Trivial, and it is what keeps the test above from passing on
     * a `Create()` that only ever writes one row.
     */
    public function testTwoNamesAreTwoPermissions(): void {
        $read = RbacPermission::Create('sweep.read', 'Read a sweep');
        $write = RbacPermission::Create('sweep.write', 'Write a sweep');

        $this->assertNotSame((int) $read->id, (int) $write->id);
    }

    // </editor-fold>

    // <editor-fold desc="RbacRole::Create">

    public function testARoleThatIsNotThereIsInserted(): void {
        $role = RbacRole::Create('roles/sweeper', 'Sweeper', 'Sweeps');

        $this->assertGreaterThan(0, (int) $role->id);
        $this->assertSame([
            'identifier' => 'roles/sweeper',
            'name' => 'Sweeper',
            'description' => 'Sweeps',
        ], $this->roleRow((int) $role->id));
    }

    /**
     * The identifier is the key, and the display name is not: a role can be renamed without
     * a second row appearing next to it.
     */
    public function testARoleWithATakenIdentifierIsUpdatedInPlace(): void {
        $first = RbacRole::Create('roles/sweeper', 'Sweeper', 'Sweeps');

        $second = RbacRole::Create('roles/sweeper', 'Chief Sweeper', 'Sweeps, in charge');

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(1, $this->countRolesIdentifiedBy('roles/sweeper'));
        $this->assertSame([
            'identifier' => 'roles/sweeper',
            'name' => 'Chief Sweeper',
            'description' => 'Sweeps, in charge',
        ], $this->roleRow((int) $first->id));
    }

    // </editor-fold>

    // <editor-fold desc="RbacRole::updatePermissions">

    public function testPermissionsAreLinkedToTheRole(): void {
        $role = RbacRole::Create('roles/sweeper', 'Sweeper', 'Sweeps');
        $read = RbacPermission::Create('sweep.read', 'Read a sweep');
        $write = RbacPermission::Create('sweep.write', 'Write a sweep');

        $role->updatePermissions([$read, $write]);

        $this->assertSame(
            [(int) $read->id, (int) $write->id],
            $this->permissionIdsOf((int) $role->id)
        );
    }

    /**
     * A role with nothing linked to it yet does not go looking for something to unlink -
     * `exists()` on the empty result is what stops it, and an entity with no id would take
     * every join row of every role with it if it did not.
     */
    public function testARoleWithNoPermissionsYetJustGetsTheOnesItIsGiven(): void {
        $role = RbacRole::Create('roles/sweeper', 'Sweeper', 'Sweeps');
        $read = RbacPermission::Create('sweep.read', 'Read a sweep');
        $before = $this->countJoinRows();

        $role->updatePermissions([$read]);

        $this->assertSame([(int) $read->id], $this->permissionIdsOf((int) $role->id));
        $this->assertSame($before + 1, $this->countJoinRows(), 'rows belonging to the roles the migration made were touched');
    }

    /**
     * Permissions belong to the role they were linked to, and not to a second role that was
     * given a different set. This is the assertion that would fail if the unlink below ever
     * grew wide enough to take another role's rows with it.
     */
    public function testAnotherRolesPermissionsAreLeftAlone(): void {
        $sweeper = RbacRole::Create('roles/sweeper', 'Sweeper', 'Sweeps');
        $reader = RbacRole::Create('roles/reader', 'Reader', 'Reads');
        $read = RbacPermission::Create('sweep.read', 'Read a sweep');
        $write = RbacPermission::Create('sweep.write', 'Write a sweep');
        $sweeper->updatePermissions([$read, $write]);

        $reader->updatePermissions([$read]);

        $this->assertSame([(int) $read->id, (int) $write->id], $this->permissionIdsOf((int) $sweeper->id));
        $this->assertSame([(int) $read->id], $this->permissionIdsOf((int) $reader->id));
    }

    /**
     * **Today's behaviour, and it is wrong.** The method reads like "replace the set", and
     * it is what the name says and what the migration relies on. It is not what happens.
     *
     * `$this->delete($existingPermissions)` is handed the whole collection, but the delete
     * underneath reads a single id off it - the first row's - and deletes that one join row.
     * Every other permission the role already had stays linked, and is then linked a second
     * time by the loop below.
     *
     * So calling it twice with the same two permissions leaves three join rows, one of them
     * a duplicate. Calling it with a *smaller* set does not take anything away: a permission
     * removed from the list keeps its row. Nothing reads these rows today, which is
     * the only reason it has not bitten - the day RBAC is enforced, revoking a permission
     * will not revoke it.
     *
     * Pinned rather than fixed. The test says what it sees.
     */
    public function testReplacingPermissionsUnlinksOnlyTheFirstOfThem(): void {
        $role = RbacRole::Create('roles/sweeper', 'Sweeper', 'Sweeps');
        $read = RbacPermission::Create('sweep.read', 'Read a sweep');
        $write = RbacPermission::Create('sweep.write', 'Write a sweep');
        $role->updatePermissions([$read, $write]);

        $role->updatePermissions([$read, $write]);

        // Read's row was the one that went, and both were written again on top of what
        // was left: three rows where there should be two, and `sweep.write` twice.
        $this->assertSame(
            [(int) $write->id, (int) $read->id, (int) $write->id],
            $this->permissionIdsOf((int) $role->id),
            'the whole set is replaced now - the delete takes the collection'
        );
    }

    /**
     * The same gap from the other side: taking a permission out of the list does not take it
     * off the role. `sweep.write` is not in the new set and is still linked afterwards.
     */
    public function testAPermissionLeftOutOfTheNewSetKeepsItsLink(): void {
        $role = RbacRole::Create('roles/sweeper', 'Sweeper', 'Sweeps');
        $read = RbacPermission::Create('sweep.read', 'Read a sweep');
        $write = RbacPermission::Create('sweep.write', 'Write a sweep');
        $role->updatePermissions([$read, $write]);

        $role->updatePermissions([$read]);

        $this->assertContains(
            (int) $write->id,
            $this->permissionIdsOf((int) $role->id),
            'a permission left out is revoked now - this test has done its job'
        );
    }

    // </editor-fold>

    // <editor-fold desc="The table">

    /**
     * @return array<string, string>
     */
    private function permissionRow(int $id): array {
        $row = $this->db->table('rbac_permissions')->select('name, description')->where('id', $id)->get()->getRowArray();

        return $row ?? [];
    }

    /**
     * @return array<string, string>
     */
    private function roleRow(int $id): array {
        $row = $this->db->table('rbac_roles')->select('identifier, name, description')->where('id', $id)->get()->getRowArray();

        return $row ?? [];
    }

    private function countPermissionsNamed(string $name): int {
        return $this->db->table('rbac_permissions')->where('name', $name)->countAllResults();
    }

    private function countRolesIdentifiedBy(string $identifier): int {
        return $this->db->table('rbac_roles')->where('identifier', $identifier)->countAllResults();
    }

    /**
     * Straight out of the join table, in the order the rows were written, duplicates and
     * all. Reading it through the relation would hide exactly what these tests are about.
     *
     * @return array<int, int>
     */
    private function permissionIdsOf(int $roleId): array {
        $rows = $this->db->table('rbac_permissions_rbac_roles')
            ->select('rbac_permission_id')
            ->where('rbac_role_id', $roleId)
            ->orderBy('id', 'asc')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row) => (int) $row['rbac_permission_id'], $rows);
    }

    private function countJoinRows(): int {
        return $this->db->table('rbac_permissions_rbac_roles')->countAllResults();
    }

    // </editor-fold>

}
