<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\Webhook;
use App\Entities\WebhookDelivery;
use App\Models\WebhookDeliveryModel;
use RestExtension\Exceptions\UnauthorizedException;

/**
 * The webhook endpoints, which are how an operator sees what kso told the outside world.
 *
 * A webhook is a url plus a bearer token, and a delivery is the record of one call to it -
 * url, token, payload and the answer that came back. The delivery log is therefore a list
 * of credentials belonging to someone else's system, and every decision this controller
 * makes is about who gets to read or repeat one of those rows.
 *
 * Only what can be decided without a network is covered. Actually re-sending a delivery
 * runs `curl_exec` out of the entity with no seam in front of it, and that one line is
 * marked in the controller and reported.
 */
class WebhooksApiTest extends ControllerTestCase {

    /**
     * Every event name the product can emit, in the order the settings page lists them.
     *
     * This is a contract in two directions at once. The frontend offers these when someone
     * creates a webhook, and the same string is written to the row and then sent as the
     * `event` field of every payload - so renaming one here does not break a build, it
     * quietly stops every subscriber from recognising the event it was set up for, and
     * nothing on either side reports an error.
     */
    public function testTheTypesOfferedAreExactlyTheEventsThatCanBeSent(): void {
        $body = $this->decode($this->signedIn()->get('webhooks/types'));

        $this->assertSame('OK', $body['status']);
        $this->assertSame([
            ['name' => 'workspace-created'],
            ['name' => 'workspace-updated'],
            ['name' => 'workspace-deleted'],
            ['name' => 'workspace-deployed'],
            ['name' => 'workspace-terminated'],
            ['name' => 'deployment-deployed'],
            ['name' => 'deployment-terminated'],
        ], $body['resources']);
    }

    /**
     * The delivery log is the one webhook endpoint that hands out a credential, so it is
     * the one where an open route would matter most. Nothing in the controller says so -
     * authorization is the `is_public` column - which is exactly why it is worth sending a
     * real request rather than reading the code.
     */
    public function testTheDeliveryLogIsRefusedWithoutAToken(): void {
        $webhook = $this->webhook();

        $this->expectException(UnauthorizedException::class);

        $this->get("webhooks/{$webhook->id}/deliveries");
    }

    /**
     * A delivery belongs to one webhook, and the endpoint is addressed by that webhook.
     * Without the filter the log of every webhook would be returned to anyone who asked for
     * any of them - and each row carries the bearer token the request was signed with.
     */
    public function testOnlyTheDeliveriesOfTheWebhookThatWasAskedForComeBack(): void {
        $mine = $this->webhook(['name' => 'mine']);
        $theirs = $this->webhook(['name' => 'theirs']);
        $this->delivery($mine, ['payload' => '{"event":"mine"}']);
        $this->delivery($theirs, ['payload' => '{"event":"theirs"}']);

        $body = $this->decode($this->signedIn()->get("webhooks/{$mine->id}/deliveries"));

        $this->assertSame(1, $body['count']);
        $this->assertSame('{"event":"mine"}', $body['resources'][0]['payload']);
    }

    /**
     * The controller adds its own filter to whatever the caller already put in the query
     * string, rather than replacing it. That is safe only as long as two filters on the
     * same property are combined with AND - if they were ever ORed together, the whole
     * delivery log, tokens included, would be one query parameter away from any signed-in
     * user.
     *
     * Asking for another webhook's deliveries therefore has to come back empty, not with
     * that webhook's rows.
     */
    public function testACallerCannotWidenTheFilterToAnotherWebhooksDeliveries(): void {
        $mine = $this->webhook(['name' => 'mine']);
        $theirs = $this->webhook(['name' => 'theirs']);
        $this->delivery($mine);
        $this->delivery($theirs, ['auth_bearer_token' => 'not-mine-to-read']);

        $body = $this->decode(
            $this->signedIn()->get("webhooks/{$mine->id}/deliveries?filter=webhook_id:{$theirs->id}")
        );

        $this->assertSame(0, $body['count'], 'the caller chose which webhook to read');
        $this->assertSame([], $body['resources']);
    }

    /**
     * Pinned as today's behaviour: the controller never looks the webhook up, so an id that
     * belongs to nothing answers the same way as one whose log is empty. A caller cannot
     * tell the two apart, which is the friendlier answer of the two - it means the endpoint
     * cannot be used to find out which webhook ids exist.
     */
    public function testAnUnknownWebhookHasAnEmptyLogRatherThanAnError(): void {
        $body = $this->decode($this->signedIn()->get('webhooks/999999/deliveries'));

        $this->assertSame('OK', $body['status']);
        $this->assertSame(0, $body['count']);
    }

