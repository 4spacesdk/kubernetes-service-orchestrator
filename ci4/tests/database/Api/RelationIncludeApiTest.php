<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;

/**
 * What `?include=` is allowed to hand back, and why the model interface is load-bearing.
 *
 * Around thirty models have no REST route of their own - `DeploymentVolumeModel`,
 * `EnvironmentVariableModel`, `LabelModel` and the rest of the sub-resources. Reading their
 * coverage it looks as though nothing ever enters them, and the tidy conclusion is that
 * their REST methods are dead and the `ResourceModelInterface` on them is decoration.
 *
 * It is not, and this file is here to say so out loud.
 *
 * `ResourceModelTrait::applyIncludeMany()` fetches an included relation by calling
 * `restGet()` on the **child's** model, with a filter naming the parent. `restGet()` opens
 * with:
 *
 * ```php
 * if ($this instanceof ResourceModelInterface) {
 *     // apply filters, includes, limit, ordering
 * } else
 *     return $this->find();   // the whole table
 * ```
 *
 * So the interface is what makes the parent filter apply at all. Take it off a child model
 * and the filter is dropped in silence: every row of that table is attached to every
 * parent, and one workspace's deployment answers with another workspace's volumes. The
 * request still returns 200, the shape of the response is unchanged, and nothing in a code
 * review looks wrong - which is exactly why it is pinned here rather than left to be
 * rediscovered.
 *
 * Verified by removing `implements ResourceModelInterface` from `DeploymentVolumeModel`:
 * `testAnIncludedCollectionHoldsOnlyTheParentsOwnRows` then returns both volumes and fails.
 */
class RelationIncludeApiTest extends ControllerTestCase {

    /**
     * The one that matters: an included has-many is filtered to its parent.
     *
     * Two deployments, one volume each. Asking for the first deployment's volumes must
     * answer with one volume, and it must be the first deployment's.
     */
    public function testAnIncludedCollectionHoldsOnlyTheParentsOwnRows(): void {
        [$mine, $theirs] = $this->twoDeploymentsWithAVolumeEach();

        $volumes = $this->includedVolumesOf($mine->id);

        $this->assertCount(1, $volumes, 'an included relation must not reach past its parent');
        $this->assertSame('/mine', $volumes[0]['mount_path']);
        $this->assertSame((int) $mine->id, (int) $volumes[0]['deployment_id']);

        // Said the other way round, because the failure this guards against is a superset
        // rather than a wrong row: the other deployment's volume must not be in there.
        $this->assertNotContains('/theirs', array_column($volumes, 'mount_path'));
        $this->assertNotContains((int) $theirs->id, array_map('intval', array_column($volumes, 'deployment_id')));
    }

    /**
     * The same for a collection request, where the extension takes a different branch.
     *
     * `applyIncludeMany()` runs once per parent in the list, so a leak here would give every
     * deployment in the response every volume in the database.
     */
    public function testEachRowInAListKeepsItsOwnIncludedRows(): void {
        [$mine, $theirs] = $this->twoDeploymentsWithAVolumeEach();

        $resources = $this->decode($this->signedIn()->get('deployments?include=deployment_volume'))['resources'] ?? [];
        $byId = array_column($resources, null, 'id');

        $this->assertArrayHasKey($mine->id, $byId);
        $this->assertArrayHasKey($theirs->id, $byId);

        $this->assertSame(['/mine'], array_column($byId[$mine->id]['deployment_volumes'], 'mount_path'));
        $this->assertSame(['/theirs'], array_column($byId[$theirs->id]['deployment_volumes'], 'mount_path'));
    }

    /**
     * A deployment with no rows of its own does not get everyone else's.
     *
     * This is the case a missing parent filter cannot be told apart from by looking at a
     * single populated parent, and it is the cheapest one to get wrong.
     *
     * Note what it does *not* say: that the field is an empty list. It is not there at all -
     * `applyIncludeMany()` leaves an empty collection out of the response rather than
     * writing `[]`, so a client cannot iterate the field without checking for it first.
     * That is asserted here directly rather than through the helper, because the helper
     * ends in `?? []` and would report an absent field and an empty one as the same thing.
     */
    public function testAParentWithNoRowsGetsNobodyElses(): void {
        [, $theirs] = $this->twoDeploymentsWithAVolumeEach();
        $empty = Fixtures::deployment([
            'workspace_id' => $theirs->workspace_id,
            'deployment_specification_id' => $theirs->deployment_specification_id,
            'name' => 'empty',
        ]);

        $resource = $this->decode(
            $this->signedIn()->get("deployments/{$empty->id}?include=deployment_volume")
        )['resource'] ?? [];

        $this->assertArrayNotHasKey('deployment_volumes', $resource);
    }

    /**
     * Today's behaviour: a by-id read carries every relation, asked for or not.
     *
     * `applyRestGetOneRelations()` walks `getRelations()` and loads all of them, minus
     * whatever the model names in `ignoredRestGetOnRelations()`. `DeploymentModel` names
     * two - migration jobs and auto updates - so somebody has already met the cost of this
     * once.
     *
     * It is worth pinning because it is the route a secret travels without anyone asking
     * for it: `GET /deployments/{id}` pulls in the deployment's database service, and that
     * entity still returns `pass` in cleartext. The include parameter is not the
     * only way in.
     *
     * If the extension ever starts honouring the include list here, this test is the one
     * that will say so - and that would be an improvement, not a regression.
     */
    public function testAByIdReadCarriesEveryRelationWithoutBeingAsked(): void {
        [$mine] = $this->twoDeploymentsWithAVolumeEach();

        $resource = $this->decode($this->signedIn()->get("deployments/{$mine->id}"))['resource'] ?? [];

        $this->assertArrayHasKey('deployment_volumes', $resource);
        $this->assertSame(['/mine'], array_column($resource['deployment_volumes'], 'mount_path'));

        // And the two the model explicitly holds back.
        $this->assertArrayNotHasKey('migration_jobs', $resource);
        $this->assertArrayNotHasKey('auto_updates', $resource);
    }

    // <editor-fold desc="Arrangement">

    /**
     * Two deployments in one workspace, one volume each, distinguishable by mount path.
     *
     * One workspace on purpose: a leak that only crossed workspaces would be caught by the
     * cheaper arrangement anyway, and this one also catches a filter that groups by
     * workspace instead of by deployment.
     *
     * @return array{0: \App\Entities\Deployment, 1: \App\Entities\Deployment}
     */
    private function twoDeploymentsWithAVolumeEach(): array {
        $workspace = Fixtures::workspace();
        $specification = Fixtures::deploymentSpecification();

        $mine = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
            'name' => 'mine',
        ]);
        $theirs = Fixtures::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
            'name' => 'theirs',
        ]);

        Fixtures::deploymentVolume(['deployment_id' => $mine->id, 'mount_path' => '/mine']);
        Fixtures::deploymentVolume(['deployment_id' => $theirs->id, 'mount_path' => '/theirs']);

        return [$mine, $theirs];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function includedVolumesOf(int $deploymentId): array {
        $response = $this->signedIn()->get("deployments/{$deploymentId}?include=deployment_volume");

        return $this->decode($response)['resource']['deployment_volumes'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode($response): array {
        return json_decode((string) $response->response()->getBody(), true) ?? [];
    }

    // </editor-fold>

}
