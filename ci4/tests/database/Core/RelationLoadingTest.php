<?php namespace App\Tests\Database\Core;

use App\DatabaseTestCase;
use App\Fixtures;
use App\Models\DeploymentModel;
use App\Models\WorkspaceModel;

/**
 * A relation read off an entity, asked more than once.
 *
 * `$deployment->workspace` builds the related entity the first time it is read and keeps it
 * on the deployment, so every later read is the same object - and the condition that ties
 * it to *this* deployment has to survive every query that object runs. It did not: the
 * builder is reset once a query has run, and the entity's model was replaced by the one
 * carried on the result, so a second `find()` was a query with no condition and no join at
 * all. `SELECT * FROM workspaces` - and the entity took the first row of the answer.
 *
 * The guard the code is written with, `if (!$x->relation->exists()) $x->relation->find();`,
 * does not catch it. It only holds when the first call found something: when the related row
 * is missing, `exists()` stays false, the guard is open, and it is the second call that
 * answers with somebody else's row. Fifty-odd places in kso are written that way.
 */
class RelationLoadingTest extends DatabaseTestCase {

    /**
     * The one that was wrong. A deployment whose workspace has been deleted must have no
     * workspace, however many times it is asked.
     *
     * The other workspace is the arrangement that matters: without a row for the query to
     * land on, an unconditioned `SELECT *` answers with nothing and the test passes against
     * the fault.
     */
    public function testARelationWhoseRowIsGoneStaysGoneWhenItIsAskedAgain(): void {
        $somebodyElse = Fixtures::workspace(['name_readable' => 'somebody-else', 'name_system' => 'somebody-else', 'namespace' => 'somebody-else']);
        $deployment = $this->deploymentWhoseWorkspaceIsGone();

        for ($ask = 1; $ask <= 3; $ask++) {
            if ($deployment->workspace_id && !$deployment->workspace->exists()) {
                $deployment->workspace->find();
            }

            $this->assertFalse($deployment->workspace->exists(), "ask {$ask}");
            $this->assertNotSame((int) $somebodyElse->id, (int) $deployment->workspace->id, "ask {$ask}");
        }
    }

    /**
     * And the other way: a relation that is there is still its own row when it is read
     * again, rather than the first row of the table.
     */
    public function testARelationThatIsThereIsStillItsOwnRowWhenItIsAskedAgain(): void {
        Fixtures::workspace(['name_readable' => 'somebody-else', 'name_system' => 'somebody-else', 'namespace' => 'somebody-else']);
        $mine = Fixtures::workspace(['name_readable' => 'mine', 'name_system' => 'mine', 'namespace' => 'mine']);
        $deployment = (new DeploymentModel())->find(Fixtures::deployment(['workspace_id' => $mine->id])->id);

        if (!$deployment->workspace->exists()) $deployment->workspace->find();
        $deployment->workspace->find();

        $this->assertTrue($deployment->workspace->exists());
        $this->assertSame('mine', $deployment->workspace->name_readable);
    }

    /**
     * The half underneath it: a model that runs a second query with a related condition
     * joins the related table again.
     *
     * `addRelatedTable()` keeps a list of the joins it has written so it does not write one
     * twice, and nothing used to clear that list when the query it belonged to was over.
     * The second query therefore had the condition and not the join, and the database was
     * asked about a table that was not in the statement.
     */
    public function testAModelAskedTwiceWithARelatedConditionJoinsBothTimes(): void {
        $workspace = Fixtures::workspace(['name_readable' => 'mine', 'name_system' => 'mine', 'namespace' => 'mine']);
        $deployment = Fixtures::deployment(['workspace_id' => $workspace->id]);

        $model = new WorkspaceModel();

        $first = $model->whereRelated(DeploymentModel::class, 'id', $deployment->id)->find();
        $second = $model->whereRelated(DeploymentModel::class, 'id', $deployment->id)->find();

        $this->assertSame('mine', $first->name_readable);
        $this->assertSame('mine', $second->name_readable);
    }

    /**
     * A deployment read back from the database, pointing at a workspace that is no longer
     * there. Written and then deleted, rather than given an id nothing ever used, so the
     * row is one the database really could have.
     */
    private function deploymentWhoseWorkspaceIsGone(): \App\Entities\Deployment {
        $workspace = Fixtures::workspace(['name_readable' => 'gone', 'name_system' => 'gone', 'namespace' => 'gone']);
        $deployment = Fixtures::deployment(['workspace_id' => $workspace->id]);
        $workspace->delete();

        return (new DeploymentModel())->find($deployment->id);
    }

}
