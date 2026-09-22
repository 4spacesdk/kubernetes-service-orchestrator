<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\GithubIntegration;
use DebugTool\Data;

class GithubIntegrations extends ResourceController {

    /**
     * Start creating the integration's GitHub App. Answers with the manifest and the url to
     * post it to; the browser does the posting, because GitHub asks the operator to confirm.
     * Creating it again replaces the App kso holds once GitHub calls back.
     *
     * @route /github-integrations/{id}/create-app
     * @method post
     * @custom true
     * @param int $id
     * @responseSchema GithubIntegrationSetupResponse
     * @return void
     * @audit none builds the url for GitHub; what GitHub sends back is saved through the entity
     */
    public function createApp(int $id): void {
        $item = new GithubIntegration();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown github integration');
            return;
        }

        try {
            Data::set('resource', [
                'url' => $item->createAppUrl(),
                'manifest' => json_encode($item->manifest(env('DEV_REMOTE_BASE_URL') ?: base_url())),
            ]);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
            return;
        }
        $this->success();
    }

    /**
     * Where the operator installs the App - or changes which repositories it sees.
     *
     * @route /github-integrations/{id}/install
     * @method post
     * @custom true
     * @param int $id
     * @responseSchema GithubIntegrationSetupResponse
     * @return void
     * @audit none builds the url for GitHub; what GitHub sends back is saved through the entity
     */
    public function install(int $id): void {
        $item = new GithubIntegration();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown github integration');
            return;
        }

        try {
            Data::set('resource', ['url' => $item->installUrl()]);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
            return;
        }
        $this->success();
    }

    /**
     * The repositories the installation sees, for tying an image to one.
     *
     * @route /github-integrations/{id}/repositories
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema GithubRepository
     * @return void
     */
    public function getRepositories(int $id): void {
        $item = new GithubIntegration();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown github integration');
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
     * PUT is switched off; the UI saves with PATCH. A routed PUT would replace every
     * column, and the App's keys are not in what the UI has to send back.
     *
     * @ignore true
     * @param $id
     * @return void
     * @codeCoverageIgnore
     */
    public function put($id = 0) {
    }

}
