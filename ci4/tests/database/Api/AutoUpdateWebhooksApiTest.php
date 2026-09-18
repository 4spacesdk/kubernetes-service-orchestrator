<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\ContainerRegistry;
use App\Fixtures;
use App\Models\AutoUpdateModel;

/**
 * The two registry webhooks, which are the only endpoints an outside system posts to.
 *
 * Harbor and Azure Container Registry call these when a tag is pushed, and kso turns that
 * into a pending update for every deployment running that image whose tag pattern matches.
 *
 * They are public by necessity - a registry has no token - so since INT-1c each call has
 * to prove itself instead: the url names the registry connection, and the call carries
 * `Authorization: Bearer <secret>`, a secret kso made when it set the webhook up. Anything
 * else is refused, including a connection kso never set a webhook up for. That closed
 * SEC-12, where a plain POST from anyone wrote rows.
 *
 * The tests keep `auto_update_require_approval` on unless they are specifically about
 * approval, so nothing here approves an update by accident.
 */
class AutoUpdateWebhooksApiTest extends ControllerTestCase {

    private const IMAGE = 'registry.example.org/team/api';

    private const SECRET = 'the-webhook-secret';

    // <editor-fold desc="Who may call">

    public function testAHarborPushWithTheSecretCreatesAPendingUpdate(): void {
        $deployment = $this->autoUpdatingDeployment(['version' => 'v1.0.0']);

        $body = $this->harborPush('v2.0.0');

        $this->assertSame('OK', $body['status']);
        $update = $this->updatesFor($deployment->id)[0];
        $this->assertSame(self::IMAGE, $update->image);
        $this->assertSame('v1.0.0', $update->previous_tag);
        $this->assertSame('v2.0.0', $update->next_tag);
        $this->assertFalse((bool) $update->is_approved);
    }

