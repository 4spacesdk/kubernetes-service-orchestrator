<?php namespace App\Controllers;

use App\Entities\GithubIntegration;

/**
 * Where GitHub sends the operator's browser while a GitHub integration is set up. Both are
 * public, because a browser arriving from GitHub carries no token, and both act only on the
 * integration whose state nonce they are handed - see `GithubIntegration`.
 *
 * The urls stay here rather than under the integration: an App already created has them
 * stored at GitHub.
 */
class GithubApp extends \App\Core\BaseController {

    public function requireAuth(string $method): bool {
        return false;
    }

    /**
     * GitHub made the App from the manifest. Swap the code for its keys, then send the
     * operator straight on to install it.
     *
     * The state is used up before GitHub is asked, so a code and state can only be tried once.
     *
     * @route /githubapp/callback
     * @method get
     * @custom true
     * @return void
     */
    public function callback(): void {
        $code = (string) $this->request->getGet('code');
        if ($code === '') {
            $this->fail('No code provided');
            return;
        }

        $integration = GithubIntegration::findBySetupState($this->request->getGet('state'));
        if ($integration === null) {
            $this->fail('This GitHub App setup was not started from kso, or it has already been used. Start it again.');
            return;
        }
        $integration->setup_state = '';
        $integration->save();

        try {
            $integration->storeApp($integration->getClient()->convertManifest($code));
        } catch (\Throwable $e) {
            $this->fail('Error during manifest conversion: ' . $e->getMessage());
            return;
        }

        $this->response->redirect($integration->installUrl());
    }

    /**
     * GitHub installed the App, or changed which repositories it sees. Only the first carries
     * a state, and only a state kso issued lets the installation be stored; the operator is
     * sent back to kso either way.
     *
     * @route /githubapp/post-install
     * @method get
     * @custom true
     * @return void
     */
    public function post_install(): void {
        $installationId = (int) $this->request->getGet('installation_id');
        $integration = GithubIntegration::findBySetupState($this->request->getGet('state'));

        $query = '';
        if ($installationId > 0 && $integration !== null) {
            $integration->storeInstallation($installationId);
            $query = '?github_install_success=1';
        }

        $this->response->redirect(getFrontendUrl('/app/integrations/github-integrations' . $query));
    }

}
