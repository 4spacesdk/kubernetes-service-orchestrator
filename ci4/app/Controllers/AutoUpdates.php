<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\AutoUpdate;
use App\Entities\ContainerRegistry;
use App\Libraries\ContainerRegistries\ImageReference;
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
        if (!$item->exists()) {
            $this->fail('unknown auto update');
            return;
        }

        $item->approve();
        $this->_setResource($item);
        $this->success();
    }

    /**
     * A push, reported by an Azure Container Registry webhook that kso set up.
     *
     * @route /auto-updates/webhooks/azure-container-registry/{containerRegistryId}
     * @method post
     * @custom true
     * @param int $containerRegistryId
     * @return void
     */
    public function webhooksAzureContainerRegistry(int $containerRegistryId): void {
        $registry = $this->registryCalling($containerRegistryId, \ContainerRegistries::AzureContainerRegistry);
        if ($registry === null) {
            $this->fail('unauthorized', 401);
            return;
        }

        $payload = $this->request->getJSON(true);
        Data::debug($payload);

        $host = $payload['request']['host'] ?? null;
        $repository = $payload['target']['repository'] ?? null;
        $tag = $payload['target']['tag'] ?? null;
        if (($payload['action'] ?? null) === 'push' && is_string($host) && is_string($repository) && is_string($tag)) {
            $this->newTag($registry, "{$host}/{$repository}", $tag);
        }

        $this->success();
    }

    /**
     * A push, reported by a Harbor webhook policy that kso set up.
     *
     * @route /auto-updates/webhooks/harbor/{containerRegistryId}
     * @method post
     * @custom true
     * @param int $containerRegistryId
     * @return void
     */
    public function webhooksHarbor(int $containerRegistryId): void {
        $registry = $this->registryCalling($containerRegistryId, \ContainerRegistries::Harbor);
        if ($registry === null) {
            $this->fail('unauthorized', 401);
            return;
        }

        $payload = $this->request->getJSON(true);
        Data::debug($payload);

        $resource = $payload['event_data']['resources'][0] ?? null;
        $resourceUrl = $resource['resource_url'] ?? null; // Eg. 651p8071.c1.de1.container-registry.ovh.net/taksinto/backend/api:hotfix
        $tag = $resource['tag'] ?? null;
        [$image, $urlTag] = ImageReference::split($resourceUrl);
        if (strtoupper((string) ($payload['type'] ?? '')) === 'PUSH_ARTIFACT'
            && is_string($resourceUrl) && is_string($tag) && $urlTag !== null) {
            $this->newTag($registry, $image, $tag);
        }

        $this->success();
    }

    /**
     * The connection a webhook call is for, if the call proves it knows the connection's
     * secret. Everything that fails - an unknown id, another provider, a connection kso
     * never set a webhook up for, a missing or wrong header - is the same answer, so a
     * caller learns nothing from which one it was.
     */
    private function registryCalling(int $id, string $provider): ?ContainerRegistry {
        $registry = new ContainerRegistry();
        $registry->find($id);
        if (!$registry->exists() || $registry->provider !== $provider
            || !$registry->acceptsWebhook($this->request->getHeaderLine('Authorization') ?: null)) {
            return null;
        }
        return $registry;
    }

    /**
     * Only for the connection's own images: a registry's webhook reports that registry's
     * pushes, and an image url from anywhere else is not its to report.
     */
    private function newTag(ContainerRegistry $registry, string $image, string $tag): void {
        if (!$registry->getClient()?->hasImage($image)) {
            Data::debug("{$image} is not in {$registry->name}, ignored");
            return;
        }

        AutoUpdate::CheckForUpdates($image, $tag);

        ZMQProxy::getInstance()->send(
            Events::AutoUpdate_Created(),
            (new ChangeEvent(null, []))->toArray()
        );
    }

    /**
     * Switched off. `@ignore true` is read by `ci4restextension`'s `ApiItem`, so the route
     * generator and swagger leave this verb out - and no migration ever wrote it into
     * `api_routes` either, so no request can reach it. Both halves are needed: the
     * annotation does not remove a row that is already in the table. See
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
     * annotation does not remove a row that is already in the table. See
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