    public function testAHarborPushWithoutTheSecretIsRefused(): void {
        $deployment = $this->autoUpdatingDeployment();

        $status = $this->statusOf($this->harbor(), $this->harborPayload('v2.0.0'), null);

        $this->assertSame(401, $status);
        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    public function testAWrongSecretIsRefused(): void {
        $deployment = $this->autoUpdatingDeployment();

        $status = $this->statusOf($this->harbor(), $this->harborPayload('v2.0.0'), 'Bearer not-the-secret');

        $this->assertSame(401, $status);
        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    /**
     * Decided with INT-1c: a connection kso never set a webhook up for has no secret, and
     * accepts nothing - not even a call with an empty bearer. The migrated connections are
     * like that until someone sets them up.
     */
    public function testAConnectionWithoutASecretAcceptsNothing(): void {
        $deployment = $this->autoUpdatingDeployment();
        $registry = $this->harbor(['webhook_secret' => '']);

        $this->assertSame(401, $this->statusOf($registry, $this->harborPayload('v2.0.0'), 'Bearer '));
        $this->assertSame(401, $this->statusOf($registry, $this->harborPayload('v2.0.0'), null));
        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    /**
     * The secret belongs to one connection. Knowing an Azure connection's secret does not
     * make a call on the Harbor url for it trusted, and an unknown id is refused the same
     * way as a wrong secret.
     */
    public function testTheUrlMustNameAConnectionOfThatProvider(): void {
        $azure = $this->azure();

        $this->assertSame(401, $this->statusOf($azure, $this->harborPayload('v2.0.0'), 'Bearer ' . self::SECRET, 'harbor'));
        $this->assertSame(401, $this->callWebhook('auto-updates/webhooks/harbor/999999', $this->harborPayload('v2.0.0'), 'Bearer ' . self::SECRET));
    }

    /**
     * A registry reports its own pushes. A call with the right secret that names an image
     * in some other registry changes nothing.
     */
    public function testAnImageFromAnotherRegistryIsIgnored(): void {
        $deployment = $this->autoUpdatingDeployment(['image' => 'elsewhere.example.org/team/api']);

        $this->postWith($this->harbor(), [
            'type' => 'PUSH_ARTIFACT',
            'event_data' => ['resources' => [['resource_url' => 'elsewhere.example.org/team/api:v2.0.0', 'tag' => 'v2.0.0']]],
        ]);

        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    // </editor-fold>

    // <editor-fold desc="The payload">

    /**
     * Harbor sends several event types on the same hook. Only a pushed artifact means a
     * new tag exists.
     */
    public function testOtherHarborEventsAreIgnored(): void {
        $deployment = $this->autoUpdatingDeployment();

        $this->postWith($this->harbor(), [
            'type' => 'DELETE_ARTIFACT',
            'event_data' => ['resources' => [['resource_url' => self::IMAGE . ':v2.0.0', 'tag' => 'v2.0.0']]],
        ]);

        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    /**
     * A push payload missing `resource_url` used to be read without a guard and end as an
     * undefined-key error. It is validated now, and a payload that does not say what was
     * pushed is ignored.
     */
    public function testAPayloadThatDoesNotSayWhatWasPushedIsIgnored(): void {
        $deployment = $this->autoUpdatingDeployment();

        foreach ([
            ['type' => 'PUSH_ARTIFACT', 'event_data' => ['resources' => [['tag' => 'v2.0.0']]]],
            ['type' => 'PUSH_ARTIFACT', 'event_data' => ['resources' => [['resource_url' => '', 'tag' => 'v2.0.0']]]],
            ['type' => 'PUSH_ARTIFACT'],
            [],
        ] as $payload) {
            $body = $this->postWith($this->harbor(), $payload);
            $this->assertSame('OK', $body['status'], json_encode($payload));
        }

        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    /**
     * The image name is split off the resource url at the last colon, because a registry
     * host can carry a port and the tag is what follows the final one.
     */
    public function testTheImageIsTakenFromTheResourceUrlUpToTheLastColon(): void {
        $deployment = $this->autoUpdatingDeployment(['image' => 'registry.example.org:5000/team/api']);

        $this->postWith($this->harbor(['harbor_url' => 'registry.example.org:5000']), [
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

        $this->postWith($this->azure(), $this->azurePayload('push'));

        $this->assertSame(self::IMAGE, $this->updatesFor($deployment->id)[0]->image);
    }

    public function testAnAzureEventThatIsNotAPushIsIgnored(): void {
        $deployment = $this->autoUpdatingDeployment();

        $this->postWith($this->azure(), $this->azurePayload('delete'));

        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    public function testAnAzurePushWithoutTheSecretIsRefused(): void {
        $deployment = $this->autoUpdatingDeployment();

        $this->assertSame(401, $this->statusOf($this->azure(), $this->azurePayload('push'), null));
        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    // </editor-fold>

    // <editor-fold desc="Which deployments">

    public function testADeploymentRunningAnotherImageIsLeftAlone(): void {
        $deployment = $this->autoUpdatingDeployment(['image' => 'registry.example.org/team/other']);

        $this->harborPush('v2.0.0');

        $this->assertCount(0, $this->updatesFor($deployment->id));
    }

    /**
     * The tag pattern is the second filter. It is anchored only at the end, so `v[0-9]`
     * also matches `not-v2` - pinned in AutoUpdateCheckForUpdatesTest.
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

    // </editor-fold>

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
     * @param array<string, mixed> $overrides
     */
    private function harbor(array $overrides = []): ContainerRegistry {
        return Fixtures::containerRegistry(array_merge([
            'provider' => \ContainerRegistries::Harbor,
            'harbor_url' => 'registry.example.org',
            'webhook_secret' => self::SECRET,
        ], $overrides));
    }

    private function azure(): ContainerRegistry {
        return Fixtures::containerRegistry([
            'provider' => \ContainerRegistries::AzureContainerRegistry,
            'azure_registry_name' => 'registry.example.org',
            'webhook_secret' => self::SECRET,
        ]);
    }

    private function harborPayload(string $tag): array {
        return [
            'type' => 'PUSH_ARTIFACT',
            'event_data' => ['resources' => [[
                'resource_url' => self::IMAGE . ':' . $tag,
                'tag' => $tag,
            ]]],
        ];
    }

    private function azurePayload(string $action): array {
        return [
            'action' => $action,
            'request' => ['host' => 'registry.example.org'],
            'target' => ['repository' => 'team/api', 'tag' => 'v2.0.0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function harborPush(string $tag): array {
        return $this->postWith($this->harbor(), $this->harborPayload($tag));
    }

    /**
     * A call as the registry would make it: to the connection's url, with its secret.
     *
     * @return array<string, mixed>
     */
    private function postWith(ContainerRegistry $registry, array $payload): array {
        $path = $this->pathFor($registry);
        $response = $this->withBodyFormat('json')->withHeaders(['Authorization' => 'Bearer ' . self::SECRET])->post($path, $payload);
        $this->assertSame(200, $response->response()->getStatusCode(), (string) $response->response()->getBody());

        return json_decode((string) $response->response()->getBody(), true);
    }

    private function statusOf(ContainerRegistry $registry, array $payload, ?string $authorization, ?string $provider = null): int {
        return $this->callWebhook($this->pathFor($registry, $provider), $payload, $authorization);
    }

    /**
     * No signedIn() anywhere in this file: a registry is not a user.
     */
    private function callWebhook(string $path, array $payload, ?string $authorization): int {
        $headers = $authorization === null ? [] : ['Authorization' => $authorization];
        $response = $this->withBodyFormat('json')->withHeaders($headers)->post($path, $payload);

        return $response->response()->getStatusCode();
    }

    private function pathFor(ContainerRegistry $registry, ?string $provider = null): string {
        $provider ??= $registry->provider === \ContainerRegistries::Harbor ? 'harbor' : 'azure-container-registry';
        return "auto-updates/webhooks/{$provider}/{$registry->id}";
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
