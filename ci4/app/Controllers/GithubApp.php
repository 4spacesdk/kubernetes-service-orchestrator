<?php namespace App\Controllers;

use App\Entities\System;
use Config\Services;
use DebugTool\Data;
use Firebase\JWT\JWT;
use Github\AuthMethod;
use Github\Client;

class GithubApp extends \App\Core\BaseController {

    public function requireAuth(string $method): bool {
        // Callback and post-install must be accessible without authentication from GitHub
        if ($method === 'callback' || $method === 'post_install') {
            return false;
        }
        return true;
    }

    /**
     * @route /githubapp/manifest
     * @method get
     * @custom true
     * @return void
     */
    public function manifest(): void {
        $baseUrl = env('DEV_REMOTE_BASE_URL') ?: base_url();
        
        $manifest = [
            'name' => 'KSO - ' . (getenv('PROJECT_NAME') ?: 'Orchestrator'),
            'url' => $baseUrl,
            'redirect_url' => $baseUrl . '/githubapp/callback',
            'setup_url' => $baseUrl . '/githubapp/post-install',
            'public' => false,
            'default_permissions' => [
                // Required to read commit messages and SHA for version control
                'contents' => 'read',
                // Mandatory for all GitHub Apps
                'metadata' => 'read',
            ],
        ];

        $this->response->setJSON($manifest);
        $this->response->send();
    }

    /**
     * @route /githubapp/callback
     * @method get
     * @custom true
     * @return void
     */
    public function callback(): void {
        $code = $this->request->getGet('code');
        if (!$code) {
            $this->fail('No code provided');
        }

        $client = Services::curlrequest();
        try {
            $response = $client->post("https://api.github.com/app-manifests/{$code}/conversions", [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'KSO-Orchestrator'
                ]
            ]);

            if ($response->getStatusCode() !== 201 && $response->getStatusCode() !== 200) {
                $this->fail('Failed to convert manifest: ' . $response->getBody());
            }

            $data = json_decode($response->getBody(), true);
            
            $system = System::Get();
            $system->github_app_id = $data['id'];
            $system->github_app_client_id = $data['client_id'];
            $system->github_app_client_secret = $data['client_secret'];
            $system->github_app_private_key = $data['pem'];
            $system->github_app_webhook_secret = $data['webhook_secret'];
            $system->github_app_slug = $data['slug'];
            $system->save();

            // Redirect back to the frontend settings page
            $redirectUrl = getFrontendUrl('/app/setup/system?github_success=1');
            $this->response->redirect($redirectUrl);
        } catch (\Exception $e) {
            $this->fail('Error during manifest conversion: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @route /githubapp/post-install
     * @method get
     * @custom true
     * @return void
     */
    public function post_install(): void {
        $installationId = $this->request->getGet('installation_id');
        if ($installationId) {
            $system = System::Get();
            $system->github_app_installation_id = (int)$installationId;
            $system->save();
        }

        // Redirect back to the frontend settings page
        $redirectUrl = getFrontendUrl('/app/setup/system?github_install_success=1');
        $this->response->redirect($redirectUrl);
    }

    /**
     * @route /githubapp/repositories
     * @method get
     * @custom true
     * @return void
     */
    public function repositories(): void {
        $system = System::Get();
        $installationId = $this->request->getGet('installation_id') ?: $system->github_app_installation_id;
        
        if (!$installationId) {
            $this->fail('No installation_id provided');
            return;
        }

        $appId = $system->github_app_id;
        $privateKey = $system->github_app_private_key;

        if (!$appId || !$privateKey) {
            $this->fail('GitHub App not configured');
            return;
        }

        $client = new Client();
        
        $payload = [
            'iat' => time() - 60,
            'exp' => time() + (10 * 60),
            'iss' => $appId,
        ];

        try {
            $jwt = JWT::encode($payload, $privateKey, 'RS256');
            $client->authenticate($jwt, null, AuthMethod::JWT);
            $token = $client->api('apps')->createInstallationToken($installationId);
            $client->authenticate($token['token'], null, AuthMethod::ACCESS_TOKEN);

            /** @var \Github\Api\Apps $appsApi */
            $appsApi = $client->api('apps');
            $repositories = $appsApi->listRepositories();
            
            $result = [];
            if (isset($repositories['repositories'])) {
                foreach ($repositories['repositories'] as $repo) {
                    // Skip archived repositories
                    if (isset($repo['archived']) && $repo['archived']) {
                        continue;
                    }
                    
                    $result[] = [
                        'id' => $repo['id'],
                        'full_name' => $repo['full_name'],
                        'name' => $repo['name'],
                    ];
                }
            }

            // Sort by name
            usort($result, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            $this->response->setJSON($result);
            $this->response->send();
        } catch (\Exception $e) {
            $this->fail('GitHub API Error: ' . $e->getMessage(), 500);
        }
    }
}
