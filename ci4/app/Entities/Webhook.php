<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class Webhook
 * @package App\Entities
 * @property string $type
 * @property string $name
 * @property string $url
 * @property string $http_method
 * @property string $content_type
 * @property string $auth_bearer_token
 *
 * Many
 * @property WebhookDelivery $webhook_deliveries
 */
class Webhook extends Entity {

    public function deliver(string $payload): void {
        $delivery = new WebhookDelivery();
        $delivery->webhook_id = $this->id;
        $delivery->url = $this->url;
        $delivery->method = $this->http_method;
        $delivery->content_type = $this->content_type;
        $delivery->auth_bearer_token = $this->auth_bearer_token;
        $delivery->payload = json_encode([
            'event' => $this->type,
            'payload' => $payload,
        ]);
        $delivery->save();

        $delivery->run();
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|Webhook[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
