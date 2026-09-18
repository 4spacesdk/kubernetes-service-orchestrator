<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\InitContainer;
use App\Fixtures;
use App\Models\InitContainerEnvironmentVariableModel;

/**
 * `PUT /init-containers/{id}/environment-variables`, the one endpoint on this controller.
 *
 * An init container runs before the application container and usually exists to wait for
 * something or to migrate something, so its environment is where the database host, the
 * credentials and the timeouts live. The dialog sends the whole list on every save, and
 * **the list that arrives replaces the list that is there** - nothing is merged and
 * nothing is matched by id, so an empty list is a valid instruction to delete every
 * variable the container had. That is the contract the dialog relies on, and it is also
 * what makes a client bug cost the whole set in one call.
 */
class InitContainersApiTest extends ControllerTestCase {

    public function testAVariableArrivesAsNameAndValueAndIsStoredAgainstTheContainer(): void {
        $container = Fixtures::initContainer();

        $this->putValues($container, [
            ['name' => 'DB_HOST', 'value' => 'mysql.internal'],
            ['name' => 'WAIT_TIMEOUT', 'value' => '120'],
        ]);

        $this->assertSame(
            ['DB_HOST' => 'mysql.internal', 'WAIT_TIMEOUT' => '120'],
            $this->variables($container)
        );
    }

    /**
     * The replacement rule. A save that drops a variable from the dialog has to remove it,
     * or an init container keeps reading a database host the user deleted.
     */
    public function testASecondCallReplacesTheVariablesRatherThanAddingToThem(): void {
        $container = Fixtures::initContainer();

        $this->putValues($container, [
            ['name' => 'DB_HOST', 'value' => 'old.internal'],
            ['name' => 'OBSOLETE', 'value' => 'yes'],
        ]);
        $this->putValues($container, [
            ['name' => 'DB_HOST', 'value' => 'new.internal'],
        ]);

        $this->assertSame(['DB_HOST' => 'new.internal'], $this->variables($container));
    }

    /**
     * The end of the same rule: an empty list is not "no change", it is "remove them all",
     * and the answer is still OK.
     */
    public function testAnEmptyListRemovesEveryVariable(): void {
        $container = Fixtures::initContainer();
        $this->putValues($container, [['name' => 'DB_HOST', 'value' => 'mysql.internal']]);

        $body = $this->putValues($container, []);

        $this->assertSame('OK', $body['status']);
        $this->assertSame([], $this->variables($container));
    }

    /**
     * The delete that precedes the write is scoped to one container. It is written as
     * `$this->init_container_environment_variables->find()->deleteAll()`, and a relation
     * that lost its `where` would empty the table instead - every init container in the
     * installation loses its environment on the next save of any one of them.
     */
    public function testSavingOneContainerLeavesAnotherContainersVariablesAlone(): void {
        $mine = Fixtures::initContainer(['name' => 'wait-for-db']);
        $other = Fixtures::initContainer(['name' => 'migrate']);
        $this->putValues($other, [['name' => 'KEEP_ME', 'value' => 'please']]);

        $this->putValues($mine, [['name' => 'DB_HOST', 'value' => 'mysql.internal']]);

        $this->assertSame(['KEEP_ME' => 'please'], $this->variables($other));
    }

    /**
     * An unknown id is answered with OK and nothing is written - the same shape as the
     * deployment and specification endpoints, and the same objection: a dialog saving
     * against a container somebody else deleted is told it worked. See FEAT-9.
     *
     * The rows matter as much as the status. The entity's `Create()` saves each variable
     * before it is attached to anything, so a guard moved one line down would leave a
     * variable behind in the table belonging to no container at all.
     */
    public function testAnUnknownContainerReportsSuccessAndWritesNothing(): void {
        $body = $this->putTo('init-containers/999999/environment-variables', [
            ['name' => 'ORPHAN', 'value' => 'should not exist'],
        ]);

        $this->assertSame('OK', $body['status']);
        $this->assertSame(
            0,
            (new InitContainerEnvironmentVariableModel())->where('name', 'ORPHAN')->find()->count()
        );
    }

    /**
     * The endpoint answers with the container it just saved, which is what the dialog
     * redraws from. It is the plain entity - an init container holds no credentials, so
     * unlike `Systems` this controller has nothing to hide behind an allow list.
     */
    public function testTheAnswerCarriesTheContainerThatWasSaved(): void {
        $container = Fixtures::initContainer(['name' => 'wait-for-db']);

        $body = $this->putValues($container, [['name' => 'DB_HOST', 'value' => 'mysql.internal']]);

        $this->assertSame((int) $container->id, (int) $body['resource']['id']);
        $this->assertSame('wait-for-db', $body['resource']['name']);
    }

    // <editor-fold desc="Helpers">

    /**
     * @param array<array<string, mixed>> $values
     * @return array<string, mixed> the decoded response
     */
    private function putValues(InitContainer $container, array $values): array {
        return $this->putTo("init-containers/{$container->id}/environment-variables", $values);
    }

    /**
     * @param array<array<string, mixed>> $values
     * @return array<string, mixed> the decoded response
     */
    private function putTo(string $path, array $values): array {
        $response = $this->withBodyFormat('json')->signedIn()->put($path, ['values' => $values]);

        return json_decode((string) $response->response()->getBody(), true);
    }

    /**
     * What the container's environment is now, as name => value.
     *
     * @return array<string, string>
     */
    private function variables(InitContainer $container): array {
        $rows = (new InitContainerEnvironmentVariableModel())
            ->where('init_container_id', $container->id)
            ->orderBy('id', 'asc')
            ->find();

        $variables = [];
        foreach ($rows as $row) {
            $variables[$row->name] = $row->value;
        }

        return $variables;
    }

    // </editor-fold>

}
