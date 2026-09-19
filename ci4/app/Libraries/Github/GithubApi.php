<?php namespace App\Libraries\Github;

use App\Entities\GithubIntegration;
use Config\Services;
use Firebase\JWT\JWT;
use Github\AuthMethod;
use Github\Client;
use Github\ResultPager;

class GithubApi extends BaseGithub {

    /**
     * A client signed in as the integration's installation. Throws when GitHub refuses.
     *
     * @codeCoverageIgnore
     */
    public static function installationClient(GithubIntegration $integration): Client {
        $client = self::appClient($integration);
        $token = $client->api('apps')->createInstallationToken((int) $integration->installation_id);
        $client->authenticate($token['token'], null, AuthMethod::ACCESS_TOKEN);

        return $client;
    }

    /**
     * A client signed in as the App itself, which is what may ask about its installations.
     *
     * @codeCoverageIgnore
     */
    private static function appClient(GithubIntegration $integration): Client {
        $client = new Client();
        $jwt = JWT::encode([
            'iat' => time() - 60,
            'exp' => time() + (10 * 60),
            'iss' => (int) $integration->app_id,
        ], $integration->private_key, 'RS256');

        $client->authenticate($jwt, null, AuthMethod::JWT);
        return $client;
    }

    /**
     * @codeCoverageIgnore
     */
    public function installationAccount(GithubIntegration $integration): string {
        $installation = self::appClient($integration)->api('apps')->getInstallation((int) $integration->installation_id);
        return (string) ($installation['account']['login'] ?? '');
    }

    /**
     * @codeCoverageIgnore
     */
    public function convertManifest(string $code): array {
        $response = Services::curlrequest()->post('https://api.github.com/app-manifests/' . rawurlencode($code) . '/conversions', [
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'KSO-Orchestrator',
            ],
            'http_errors' => false,
        ]);

        if ($response->getStatusCode() !== 201 && $response->getStatusCode() !== 200) {
            throw new \Exception('Failed to convert manifest: ' . $response->getBody());
        }

        return json_decode($response->getBody(), true);
    }

    /**
     * @codeCoverageIgnore
     */
    public function listRepositories(GithubIntegration $integration): array {
        $client = self::installationClient($integration);

        /** @var \Github\Api\Apps $appsApi */
        $appsApi = $client->api('apps');
        $paginator = new ResultPager($client);
        $response = $paginator->fetch($appsApi, 'listRepositories');

        $repositories = $response['repositories'] ?? [];
        while ($paginator->hasNext()) {
            $response = $paginator->fetchNext();
            $repositories = array_merge($repositories, $response['repositories'] ?? []);
        }

        return array_map(fn (array $repo) => [
            'id' => (int) $repo['id'],
            'full_name' => (string) $repo['full_name'],
            'name' => (string) $repo['name'],
            'archived' => (bool) ($repo['archived'] ?? false),
        ], $repositories);
    }

}
