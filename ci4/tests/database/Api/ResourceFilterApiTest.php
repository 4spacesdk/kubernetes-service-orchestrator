<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\Workspace;
use App\Fixtures;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\TestResponse;

/**
 * What `?filter=` actually narrows, on the three models that carry a filter of their own.
 *
 * `WorkspaceModel`, `DeploymentModel` and `DeploymentSpecificationModel` each read a
 * filter out of the query parser in `preRestGet()`, set `ignoreAuto = true` on it so the
 * extension's generic handling leaves it alone, and then build the condition by hand. The
 * `ignoreAuto` is the dangerous half: once it is set, a branch that decides not to add a
 * condition does not fall back to anything - the filter is simply gone, and the caller is
 * answered with **every row**, under a `200 OK` that looks exactly like a narrow one.
 *
 * So every test here names the rows it expects back and the rows it expects left out.
 * Asserting on the status line, or only on the rows that should be there, would pass
 * against a filter that was deleted.
 *
 * Each arrangement writes at least one row that must *not* come back, in the same table
 * and visible to the same caller.
 */
class ResourceFilterApiTest extends ControllerTestCase {

    // <editor-fold desc="Workspaces - the label filter">

    /**
     * One selector: only the workspaces carrying that exact name and value.
     *
     * The subquery counts matching labels per workspace and keeps the ones above zero, so
     * the two failures worth separating are "matches nothing" and "matches everything".
     * `other` has a label with the same name and a different value, which is the pair a
     * condition built on the name alone would let through.
     */
    public function testAWorkspaceLabelFilterAnswersOnlyTheWorkspacesCarryingThatLabel(): void {
        $mine = $this->workspaceNamed('with-prod', ['environment' => 'production']);
        $sameName = $this->workspaceNamed('with-staging', ['environment' => 'staging']);
        $sameValue = $this->workspaceNamed('other-name', ['tier' => 'production']);
        $bare = $this->workspaceNamed('unlabelled', []);

        $names = $this->namesOf($this->signedIn()->get('workspaces?filter=label:environment=production'));

        $this->assertSame(['with-prod'], $names);
        $this->assertNotContains('with-staging', $names, 'a different value for the same label name');
        $this->assertNotContains('other-name', $names, 'the same value under a different label name');
        $this->assertNotContains('unlabelled', $names, 'no label at all');

        // All four are readable without the filter, so the absences above are the filter's
        // doing and not an arrangement that never wrote the rows.
        $this->forgetTheLastRequest();
        $unfiltered = $this->namesOf($this->signedIn()->get('workspaces'));
        foreach ([$mine, $sameName, $sameValue, $bare] as $workspace) {
            $this->assertContains($workspace->name_readable, $unfiltered);
        }
    }

    /**
     * Two selectors: every one of them has to match, and each adds its own subquery.
     *
     * The selectors are separated by a comma, and a comma is also what `QueryParser` splits
     * *filters* on - so the value has to be quoted to reach `preRestGet()` in one piece.
     * Unquoted, `filter=label:a=1,b=2` is parsed as two filters and the second one is
     * nonsense; see `testAnUnquotedSecondSelectorIsParsedAsAFilterOfItsOwn()`.
     *
     * `only-one-of-them` carries the first selector and not the second, which is the row a
     * loop that overwrote its condition instead of adding one would hand back.
     */
    public function testEveryLabelSelectorHasToMatchForTheWorkspaceToComeBack(): void {
        $this->workspaceNamed('both', ['environment' => 'production', 'tier' => 'web']);
        $this->workspaceNamed('only-one-of-them', ['environment' => 'production']);
        $this->workspaceNamed('the-other-one', ['tier' => 'web']);

        $names = $this->namesOf(
            $this->signedIn()->get('workspaces?filter=label:"environment=production,tier=web"')
        );

        $this->assertSame(['both'], $names);
    }

