<?php namespace App\Entities;

use DebugTool\Data;
use App\Core\Entity;
use App\Entities\Concerns\WriteOnlySecrets;

/**
 * Class WebhookDelivery
 * @package App\Entities
 * @property int $webhook_id
 * @property Webhook $webhook
 * @property string $url
 * @property string $method
 * @property string $content_type
 * @property string $auth_bearer_token write-only, see WriteOnlySecrets
 * @property bool $has_auth_bearer_token
 * @property string $payload
 * @property int $response_code
 * @property string $response_headers
 * @property string $response_body
 * @property int $response_time
 */
class WebhookDelivery extends Entity {

    /**
     * A delivery keeps the webhook's bearer token so a retry sends the same request, which
     * made the delivery log a second copy of every subscriber's credential - one per
     * attempt, listed by `GET /webhooks/{id}/deliveries`.
     *
     * No write takes it, so there is nothing to keep: a delivery is written by kso and read
     * by the log.
     */
    public const array SecretFields = ['auth_bearer_token'];

    use WriteOnlySecrets;

    public $hiddenFields = self::SecretFields;

    /**
     * Send this delivery again as a new one, leaving the delivery it retries where it is.
     *
     * `$new = $this` was not a copy - objects are assigned by reference - so this used to
     * clear the id on the delivery it was asked to retry and save *that* as the new row.
     * The original row stayed in the database, but the entity in memory had become the
     * retry, so a second retry on the same object re-ran the retry rather than the delivery.
     */
    public function retry(): WebhookDelivery {
        $new = $this->asFreshAttempt();
        $new->save();
        $new->run();
        return $new;
    }

    /**
     * The row a retry starts from: the same request, none of the last attempt's answer, and
     * an identity of its own.
     *
     * Its own identity matters beyond the id. A `clone` would carry `created` across too,
     * and `completeSave()` leaves a `created` that is already set alone - so every retry
     * would be listed as having happened when the delivery it retries did, which is the one
     * thing a list of attempts is read for.
     *
     * Separate from `retry()` because `run()` under it is a real HTTP request with no seam
     * in front of it. This is the part that can be looked at.
     */
    public function asFreshAttempt(): WebhookDelivery {
        $new = $this->getCopy();

        // The previous attempt's answer is not this attempt's. `run()` fills these in, but
        // it saves the row first, so without this the new delivery carries the old response
        // until the call comes back - and keeps it forever if it never does.
        $new->response_code = null;
        $new->response_headers = null;
        $new->response_body = null;
        $new->response_time = null;

        return $new;
    }

    public function run(): void {
        $headers = [
            "Content-Type: {$this->content_type}",
        ];

        if ($this->auth_bearer_token) {
            $headers[] = "Authorization: Bearer {$this->auth_bearer_token}";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->url);

        switch($this->method) {
            case 'get':
                break;
            case 'post':
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);
                break;
            case 'patch':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);
                break;
            case 'put':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);
                break;
            case 'delete':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);
                break;
        }

        $time = microtime(true);
        $response = curl_exec($ch);
        $this->response_time = (microtime(true) - $time) * 1000;
        $this->response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->response_headers = substr($response, 0, $headerSize);
        $this->response_body = substr($response, $headerSize);
        $this->save();
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|WebhookDelivery[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
