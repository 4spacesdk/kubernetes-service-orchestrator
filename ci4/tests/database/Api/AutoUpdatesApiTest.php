<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\AutoUpdate;
use App\Fixtures;
use App\Models\AutoUpdateModel;

/**
 * The approval endpoint on the AutoUpdates controller.
 *
 * The webhooks that create pending updates live in AutoUpdateWebhooksApiTest, which is
 * deliberately unauthenticated all the way through - a registry has no token. This one is
 * the opposite half: `approve` is a signed-in operator action, and it is the gate a
 * deployment with `auto_update_require_approval` waits behind.
 *
 * What approval does here is narrow, and worth being precise about: it sets a flag, stamps
 * a date and emits `AutoUpdate_Approved`. **The rollout is not part of the request.** The
 * version only changes when the zmq client picks that event up and calls back into
 * `ZMQ::autoUpdateApproved()`, which is where `rollout()` lives - a different process, out
 * of band, after the response has gone out.
 *
 * That also makes this the file where a test emitting real events would do most harm:
 * without the silenced ZMQProxy that `tests/bootstrap.php` installs, every test below would
 * publish a real approval and a live client would deploy a tag into whatever cluster the
 * development environment is pointed at. Nothing here may be made to emit for real.
 */
class AutoUpdatesApiTest extends ControllerTestCase {

    private const IMAGE = 'registry.example.org/team/api';

    public function testApprovingSetsTheFlagAndStampsTheDate(): void {
        $update = $this->pendingUpdate();

        $body = $this->approve($update->id);

        $stored = $this->reload($update->id);

        $this->assertSame('OK', $body['status']);
        $this->assertTrue((bool) $stored->is_approved);
        $this->assertNotSame('', (string) $stored->approved_date);
    }

    /**
     * The approved update comes back as the resource, so the client does not have to fetch
     * it again to redraw the row it just acted on.
     */
    public function testTheApprovedUpdateIsAnsweredAsTheResource(): void {
        $update = $this->pendingUpdate();

        $body = $this->approve($update->id);

        $this->assertSame((int) $update->id, (int) $body['resource']['id']);
        $this->assertSame('v2.0.0', $body['resource']['next_tag']);
    }

    /**
     * Approving does not touch the deployment's version. Only `rollout()` does that, and
     * the request never reaches it - so as far as the running workload is concerned,
     * nothing has happened by the time the client gets its OK.
     */
    public function testApprovingLeavesTheRunningVersionAlone(): void {
        $deployment = Fixtures::autoUpdatableDeployment([
            'image' => self::IMAGE,
            'version' => 'v1.0.0',
        ]);
        $update = $this->pendingUpdate($deployment->id);

        $this->approve($update->id);

        $reloaded = new \App\Entities\Deployment();
        $reloaded->find($deployment->id);
        $this->assertSame('v1.0.0', $reloaded->version);
    }

    /**
     * The same shape as the update endpoints elsewhere: an id that does not exist is
     * answered with OK rather than refused, and nothing is written.
     */
    public function testAnUnknownAutoUpdateIsRefused(): void {
        $body = $this->approve(999999);

        $this->assertSame('unknown auto update', $body['error'] ?? null);
        $this->assertSame(0, (new AutoUpdateModel())->where('is_approved', true)->find()->count());
    }

    // <editor-fold desc="Helpers">

    /**
     * An unapproved update, written straight through the entity rather than through
     * `CheckForUpdates()` - this file is about what approval does to a row, not about how
     * the row got there.
     */
    private function pendingUpdate(?int $deploymentId = null): AutoUpdate {
        $deploymentId ??= Fixtures::autoUpdatableDeployment([
            'image' => self::IMAGE,
            'version' => 'v1.0.0',
        ])->id;

        $update = new AutoUpdate();
        $update->deployment_id = $deploymentId;
        $update->image = self::IMAGE;
        $update->previous_tag = 'v1.0.0';
        $update->next_tag = 'v2.0.0';
        $update->is_approved = false;
        $update->save();

        return $this->reload($update->id);
    }

    private function reload(int $id): AutoUpdate {
        $update = new AutoUpdate();
        $update->find($id);

        return $update;
    }

    /**
     * @return array<string, mixed> the decoded response
     */
    private function approve(int $id): array {
        $response = $this->signedIn()->put("auto-updates/{$id}/approve");

        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