    /**
     * Today's behaviour for the unquoted form the filter syntax suggests.
     *
     * `QueryParser::parseFilter()` splits on commas outside brackets and quotes, so
     * `label:environment=production,tier=web` arrives as two filters: `label` with the
     * value `environment=production`, and a second one whose property is the empty string.
     * The empty one has no `ignoreAuto`, so the extension applies it as an ordinary
     * condition on a column called `''`.
     *
     * The result is not a wrong row - it is a database error - and it is pinned because
     * the shape reads as supported: the model splits its own value on commas, so somebody
     * wrote it expecting to receive more than one selector that way.
     *
     * `tier=web` has no colon in it, so `QueryFilter::parse()` takes the property to be the
     * empty string and the value to be everything after the first character. The condition
     * that reaches MySQL is `= 'ier=web'`, with no column in front of it. Reported, not
     * fixed.
     */
    public function testAnUnquotedSecondSelectorIsParsedAsAFilterOfItsOwn(): void {
        $this->workspaceNamed('both', ['environment' => 'production', 'tier' => 'web']);

        try {
            $this->signedIn()->get('workspaces?filter=label:environment=production,tier=web');
            $this->fail('the unquoted form started working - good, and this pin is now the fix');
        } catch (DatabaseException $e) {
            // The leading character of the second selector is eaten as the separator, and
            // what is left is compared against nothing at all.
            $this->assertStringContainsString("= 'ier=web'", $e->getMessage());
        }
    }

    // </editor-fold>

    // <editor-fold desc="Workspaces - the status filter">

    /**
     * A list of statuses narrows to exactly those statuses.
     *
     * The filter only reaches `whereIn()` when the parser produced an array, which is the
     * `[a,b]` form - so this is the one call shape the branch was written for.
     */
    public function testAWorkspaceStatusFilterAnswersOnlyTheListedStatuses(): void {
        $this->workspaceNamed('is-active', [], \WorkspaceStatusTypes::Active);
        $this->workspaceNamed('is-inactive', [], \WorkspaceStatusTypes::Inactive);
        $this->workspaceNamed('is-draft', [], \WorkspaceStatusTypes::Draft);

        $names = $this->namesOf(
            $this->signedIn()->get('workspaces?filter=status:[active,inactive]')
        );

        $this->assertContains('is-active', $names);
        $this->assertContains('is-inactive', $names);
        $this->assertNotContains('is-draft', $names, 'a status that was not asked for');
    }

    /**
     * An empty list is treated as no filter at all, and that is deliberate.
     *
     * `$statuses[0] !== ''` is there because `filter=status:[]` parses to `['']`, which
     * `whereIn()` would turn into "status IN ('')" and answer nothing. A UI that clears its
     * status picker sends exactly that, and it means "all".
     */
    public function testAnEmptyStatusListIsNotAFilter(): void {
        $this->workspaceNamed('is-active', [], \WorkspaceStatusTypes::Active);
        $this->workspaceNamed('is-draft', [], \WorkspaceStatusTypes::Draft);

        $names = $this->namesOf($this->signedIn()->get('workspaces?filter=status:[]'));

        $this->assertContains('is-active', $names);
        $this->assertContains('is-draft', $names);
    }

    /**
     * **A scalar status filter is dropped in silence, and the caller gets every workspace.**
     *
     * `filter=status:active` is the ordinary filter syntax, and it is what every other
     * filterable field on every other resource accepts. Here it parses to the string
     * `active`, the `is_array()` guard rejects it, and nothing is added - but `ignoreAuto`
     * has already been set two lines above, so the extension will not apply it either.
     *
     * The response is `200 OK`, it is shaped exactly like a filtered one, and it contains
     * rows in every other status. Reported, not fixed.
     *
     * The assertion is deliberately the wrong-looking way round: it asserts the draft
     * workspace **is** in the answer. The day the branch learns to handle a scalar, this
     * test fails and that failure is the fix landing.
     */
    public function testAScalarStatusFilterIsDroppedAndAnswersEveryStatus(): void {
        $this->workspaceNamed('is-active', [], \WorkspaceStatusTypes::Active);
        $this->workspaceNamed('is-draft', [], \WorkspaceStatusTypes::Draft);

        $names = $this->namesOf($this->signedIn()->get('workspaces?filter=status:active'));

        $this->assertContains('is-active', $names);
        $this->assertContains(
            'is-draft',
            $names,
            'a scalar status filter started narrowing - good, and this pin is now the fix'
        );
    }

