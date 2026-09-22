<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\ContainerRegistry;
use App\Interfaces\ContainerRegistryImportRequest;
use App\Libraries\Audit\Audit;
use DebugTool\Data;

class ContainerRegistries extends ResourceController {

    /**
     * Ask the registry for something, so a new connection can be checked before an image
     * relies on it. Answers OK with what the registry said, or the error it gave.
     *
     * @route /container-registries/{id}/test
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema ContainerRegistryTestResponse
     * @return void
     */
    public function test(int $id): void {
        $item = new ContainerRegistry();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown container registry');
            return;
        }

        $client = $item->getClient();
        if ($client === null) {
            $this->fail("unsupported provider '{$item->provider}'");
            return;
        }

        try {
            Data::set('resource', ['message' => $client->testConnection()]);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
            return;
        }
        $this->success();
    }

    /**
     * The repositories in the registry, each with the image already made from it, if any.
     *
     * @route /container-registries/{id}/repositories
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema ContainerRegistryRepository
     * @return void
     */
    public function getRepositories(int $id): void {
        $item = new ContainerRegistry();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown container registry');
            return;
        }

        try {
            Data::set('resources', $item->getRepositories());
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
            return;
        }
        $this->success();
    }

    /**
     * Make an image for each named repository. Answers with the images it made.
     *
     * @route /container-registries/{id}/import
     * @method post
     * @custom true
     * @param int $id
     * @requestSchema ContainerRegistryImportRequest
     * @return void
     * @audit entity
     */
    public function import(int $id): void {
        $item = new ContainerRegistry();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown container registry');
            return;
        }

        /** @var ContainerRegistryImportRequest $body */
        $body = $this->request->getJSON();
        $names = array_values(array_filter((array) ($body->repositories ?? []), 'is_string'));

        try {
            $created = $item->importRepositories($names);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
            return;
        }
        Data::set('resources', $created->allToArray());
        $this->success();
    }

    /**
     * Make the registry tell kso about new tags: Pub/Sub for Artifact Registry, a webhook
     * with a secret for Harbor and Azure. Answers with what was done, or why it could not.
     *
     * The webhook url is built from the address this request came in on - the one a
     * person reaches kso at, which is the one a registry has to call.
     *
     * @route /container-registries/{id}/setup-events
     * @method post
     * @custom true
     * @param int $id
     * @responseSchema ContainerRegistryTestResponse
     * @return void
     * @audit container_registry.setup_events
     */
    public function setupEvents(int $id): void {
        $item = new ContainerRegistry();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown container registry');
            return;
        }

        try {
            Data::set('resource', ['message' => $item->setupEvents(config('App')->baseURL)]);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
            return;
        }
        Audit::Record('container_registry.setup_events', $item);
        $this->success();
    }

    /**
     * PUT is switched off; the UI saves with PATCH. A routed PUT would replace every
     * column, and the secrets are not in what the UI has to send back.
     *
     * @ignore true
     * @param $id
     * @return void
     * @codeCoverageIgnore
     */
    public function put($id = 0) {
    }

}
