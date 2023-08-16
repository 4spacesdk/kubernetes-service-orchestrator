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
            $this->_setResource($item->retry());
        } else {
            $this->_setResource($item);
        }
        $this->success();
    }

}