    /**
     * Retry is addressed by both ids and matches on both, and this is the reason: the
     * delivery id alone would let anyone with a signed-in token replay any delivery in the
     * system by walking the numbers - each replay being a fresh HTTP call to a third party,
     * carrying that third party's own bearer token, with a payload the caller never had to
     * be allowed to see.
     *
     * Nothing may be sent and nothing may be written when the pair does not match, so the
     * row count is asserted as well as the answer.
     */
    public function testADeliveryCannotBeReplayedThroughAnotherWebhook(): void {
        $mine = $this->webhook(['name' => 'mine']);
        $theirs = $this->webhook(['name' => 'theirs']);
        $delivery = $this->delivery($theirs);

        $before = (new WebhookDeliveryModel())->countAllResults();

        $body = $this->decode($this->signedIn()->put("webhooks/{$mine->id}/deliveries/{$delivery->id}/retry"));

        $this->assertNull($body['resource']['id'], 'a delivery was handed back, so one was found');
        $this->assertSame($before, (new WebhookDeliveryModel())->countAllResults(), 'the delivery was re-sent');
    }

    /**
     * A webhook cannot be edited through the API, and two things make that true.
     *
     * The route is gone: `PUT /webhooks/{id}` used to be in `api_routes`, reaching an empty
     * method that answered 200 with no body at all - a caller had no way to tell that the
     * update had been ignored rather than applied.
     *
     * The empty `put()` stays, and it is the half that matters. It overrides the one the
     * resource controller gives every entity, which replaces *every* column - so if the row
     * ever came back, a partial body would blank the url, method, content type and bearer
     * token of a subscriber's own system.
     */
    public function testAWebhookCannotBeChangedThroughTheApi(): void {
        // Named exactly, not matched loosely: `PUT /webhooks/{id}/deliveries/{id}/retry` is
        // a real endpoint and starts with the same word.
        $this->assertSame(
            0,
            $this->db->table('api_routes')
                ->where('method', 'put')
                ->whereIn('from', ['webhooks', 'webhooks/([0-9]+)'])
                ->countAllResults(),
            'the route is back - see ApiRouteTableTest'
        );

        $this->assertSame(
            \App\Controllers\Webhooks::class,
            (new \ReflectionMethod(\App\Controllers\Webhooks::class, 'put'))->getDeclaringClass()->getName(),
            'the controller no longer overrides put(), so the resource controller\'s own would be routed'
        );
    }

    /**
     * Pinned, not endorsed. A delivery is returned with the bearer token it was sent with,
     * in clear text, to any signed-in caller - the same shape of exposure the GitHub App key
     * once had, one level down. The token belongs to the subscriber's system, not to kso, so
     * kso cannot rotate it and the subscriber has no way of knowing it was read.
     *
     * The field list is asserted whole so that the day it is narrowed, that is a deliberate
     * edit here rather than a silent change of what the settings page shows.
     */
    public function testADeliveryIsHandedBackWithTheSubscribersBearerToken(): void {
        $webhook = $this->webhook();
        $this->delivery($webhook, ['auth_bearer_token' => 'the-subscribers-token']);

        $body = $this->decode($this->signedIn()->get("webhooks/{$webhook->id}/deliveries"));

        $this->assertSame('the-subscribers-token', $body['resources'][0]['auth_bearer_token']);
        $this->assertSame([
            'id',
            'webhook_id',
            'url',
            'method',
            'content_type',
            'auth_bearer_token',
            'payload',
            'response_code',
            'response_headers',
            'response_body',
            'response_time',
            'created',
            'updated',
        ], array_keys($body['resources'][0]));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function webhook(array $overrides = []): Webhook {
        $webhook = new Webhook();
        foreach (array_merge([
            'type' => \WebHookTypes::Workspace_Deployed,
            'name' => 'subscriber',
            'url' => 'https://subscriber.invalid/hook',
            'http_method' => 'post',
            'content_type' => 'application/json',
            'auth_bearer_token' => 'a-token',
        ], $overrides) as $field => $value) {
            $webhook->{$field} = $value;
        }
        $webhook->save();

        return $webhook;
    }

    /**
     * A delivery that has already been sent, as the log holds them. Never run - `run()`
     * would reach the network - so the response fields are written here as a finished call.
     *
     * @param array<string, mixed> $overrides
     */
    private function delivery(Webhook $webhook, array $overrides = []): WebhookDelivery {
        $delivery = new WebhookDelivery();
        foreach (array_merge([
            'webhook_id' => $webhook->id,
            'url' => $webhook->url,
            'method' => $webhook->http_method,
            'content_type' => $webhook->content_type,
            'auth_bearer_token' => $webhook->auth_bearer_token,
            'payload' => '{"event":"workspace-deployed"}',
            'response_code' => 200,
            'response_headers' => '',
            'response_body' => 'ok',
            'response_time' => 12,
        ], $overrides) as $field => $value) {
            $delivery->{$field} = $value;
        }
        $delivery->save();

        return $delivery;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

}
