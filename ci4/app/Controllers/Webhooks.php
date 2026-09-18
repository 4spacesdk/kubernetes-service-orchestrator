<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\WebhookDelivery;
use App\Models\WebhookDeliveryModel;
use DebugTool\Data;

class Webhooks extends ResourceController {

    /**
     * @ignore true
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

    /**
     * @route /webhooks/types
     * @method get
     * @custom true
     * @responseSchema WebhookTypesGetResponse
     */
    public function typesGet(): void {
        Data::set('resources', array_map(fn (string $name) => ['name' => $name], \WebHookTypes::All()));
        $this->success();
    }

    /**
     * @route /webhooks/{webhookId}/deliveries
     * @method get
     * @custom true
     * @param int $webhookId
     * @responseSchema WebhookDelivery
     * @return void
     */
    public function deliveriesGet(int $webhookId): void {
        $this->queryParser->parseFilter("webhook_id:$webhookId");
        $items = (new WebhookDeliveryModel())->restGet(0, $this->queryParser);
        $this->_setResources($items);
        $this->success();
    }

    /**
     * @route /webhooks/{webhookId}/deliveries/{webhookDeliveryId}/retry
     * @method put
     * @custom true
     * @param int $webhookId
     * @param int $webhookDeliveryId
     * @responseSchema WebhookDelivery
     * @return void
     */
    public function deliveriesRetry(int $webhookId, int $webhookDeliveryId): void {
        /** @var WebhookDelivery $item */
        $item = (new WebhookDeliveryModel())
            ->where('webhook_id', $webhookId)
            ->where('id', $webhookDeliveryId)
            ->find();
        if ($item->exists()) {
            // `WebhookDelivery::retry()` re-sends the delivery with `curl_exec` straight out
            // of the entity, so reaching this line is a real HTTP request to whatever url
            // the delivery carries. It does not go through `service('integrations')`, and
            // there is no other seam in front of it, so a test cannot take its place.
            // @codeCoverageIgnoreStart
            $this->_setResource($item->retry());
            // @codeCoverageIgnoreEnd
        } else {
            $this->_setResource($item);
        }
        $this->success();
    }

}
