<?php namespace App\Libraries\WebHooks;

use App\Entities\Webhook;
use App\Models\WebhookModel;
use DebugTool\Data;

class WebhookHelper {

    public static function Deliver(string $type, string $payload): void {
        /** @var Webhook $webhooks */
        $webhooks = (new WebhookModel())
            ->where('type', $type)
            ->find();
        foreach ($webhooks as $webhook) {
            $webhook->deliver($payload);
        }
        Data::debug('delivered', $type, 'to', $webhooks->count(), 'webhooks');
    }

}