    // </editor-fold>

    // <editor-fold desc="Workspaces - ?include=deployment">

    /**
     * `?include=deployment` is served by `postRestGet()`, not by the extension.
     *
     * `preRestGet()` sets `ignoreAuto` on the include so `applyIncludeMany()` skips it, and
     * `postRestGet()` then fetches every deployment of every workspace in the response in
     * one query and hands each one to the workspace it belongs to. A row handed to the
     * wrong workspace is the failure this guards, so both workspaces are asserted - a leak
     * shows as the other workspace's deployment appearing here.
     *
     * The two urls are computed on the way out, from the workspace's subdomain and domain
     * and from the specification's prefix and suffix, so they are asserted as values rather
     * than as keys.
     */
    public function testIncludingDeploymentsGivesEachWorkspaceItsOwnWithUrlsAndLabels(): void {
        $domain = Fixtures::domain(['name' => 'example.test']);
        $specification = Fixtures::deploymentSpecification([
            'domain_prefix' => '',
            'domain_suffix' => '.svc',
            'domain_tls' => 'https',
        ]);

        $mine = $this->workspaceNamed('mine', [], \WorkspaceStatusTypes::Active);
        $mine->subdomain = 'mine';
        $mine->domain_id = $domain->id;
        $mine->save();

        $theirs = $this->workspaceNamed('theirs', [], \WorkspaceStatusTypes::Active);
        $theirs->subdomain = 'theirs';
        $theirs->domain_id = $domain->id;
        $theirs->save();

        $ours = Fixtures::deployment([
            'workspace_id' => $mine->id,
            'deployment_specification_id' => $specification->id,
            'name' => 'ours',
            'namespace' => 'mine',
        ]);
        $yours = Fixtures::deployment([
            'workspace_id' => $theirs->id,
            'deployment_specification_id' => $specification->id,
            'name' => 'yours',
            'namespace' => 'theirs',
        ]);

        // Both deployments carry a label, and the two labels differ. One labelled row would
        // be matched by a loop that put every label on whichever deployment came back first.
        $this->attachLabel($ours->id, 'deployments_labels', 'deployment_id', 'role', 'api');
        $this->attachLabel($yours->id, 'deployments_labels', 'deployment_id', 'role', 'worker');

        $byName = $this->byName($this->signedIn()->get('workspaces?include=deployment'));

        $this->assertSame(['ours'], array_column($byName['mine']['deployments'], 'name'));
        $this->assertSame(['yours'], array_column($byName['theirs']['deployments'], 'name'));

        $deployment = $byName['mine']['deployments'][0];
        $this->assertSame('https://mine.example.test.svc', $deployment['url_external']);
        $this->assertSame('ours.mine.svc', $deployment['url_internal']);

        // The second query in `postRestGet()`: labels are loaded for the deployments that
        // were found, and attached to the deployment they belong to.
        $this->assertSame(['api'], array_column($deployment['labels'], 'value'));
        $this->assertSame(['worker'], array_column($byName['theirs']['deployments'][0]['labels'], 'value'));
    }

