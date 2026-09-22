<?php namespace App\Tests\Database\Audit;

use App\ControllerTestCase;
use App\Fixtures;

/**
 * What the trail says about requests: a change is the signed-in user's, with the client and
 * the address it came from, and the named actions are recorded where they happen.
 *
 * A sample of the routes. `AuditedRoutesTest` holds every one of them to a decision, and to a
 * `Audit::Record` for each action it names.
 */
class AuditContextApiTest extends ControllerTestCase {

    public function testAChangeThroughTheApiIsTheSignedInUsers(): void {
        $service = Fixtures::databaseService(['host' => 'old.internal']);

        $body = json_decode((string) $this->withBodyFormat('json')->signedIn()
            ->patch("database_services/{$service->id}", ['host' => 'new.internal'])
            ->response()->getBody(), true);
        $this->assertSame('OK', $body['status'], json_encode($body));

        $event = $this->db->table('audit_events')
            ->where('resource_type', 'DatabaseService')
            ->where('resource_id', $service->id)
            ->orderBy('id', 'desc')
            ->get()->getRowArray();
        $this->assertSame('updated', $event['action']);
        $this->assertSame($this->signedInUserId(), (int) $event['user_id']);
        $this->assertStringStartsWith('phpunit-', $event['client_id']);
        $this->assertSame('api', $event['source']);
        $this->assertNotEmpty($event['ip_address']);
        $this->assertSame(['host' => ['old.internal', 'new.internal']], json_decode($event['details'], true)['changes']);
    }

    /**
     * An `@audit entity` route: the entity records the change itself.
     */
    public function testARouteThatSavesAnEntityIsRecordedByIt(): void {
        $workspace = Fixtures::workspace(['name_readable' => 'Before']);

        $this->signedIn()->put("workspaces/{$workspace->id}/name?value=After");

        $event = $this->lastEvent('Workspace', (int) $workspace->id);
        $this->assertSame('updated', $event['action']);
        $this->assertSame(['Before', 'After'], json_decode($event['details'], true)['changes']['name_readable']);
        $this->assertSame('After', $event['resource_name']);
        $this->assertSame('After', $event['resource_name']);
        $this->assertSame($this->signedInUserId(), (int) $event['user_id']);
    }

    public function testANamedActionIsRecorded(): void {
        $image = Fixtures::containerImage();

        $this->signedIn()->put("container-images/{$image->id}/scan");

        $event = $this->lastEvent('ContainerImage', (int) $image->id);
        $this->assertSame('container_image.scan', $event['action']);
        $this->assertSame($this->signedInUserId(), (int) $event['user_id']);
    }

    /**
     * Before the sign-in there is nobody to be - the user is named.
     */
    public function testASignInIsRecordedAsTheUsers(): void {
        $user = Fixtures::user(['username' => 'auditor@example.org', 'password' => 'the-right-one']);

        $this->post('login', ['username' => 'auditor@example.org', 'password' => 'the-right-one']);

        $event = $this->lastEvent('User', (int) $user->id);
        $this->assertSame('user.sign_in', $event['action']);
        $this->assertSame((int) $user->id, (int) $event['user_id']);
        $this->assertSame('web', $event['source']);
    }

    /**
     * @return array<string, mixed>
     */
    private function lastEvent(string $type, int $id): array {
        return $this->db->table('audit_events')
            ->where('resource_type', $type)
            ->where('resource_id', $id)
            ->orderBy('id', 'desc')
            ->get()->getRowArray() ?? [];
    }

}
