<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\Webhook;
use App\Entities\WebhookDelivery;
use App\Models\WebhookDeliveryModel;

/**
 * Re-sending a delivery to a subscriber.
 *
 * The delivery log is an operator's record of what kso told the outside world and what came
 * back, and a retry adds an attempt to it rather than replacing one. `retry()` itself ends
 * in `curl_exec` with no seam in front of it - see the note in `Webhooks::deliveriesRetry()`
 * - so what is tested here is `asFreshAttempt()`, the row a retry is built from, which is
 * where every decision about it is made.
 */
class WebhookDeliveryRetryTest extends DatabaseTestCase {

    /**
     * `$new = $this` was not a copy. Objects are assigned by reference, so this cleared the
     * id on the delivery it was asked to retry and saved *that* as the new row: the entity
     * in the caller's hand had become the retry, and a second retry on it re-ran the retry
     * rather than the delivery.
     */
    public function testTheDeliveryBeingRetriedIsLeftAsItIs(): void {
        $delivery = $this->delivery();
        $id = $delivery->id;

        $fresh = $this->attemptFrom($delivery);

        $this->assertNotSame($delivery, $fresh, 'a copy, not the same object under a second name');
        $this->assertSame($id, $delivery->id, 'the delivery being retried keeps its identity');
        $this->assertSame(200, $delivery->response_code, 'and what came back the first time');
    }

    /**
     * Retrying twice from the same entity is two more attempts on the same delivery. It used
     * to be an attempt on the delivery and then an attempt on that attempt.
     */
    public function testRetryingTwiceRetriesTheSameDeliveryBothTimes(): void {
        $delivery = $this->delivery(['payload' => '{"event":"workspace-deployed"}']);

        $first = $this->attemptFrom($delivery);
        $delivery->response_code = 500;
        $second = $this->attemptFrom($delivery);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('{"event":"workspace-deployed"}', $first->payload);
        $this->assertSame('{"event":"workspace-deployed"}', $second->payload);
        $this->assertSame(3, (new WebhookDeliveryModel())->countAllResults(), 'the original and two attempts');
    }

    /**
     * The point of a retry: the same call, to the same place, with the same credential.
     */
    public function testTheRetryIsTheSameRequest(): void {
        $delivery = $this->delivery([
            'url' => 'https://subscriber.invalid/hook',
            'method' => 'post',
            'content_type' => 'application/json',
            'auth_bearer_token' => 'the-subscribers-token',
            'payload' => '{"event":"workspace-deployed"}',
        ]);

        $fresh = $this->attemptFrom($delivery);

        $this->assertSame($delivery->webhook_id, $fresh->webhook_id);
        $this->assertSame('https://subscriber.invalid/hook', $fresh->url);
        $this->assertSame('post', $fresh->method);
        $this->assertSame('application/json', $fresh->content_type);
        $this->assertSame('the-subscribers-token', $fresh->auth_bearer_token);
        $this->assertSame('{"event":"workspace-deployed"}', $fresh->payload);
    }

    /**
     * An attempt that has not been made yet has no answer. `run()` fills these in, but it
     * saves the row before calling out, so a copied response would be what the log shows
     * until the call came back - and forever if it never did.
     */
    public function testTheRetryCarriesNoneOfTheLastAttemptsAnswer(): void {
        $delivery = $this->delivery([
            'response_code' => 500,
            'response_headers' => 'HTTP/1.1 500',
            'response_body' => 'upstream is down',
            'response_time' => 4210,
        ]);

        $fresh = $this->attemptFrom($delivery);

        $this->assertNull($fresh->response_code);
        $this->assertNull($fresh->response_headers);
        $this->assertNull($fresh->response_body);
        $this->assertNull($fresh->response_time);
    }

    /**
     * When the attempt was made is the one thing a list of attempts is read for. A `clone`
     * would carry `created` across, and `completeSave()` leaves a `created` that is already
     * set alone - so every retry would be listed as having happened when the delivery it
     * retries did.
     */
    public function testTheRetryIsStampedWithItsOwnTimeRatherThanTheOriginals(): void {
        $delivery = $this->delivery();
        $delivery->created = '2024-05-01 10:00:00';
        $delivery->save();

        $fresh = $this->attemptFrom($delivery);

        $this->assertNotSame('2024-05-01 10:00:00', $fresh->created);
        $this->assertNotEmpty($fresh->created, 'the row still says when it was made');
    }

    // <editor-fold desc="Fixtures">

    /**
     * `retry()` without the `run()` under it, which would reach the network. This is the
     * whole of what `retry()` decides; the rest is curl.
     */
    private function attemptFrom(WebhookDelivery $delivery): WebhookDelivery {
        $fresh = $delivery->asFreshAttempt();
        $fresh->save();

        return $fresh;
    }

    /**
     * A delivery that has already been sent, as the log holds them.
     *
     * @param array<string, mixed> $overrides
     */
    private function delivery(array $overrides = []): WebhookDelivery {
        $webhook = new Webhook();
        $webhook->type = \WebHookTypes::Workspace_Deployed;
        $webhook->name = 'subscriber';
        $webhook->url = 'https://subscriber.invalid/hook';
        $webhook->http_method = 'post';
        $webhook->content_type = 'application/json';
        $webhook->auth_bearer_token = 'a-token';
        $webhook->save();

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

    // </editor-fold>

}