    /**
     * A workspace with no deployments comes back **without a `deployments` key at all**.
     *
     * This is the branch where `postRestGet()` finds nothing and its `$deployments->exists()`
     * guard skips the label query, and it is worth its own test because the answer differs
     * from the rest of the API: `RelationIncludeApiTest::testAParentWithNoRowsGetsAnEmptyCollection`
     * shows the extension's own `applyIncludeMany()` setting an empty list on the parent,
     * while this hand-rolled include only ever sets the property on a parent that has rows.
     *
     * A client written against `?include=` therefore has to handle two shapes for the same
     * question depending on which resource it asked. Pinned, not endorsed - and the fix is
     * to set the empty collection, which makes this test fail.
     */
    public function testAWorkspaceWithoutDeploymentsHasNoDeploymentsKeyAtAll(): void {
        $this->workspaceNamed('no-deployments', [], \WorkspaceStatusTypes::Active);

        $byName = $this->byName($this->signedIn()->get('workspaces?include=deployment'));

        $this->assertArrayHasKey('no-deployments', $byName);
        $this->assertArrayNotHasKey(
            'deployments',
            $byName['no-deployments'],
            'an included empty collection started coming back as [] - good, and this pin is now the fix'
        );
    }

    // </editor-fold>

    // <editor-fold desc="Deployments and specifications - the same label filter">

