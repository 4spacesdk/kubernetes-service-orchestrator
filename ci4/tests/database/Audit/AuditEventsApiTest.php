<?php namespace App\Tests\Database\Audit;

use App\ControllerTestCase;
use App\Fixtures;
use CodeIgniter\Exceptions\PageNotFoundException;
use RestExtension\Exceptions\UnauthorizedException;

/**
 * `GET /audit_events`, the trail to read: one thing's history, and who did it.
 */
class AuditEventsApiTest extends ControllerTestCase {

    public function testOneThingsHistoryWithWhoDidIt(): void {
        $service = Fixtures::databaseService(['host' => 'old.internal']);
        $this->withBodyFormat('json')->signedIn()->patch("database_services/{$service->id}", ['host' => 'new.internal']);

        $body = $this->decode($this->signedIn()->get(
            "audit_events?filter=resource_type:DatabaseService,resource_id:{$service->id}&ordering=id&include=user"
        ));

        $this->assertSame('OK', $body['status'], json_encode($body));
        $this->assertSame(['created', 'updated'], array_column($body['resources'], 'action'));
        $this->assertSame($this->signedInUserId(), (int) $body['resources'][1]['user']['id']);
        $this->assertSame(['old.internal', 'new.internal'], json_decode($body['resources'][1]['details'], true)['changes']['host']);
    }

    /**
     * The filters the Audit Trail page sends: who, source, action, what, and a period - the
     * period with a time in it, whose colons the filter syntax has to leave alone.
     */
    public function testTheFiltersThePageSends(): void {
        $service = Fixtures::databaseService(['name' => 'filtered']);
        $this->withBodyFormat('json')->signedIn()->patch("database_services/{$service->id}", ['host' => 'new.internal']);
        $today = date('Y-m-d');
        $userId = $this->signedInUserId();

        $body = $this->decode($this->signedIn()->get('audit_events?filter='
            . "user_id:[{$userId}],source:[api],action:[updated,deleted],resource_type:[DatabaseService],"
            . "created:>={$today} 00:00:00,created:<={$today} 23:59:59"));

        $this->assertSame('OK', $body['status'], json_encode($body));
        $this->assertSame([(int) $service->id], array_map('intval', array_column($body['resources'], 'resource_id')));
        $this->assertSame(['updated'], array_column($body['resources'], 'action'));
    }

    /**
     * Nobody edits the trail through the API - not even to tidy it.
     */
    public function testTheTrailCannotBeChangedThroughTheApi(): void {
        foreach (['post' => 'audit_events', 'patch' => 'audit_events/1', 'delete' => 'audit_events/1'] as $method => $path) {
            try {
                $response = $this->withBodyFormat('json')->signedIn()->call($method, $path, ['action' => 'forged']);
                $this->fail("{$method} {$path} answered {$response->response()->getStatusCode()}");
            } catch (PageNotFoundException) {
            }
        }
        $this->assertSame(0, $this->db->table('audit_events')->where('action', 'forged')->countAllResults());
    }

    public function testTheTrailIsNotPublic(): void {
        $this->expectException(UnauthorizedException::class);

        $this->get('audit_events');
    }

    private function decode($response): array {
        return json_decode((string) $response->response()->getBody(), true) ?? [];
    }

}
