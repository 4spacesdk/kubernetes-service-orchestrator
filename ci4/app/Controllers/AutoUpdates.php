<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\AutoUpdate;
use App\Libraries\ZMQ\ChangeEvent;
use App\Libraries\ZMQ\Events;
use App\Libraries\ZMQ\ZMQProxy;
use DebugTool\Data;

class AutoUpdates extends ResourceController {

    /**
     * @route /auto-updates/{id}/approve
     * @method put
     * @custom true
     * @param int $id
     * @return void
     */
    public function approve(int $id): void {
        $item = new AutoUpdate();
        $item->find($id);
        if ($item->exists()) {
            $item->approve();
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /auto-updates/webhooks/azure-container-registry
     * @method post
     * @custom true
     * @return void
     */
    public function webhooksAzureContainerRegistry(): void {
        $payload = $this->request->getJSON(true);
        Data::debug($payload);

        switch ($payload['action']) {
            case 'push':
                $image = $payload['request']['host'] . '/' . $payload['target']['repository'];
                $tag = $payload['target']['tag'];

                AutoUpdate::CheckForUpdates($image, $tag);

                ZMQProxy::getInstance()->send(
                    Events::AutoUpdate_Created(),
                    (new ChangeEvent(null, []))->toArray()
                );
                break;
        }

        $this->success();
    }

    /**
     * @route /auto-updates/webhooks/harbor
     * @method post
     * @custom true
     * @return void
     */
    public function webhooksHarbor(): void {
        $payload = $this->request->getJSON(true);
        Data::debug($payload);

        $eventType = strtoupper($payload['type'] ?? '');
        if ($eventType === 'PUSH_ARTIFACT') {
            $resource = $payload['event_data']['resources'][0] ?? [];
            $resourceUrl = $resource['resource_url']; // Eg. 651p8071.c1.de1.container-registry.ovh.net/taksinto/backend/api:hotfix
            $tag = $resource['tag'];

            if (!empty($resourceUrl)) {
                $image = substr($resourceUrl, 0, strrpos($resourceUrl, ':'));

                AutoUpdate::CheckForUpdates($image, $tag);

                ZMQProxy::getInstance()->send(
                    Events::AutoUpdate_Created(),
                    (new ChangeEvent(null, []))->toArray()
                );
            }
        }

        $this->success();
    }

    /**
     * Switched off. `@ignore true` is read by `ci4restextension`'s `ApiItem`, so the route
     * generator and swagger leave this verb out - and no migration ever wrote it into
     * `api_routes` either, so no request can reach it. Both halves are needed: the
     * annotation does not remove a row that is already in the table. See SEC-11, and
     * `Workspaces`/`Deployments`, where exactly that went wrong.
     *
     * @param $id
     * @return void
     * @codeCoverageIgnore
     * @ignore true
     */
    public function put($id = 0) {
    }

    /**
     * Switched off. `@ignore true` is read by `ci4restextension`'s `ApiItem`, so the route
     * generator and swagger leave this verb out - and no migration ever wrote it into
     * `api_routes` either, so no request can reach it. Both halves are needed: the
     * annotation does not remove a row that is already in the table. See SEC-11, and
     * `Workspaces`/`Deployments`, where exactly that went wrong.
     *
     * @param $id
     * @return void
     * @codeCoverageIgnore
     * @ignore true
     */
    public function patch($id = 0) {
    }

}
