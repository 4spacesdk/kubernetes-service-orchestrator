<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use App\Models\AutoUpdateModel;

/**
 * The two registry webhooks, which are the only endpoints an outside system posts to.
 *
 * Harbor and Azure Container Registry call these when a tag is pushed, and kso turns that
 * into a pending update for every deployment running that image whose tag pattern matches.
 *
 * **Neither webhook authenticates or verifies anything.** They are public by necessity -
 * a registry has no token - but nothing checks a shared secret, a signature or the source
 * address either, and the payload is trusted as it arrives. Anyone who can reach the API
 * can post a crafted body and have rows written. See SEC-12; the first test here holds
 * that behaviour so a fix is visible.
 *
 * The tests keep `auto_update_require_approval` on unless they are specifically about
 * approval, so nothing here approves an update by accident.
 */
class AutoUpdateWebhooksApiTest extends ControllerTestCase {

    private const IMAGE = 'registry.example.org/team/api';

    /**
     * No token, no signature, no shared secret - a plain POST is enough to write a row.
     * See SEC-12.
     */
    public function testTheWebhookIsOpenToAnyoneWhoCanReachIt(): void {
        $deployment = $this->autoUpdatingDeployment();

        $body = $this->harborPush('v2.0.0');

        $this->assertSame('OK', $body['status']);
        $this->assertCount(1, $this->updatesFor($deployment->id));
    }

    public function testAHarborPushCreatesAPendingUpdate(): void {
        $deployment = $this->autoUpdatingDeployment(['version' => 'v1.0.0']);

        $this->harborPush('v2.0.0');

        $update = $this->updatesFor($deployment->id)[0];

        $this->assertSame(self::IMAGE, $update->image);
        $this->assertSame('v1.0.0', $update->previous_tag);
        $this->assertSame('v2.0.0', $update->next_tag);
        $this->assertFalse((bool) $update->is_approved);
    }

    /**
     * Harbor sends several event types on the same hook. Only a pushed artifact means a
     * new tag exists.
     */
    public function testOtherHarborEventsAreIgnored(): void {
        $deployment = $this->autoUpdatingDeployment();

        $this->postJson('auto-updates/webhooks/harbor', [
            'type' => 'DELETE_ARTIFACT',
            'event_data' => ['resources' => [['resource_url' => self::IMAGE . ':v2.0.0', 'tag' => 'v2.0.0']]],
        ]);

        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    public function testAHarborPayloadWithoutAResourceUrlIsIgnored(): void {
        $deployment = $this->autoUpdatingDeployment();

        $this->postJson('auto-updates/webhooks/harbor', [
            'type' => 'PUSH_ARTIFACT',
            'event_data' => ['resources' => [['resource_url' => '', 'tag' => 'v2.0.0']]],
        ]);

        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    /**
     * A push payload missing `resource_url` is read without a guard, so it ends as an
     * undefined-key error rather than as a refusal. Unauthenticated callers can trigger it
     * with four bytes of JSON. See SEC-12.
     */
    public function testAMalformedHarborPayloadIsAnUnhandledError(): void {
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessageMatches('/resource_url/');

        $this->postJson('auto-updates/webhooks/harbor', [
            'type' => 'PUSH_ARTIFACT',
            'event_data' => ['resources' => [['tag' => 'v2.0.0']]],
        ]);
    }

    /**
     * The image name is split off the resource url at the last colon, because a registry
     * host can carry a port and the tag is what follows the final one.
     */
    public function testTheImageIsTakenFromTheResourceUrlUpToTheLastColon(): void {
        $deployment = $this->autoUpdatingDeployment(['image' => 'registry.example.org:5000/team/api']);

        $this->postJson('auto-updates/webhooks/harbor', [
            'type' => 'PUSH_ARTIFACT',
            'event_data' => ['resources' => [[
                'resource_url' => 'registry.example.org:5000/team/api:v2.0.0',
                'tag' => 'v2.0.0',
            ]]],
        ]);

        $this->assertSame('registry.example.org:5000/team/api', $this->updatesFor($deployment->id)[0]->image);
    }

    /**
     * Azure sends the host and the repository separately, and kso joins them into the
     * image name the deployment is matched on.
     */
    public function testTheAzureWebhookJoinsHostAndRepository(): void {
        $deployment = $this->autoUpdatingDeployment();

        $this->postJson('auto-updates/webhooks/azure-container-registry', [
            'action' => 'push',
            'request' => ['host' => 'registry.example.org'],
            'target' => ['repository' => 'team/api', 'tag' => 'v2.0.0'],
        ]);

        $this->assertSame(self::IMAGE, $this->updatesFor($deployment->id)[0]->image);
    }

    public function testAnAzureEventThatIsNotAPushIsIgnored(): void {
        $deployment = $this->autoUpdatingDeployment();

        $this->postJson('auto-updates/webhooks/azure-container-registry', [
            'action' => 'delete',
            'request' => ['host' => 'registry.example.org'],
            'target' => ['repository' => 'team/api', 'tag' => 'v2.0.0'],
        ]);

        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    public function testADeploymentRunningAnotherImageIsLeftAlone(): void {
        $deployment = $this->autoUpdatingDeployment(['image' => 'registry.example.org/team/other']);

        $this->harborPush('v2.0.0');

        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    /**
     * The tag pattern is the second filter. It is anchored only at the end, so `v[0-9]`
     * also matches `not-v2` - pinned in AutoUpdateCheckForUpdatesTest, and the reason a
     * pattern is not the security boundary it looks like.
     */
    public function testATagThatDoesNotMatchThePatternIsIgnored(): void {
        $deployment = $this->autoUpdatingDeployment(['auto_update_tag_regex' => 'v[0-9]+\.[0-9]+\.[0-9]+']);

        $this->harborPush('nightly');

        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    /**
     * Without required approval the update is marked approved as it is created. That only
     * sets a flag and emits an event - nothing in this repository reads it back - so the
     * webhook writes a record rather than changing a running version by itself.
     */
    public function testAnUpdateIsApprovedImmediatelyWhenApprovalIsNotRequired(): void {
        $deployment = $this->autoUpdatingDeployment(['auto_update_require_approval' => false]);

        $this->harborPush('v2.0.0');

        $this->assertTrue((bool) $this->updatesFor($deployment->id)[0]->is_approved);
    }

    // <editor-fold desc="Helpers">

    /**
     * @param array<string, mixed> $overrides
     */
    private function autoUpdatingDeployment(array $overrides = []): \App\Entities\Deployment {
        return Fixtures::autoUpdatableDeployment(array_merge([
            'image' => self::IMAGE,
            'version' => 'v1.0.0',
            'auto_update_tag_regex' => 'v[0-9]+\.[0-9]+\.[0-9]+',
            'auto_update_require_approval' => true,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function harborPush(string $tag): array {
        return $this->postJson('auto-updates/webhooks/harbor', [
            'type' => 'PUSH_ARTIFACT',
            'event_data' => ['resources' => [[
                'resource_url' => self::IMAGE . ':' . $tag,
                'tag' => $tag,
            ]]],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postJson(string $path, array $payload): array {
        // No signedIn() anywhere in this file: that is the point.
        $response = $this->withBodyFormat('json')->post($path, $payload);

        return json_decode((string) $response->response()->getBody(), true);
    }

    /**
     * @return \App\Entities\AutoUpdate[]
     */
    private function updatesFor(int $deploymentId): array {
        return (new AutoUpdateModel())
            ->where('deployment_id', $deploymentId)
            ->orderBy('id', 'asc')
            ->find()
            ->all ?? [];
    }

    // </editor-fold>

}
