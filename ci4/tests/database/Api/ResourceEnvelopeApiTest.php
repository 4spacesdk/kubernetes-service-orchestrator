<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;

/**
 * The shape of a list response, which every generated API client is built against.
 *
 * `ResourceController::_setResources()` is the one place a collection becomes an envelope,
 * and it is handed two different things by its callers. A model's `restGet()` normally
 * answers with an entity collection, but when the request carries `count` it answers with
 * an integer instead - the whole point of that parameter is to ask "how many" without
 * paying for the rows. `_setResources()` has to tell those apart, because a client reading
 * `resources` when only a number was asked for gets nothing, and a client that is handed
 * the rows it deliberately did not ask for pays for a query it wanted to avoid.
 *
 * Both are exercised through the same endpoint here, so the only difference between the
 * two tests is the query parameter - which is exactly the difference the production code
 * branches on.
 */
class ResourceEnvelopeApiTest extends ControllerTestCase {

    /**
     * The normal case: a collection is sent as a count *and* the rows. The count is not
     * derived from `resources` by the client, it is its own field, so it has to agree with
     * what was sent or a paginated list shows the wrong total.
     */
    public function testACollectionIsAnsweredWithBothACountAndTheRows(): void {
        $deployment = Fixtures::deployment();
        $this->migrationJob($deployment->id, 'first-log');
        $this->migrationJob($deployment->id, 'second-log');

        $body = $this->decode($this->signedIn()->get("deployments/{$deployment->id}/migration-jobs"));

        $this->assertSame(2, $body['count']);
        $this->assertCount(2, $body['resources']);
        $this->assertSame(
            ['first-log', 'second-log'],
            array_column($body['resources'], 'log'),
            'the rows themselves are in the envelope, not just how many there are'
        );
    }

    /**
     * `?count=` makes the model answer with a number rather than rows, and the envelope
     * then carries the number alone. Sending `resources` as well would defeat the
     * parameter: the caller asked for a total precisely so the rows would not be loaded,
     * and a list screen that shows a total for ten thousand deployments would be fetching
     * all of them to display one integer.
     */
    public function testACountOnlyRequestIsAnsweredWithTheNumberAndNoRows(): void {
        $deployment = Fixtures::deployment();
        $this->migrationJob($deployment->id, 'first-log');
        $this->migrationJob($deployment->id, 'second-log');

        $body = $this->decode($this->signedIn()->get("deployments/{$deployment->id}/migration-jobs?count=1"));

        $this->assertSame(2, $body['count']);
        $this->assertArrayNotHasKey('resources', $body);
    }

    /**
     * One resource is `resource`, never a one-element `resources`. The two field names are
     * the only thing separating the two shapes, and the generated clients type them
     * differently - an endpoint that answered a single entity as a list would deserialise
     * into the wrong type and break at the call site rather than in the response.
     */
    public function testASingleResourceIsAnsweredUnderItsOwnFieldWithNoCount(): void {
        $deployment = Fixtures::deployment(['version' => '1.0.0']);

        $body = $this->decode($this->signedIn()->put("deployments/{$deployment->id}/version?value=2.0.0"));

        $this->assertSame('2.0.0', $body['resource']['version']);
        $this->assertArrayNotHasKey('resources', $body);
        $this->assertArrayNotHasKey('count', $body);
    }

    // <editor-fold desc="Helpers">

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    private function migrationJob(int $deploymentId, string $log): void {
        $this->db->table('migration_jobs')->insert([
            'deployment_id' => $deploymentId,
            'status' => 'completed',
            'log' => $log,
            'command' => 'php spark migrate',
            'image' => 'registry/app:1.0',
            'created' => date('Y-m-d H:i:s'),
        ]);
    }

    // </editor-fold>

}
