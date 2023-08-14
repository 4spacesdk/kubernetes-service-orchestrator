<?php namespace App\Controllers;

use App\Entities\KeelHookQueueItem;
use App\Libraries\ZMQ\ChangeEvent;
use App\Libraries\ZMQ\Events;
use App\Libraries\ZMQ\ZMQProxy;
use DebugTool\Data;

class KeelHooks extends \App\Core\BaseController {

    public function requireAuth(string $method): bool {
        return false;
    }

    public function index() {
        $data = $this->request->getJSON(true);

        $queueItem = new KeelHookQueueItem();
        $queueItem->status = \KeelHookStatusTypes::New;
        $queueItem->data = json_encode($data, JSON_PRETTY_PRINT);
        $queueItem->save();
        Data::set('queue_item', $queueItem->toArray());

        ZMQProxy::getInstance()->send(
            Events::KeelHookQueueItem_Created(),
            (new ChangeEvent(null, $queueItem->toArray()))->toArray()
        );

        $this->response->setJSON(Data::getStore());
        $this->response->send();
    }

}
