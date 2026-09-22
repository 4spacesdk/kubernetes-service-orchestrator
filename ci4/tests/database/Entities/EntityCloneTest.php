<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\Deployment;
use App\Fixtures;

/**
 * The snapshot of an entity that is pushed to every connected browser.
 *
 * When a deployment or a workspace finishes deploying, kso pushes the entity as a
 * change event, and every open browser writes what arrives straight into the
 * row the user is looking at. `getClone()` is what decides the contents of that message,
 * so this is not an internal helper - it is the payload a user's screen is updated with.
 *
 * Two decisions are worth holding on to; `getClone()`'s own docblock says the same two, and
 * these are what hold them.
 */
class EntityCloneTest extends DatabaseTestCase {

    public function testACloneCarriesTheRowsOwnColumns(): void {
        $deployment = Fixtures::deployableDeployment(['name' => 'web', 'namespace' => 'acme']);

        $clone = $deployment->getClone();

        // Of the same type as the entity it came from, because the receiving browser is told
        // which resource the event belongs to and expects that resource's fields.
        $this->assertInstanceOf(Deployment::class, $clone);
        $this->assertSame($deployment->id, $clone->id);
        $this->assertSame('web', $clone->name);
        $this->assertSame('acme', $clone->namespace);
        $this->assertSame('1.2.3', $clone->version);
    }

    /**
     * The premise the filter rests on: the row `getClone()` reads is not the row as it was
     * loaded. OrmExtension's `completeSave()` calls `syncOriginal()`, which folds whatever
     * is in `attributes` at that moment into `original` - loaded relations and all, each
     * serialised into a blob carrying the whole related row.
     *
     * Without this, the test below would pass on an entity whose `original` never held a
     * relation in the first place, and the filter it is there to prove would not be doing
     * anything.
     */
    public function testARowRememberedAfterASaveHoldsWhateverRelationsWereLoaded(): void {
        $deployment = Fixtures::deployableDeployment();
        $deployment->findDeploymentSpecification();
        $deployment->workspace->find();
        $deployment->save();

        $original = (new \ReflectionProperty($deployment, 'original'))->getValue($deployment);

        $this->assertArrayHasKey('workspace', $original);
        $this->assertArrayHasKey('deployment_specification', $original);
        $this->assertStringContainsString(
            '"namespace"',
            $original['workspace'],
            'the whole related row, not a reference to it'
        );
    }

    /**
     * And what that costs when it is not filtered out: the blob lands where a column is
     * expected, and turning the payload into something sendable is a fatal error rather
     * than a heavy message.
     */
    public function testARelationCopiedAcrossAsAColumnCannotBeSent(): void {
        $deployment = Fixtures::deployableDeployment();
        $deployment->workspace->find();
        $deployment->save();
        $unfiltered = new Deployment();
        foreach ((new \ReflectionProperty($deployment, 'original'))->getValue($deployment) as $key => $value) {
            $unfiltered->{$key} = $value;
        }

        $this->expectException(\Error::class);

        $unfiltered->toArray();
    }

    /**
     * A deployment being deployed has its specification and its workspace loaded - the
     * steps need them - and a step saving the deployment folds whatever is loaded into the
     * row it remembers. Without the filter on table fields, the change event for one
     * deployment would carry that whole tree along: the workspace, the specification and
     * everything those two have loaded in turn. Every browser would be sent it, and would
     * overwrite its own copies of those records with whatever this deployment happened to
     * have in memory.
     */
    public function testACloneLeavesLoadedRelationsBehind(): void {
        $deployment = Fixtures::deployableDeployment();
        $deployment->findDeploymentSpecification();
        $deployment->workspace->find();
        $deployment->save();

        $clone = $deployment->getClone();

        // The entity itself does carry them, so the difference below is the clone's doing
        // and not a relation that was never loaded in the first place.
        $this->assertArrayHasKey('workspace', $deployment->toArray());
        $this->assertArrayHasKey('deployment_specification', $deployment->toArray());

        $this->assertArrayNotHasKey('workspace', $clone->toArray());
        $this->assertArrayNotHasKey('deployment_specification', $clone->toArray());
        $this->assertSame($deployment->workspace_id, $clone->workspace_id, 'the reference is kept');
    }

    /**
     * The same protection seen from the workspace side, where the list is attached in
     * memory and never saved. `Workspace::deploy()` assigns the deployments it just
     * deployed to the workspace before announcing it, so a clone taken from the live
     * attributes would push every one of those deployments inside the workspace event.
     */
    public function testACloneLeavesARelationAttachedInMemoryBehind(): void {
        $workspace = Fixtures::workspace();
        $deployment = Fixtures::deployment(['workspace_id' => $workspace->id]);
        $workspace->deployments = $deployment;

        $clone = $workspace->getClone();

        $this->assertArrayHasKey('deployments', $workspace->toArray());
        $this->assertArrayNotHasKey('deployments', $clone->toArray());
    }

    /**
     * The surprising one. `getClone()` reads the row as it was loaded from the database,
     * not the values the entity is holding now, so anything changed and not yet saved is
     * missing from the snapshot. That is right for the way it is used today - the event is
     * sent after the save, so the row that was loaded and the row in the database agree -
     * but it means announcing a change before saving it sends browsers the old value and
     * they show it as the new one. Nothing about the call says so.
     */
    public function testACloneKeepsTheValuesTheRowWasLoadedWithRatherThanTheCurrentOnes(): void {
        $deployment = Fixtures::deployment(['name' => 'web', 'status' => \DeploymentStatusTypes::Draft]);
        $deployment->name = 'renamed';
        $deployment->status = \DeploymentStatusTypes::Synced;

        $clone = $deployment->getClone();

        $this->assertSame('web', $clone->name);
        $this->assertSame(\DeploymentStatusTypes::Draft, $clone->status);
        $this->assertSame('renamed', $deployment->name, 'and the entity itself is left alone');
    }

    /**
     * The snapshot is taken once and sent asynchronously, so whatever happens to the
     * deployment afterwards must not reach into the message already on its way out.
     */
    public function testTheCloneIsDetachedFromTheEntityItCameFrom(): void {
        $deployment = Fixtures::deployment(['name' => 'web']);

        $clone = $deployment->getClone();
        $deployment->name = 'renamed';
        $deployment->save();

        $this->assertSame('web', $clone->name);
    }

}