    /**
     * `DeploymentModel::preRestGet()` carries its own copy of the label subquery.
     *
     * It is the same code as the workspace one with a different relation in the middle, and
     * it is tested separately for that reason: the two have already drifted once - the
     * workspace model also has a status filter and this one does not.
     */
    public function testADeploymentLabelFilterAnswersOnlyTheDeploymentsCarryingThatLabel(): void {
        $workspace = Fixtures::workspace();
        $specification = Fixtures::deploymentSpecification();

        $labelled = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
            'name' => 'labelled',
        ]);
        Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
            'name' => 'unlabelled',
        ]);
        $wrongValue = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
            'name' => 'wrong-value',
        ]);
        $wrongName = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
            'name' => 'wrong-name',
        ]);

        $this->attachLabel($labelled->id, 'deployments_labels', 'deployment_id', 'environment', 'production');
        $this->attachLabel($wrongValue->id, 'deployments_labels', 'deployment_id', 'environment', 'staging');

        // Same value under a different name, so the name half of the condition is load
        // bearing too: without it `wrong-name` comes back as well.
        $this->attachLabel($wrongName->id, 'deployments_labels', 'deployment_id', 'tier', 'production');

        $names = $this->namesOf(
            $this->signedIn()->get('deployments?filter=label:environment=production'),
            'name'
        );

        $this->assertSame(['labelled'], $names);
    }

    /**
     * And the third copy, on specifications. Same shape, third relation.
     */
    public function testASpecificationLabelFilterAnswersOnlyTheSpecificationsCarryingThatLabel(): void {
        $labelled = Fixtures::deploymentSpecification(['name' => 'labelled-spec']);
        Fixtures::deploymentSpecification(['name' => 'unlabelled-spec']);
        $wrongValue = Fixtures::deploymentSpecification(['name' => 'wrong-value-spec']);
        $wrongName = Fixtures::deploymentSpecification(['name' => 'wrong-name-spec']);

        $this->attachLabel(
            $labelled->id,
            'deployment_specifications_labels',
            'deployment_specification_id',
            'environment',
            'production'
        );
        $this->attachLabel(
            $wrongValue->id,
            'deployment_specifications_labels',
            'deployment_specification_id',
            'environment',
            'staging'
        );
        $this->attachLabel(
            $wrongName->id,
            'deployment_specifications_labels',
            'deployment_specification_id',
            'tier',
            'production'
        );

        $names = $this->namesOf(
            $this->signedIn()->get('deployment_specifications?filter=label:environment=production'),
            'name'
        );

        $this->assertSame(['labelled-spec'], $names);
    }

    // </editor-fold>

    // <editor-fold desc="Users - the one model with an ordering of its own">

    /**
     * `UserModel::applyOrder()` turns `ordering=name` into two columns.
     *
     * There is no `name` column on `users`, so the base implementation would order by a
     * column that does not exist. The two users sharing a first name are what makes the
     * second `orderBy` visible: without it they come back in whatever order the table
     * happens to hand them over.
     *
     * The response is narrowed to the three users written here, because the signed-in
     * fixture user and any other run's are in the same table.
     */
    public function testUsersOrderedByNameAreOrderedByFirstNameAndThenLastName(): void {
        $usernames = $this->threeUsersWithATiedFirstName();

        $order = $this->usernamesInOrder("users?ordering=name&filter=username:[{$usernames}]");

        $this->assertSame(['zz-order-ann', 'zz-order-bo-alpha', 'zz-order-bo-zeta'], $order);
    }

    /**
     * The same, descending, so the direction is passed through to both columns rather than
     * hard-coded on one of them.
     */
    public function testUsersOrderedByNameDescendingReverseBothColumns(): void {
        $usernames = $this->threeUsersWithATiedFirstName();

        $order = $this->usernamesInOrder("users?ordering=name:desc&filter=username:[{$usernames}]");

        $this->assertSame(['zz-order-bo-zeta', 'zz-order-bo-alpha', 'zz-order-ann'], $order);
    }

    /**
     * Any other property falls through to the base implementation and orders by the column.
     *
     * Ordering by `last_name` puts the three in an order that neither `name` nor the
     * insertion order produces, so a `applyOrder()` that swallowed everything would fail
     * here rather than agree by accident.
     */
    public function testUsersOrderedByAnOrdinaryColumnFallThroughToTheBaseOrdering(): void {
        $usernames = $this->threeUsersWithATiedFirstName();

        $order = $this->usernamesInOrder("users?ordering=last_name&filter=username:[{$usernames}]");

        $this->assertSame(['zz-order-bo-alpha', 'zz-order-ann', 'zz-order-bo-zeta'], $order);
    }

    // </editor-fold>

    // <editor-fold desc="Arrangement">

    /**
     * A workspace with a readable name, a status, and a label per pair given.
     *
     * @param array<string, string> $labels
     */
    private function workspaceNamed(string $name, array $labels, string $status = \WorkspaceStatusTypes::Draft): Workspace {
        $workspace = Fixtures::workspace([
            'name_readable' => $name,
            'name_system' => $name,
            'namespace' => $name,
            'status' => $status,
        ]);

        foreach ($labels as $labelName => $value) {
            $this->attachLabel($workspace->id, 'labels_workspaces', 'workspace_id', $labelName, $value);
        }

        return $workspace;
    }

    /**
     * A label row plus the junction row that hangs it off one owner.
     *
     * Written straight to the tables: `Fixtures` has no label helper, and this test must
     * not be the reason one is added to a shared file.
     */
    private function attachLabel(int $ownerId, string $joinTable, string $ownerColumn, string $name, string $value): void {
        $this->db->table('labels')->insert(['name' => $name, 'value' => $value]);
        $labelId = (int) $this->db->insertID();

        $this->db->table($joinTable)->insert([
            'label_id' => $labelId,
            $ownerColumn => $ownerId,
        ]);
    }

    /**
     * Three users whose ordering by first name, by last name and by insertion all differ.
     *
     * @return string the usernames, ready to drop into a `[a,b,c]` filter
     */
    private function threeUsersWithATiedFirstName(): string {
        Fixtures::user(['username' => 'zz-order-bo-zeta', 'first_name' => 'Bo', 'last_name' => 'Zeta']);
        Fixtures::user(['username' => 'zz-order-ann', 'first_name' => 'Ann', 'last_name' => 'Noor']);
        Fixtures::user(['username' => 'zz-order-bo-alpha', 'first_name' => 'Bo', 'last_name' => 'Alpha']);

        return 'zz-order-bo-zeta,zz-order-ann,zz-order-bo-alpha';
    }

    // </editor-fold>

    // <editor-fold desc="Reading responses">

    /**
     * @return list<string>
     */
    private function namesOf(TestResponse $response, string $field = 'name_readable'): array {
        return array_column($this->decode($response)['resources'] ?? [], $field);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function byName(TestResponse $response): array {
        return array_column($this->decode($response)['resources'] ?? [], null, 'name_readable');
    }

    /**
     * @return list<string>
     */
    private function usernamesInOrder(string $path): array {
        return array_column($this->decode($this->signedIn()->get($path))['resources'] ?? [], 'username');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true) ?? [];
    }

    // </editor-fold>

}
