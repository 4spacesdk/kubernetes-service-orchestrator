<?php namespace App\Libraries\VersionControlSystems;

use App\Entities\ContainerImage;
use App\Entities\System;
use DebugTool\Data;
use Firebase\JWT\JWT;
use Github\AuthMethod;
use Github\Client;

class GithubVersionControl extends BaseVersionControlSystem {

    private ContainerImage $containerImage;
    private Client $client;

    public function __construct(ContainerImage $containerImage) {
        $this->containerImage = $containerImage;

        $this->client = new Client();
        $this->authenticateAsInstallation();
    }

    private function authenticateAsInstallation(): void {
        $system = System::Get();
        $appId = $system->github_app_id;
        $privateKey = $system->github_app_private_key;
        $installationId = $system->github_app_installation_id;

        if (!$appId || !$privateKey || !$installationId) {
            return;
        }

        $payload = [
            'iat' => time() - 60,
            'exp' => time() + (10 * 60),
            'iss' => $appId,
        ];

        try {
            $jwt = JWT::encode($payload, $privateKey, 'RS256');

            $this->client->authenticate($jwt, null, AuthMethod::JWT);
            $token = $this->client->api('apps')->createInstallationToken($installationId);

            $this->client->authenticate($token['token'], null, AuthMethod::ACCESS_TOKEN);
        } catch (\Exception $e) {
            Data::debug("GitHub App Auth Error: " . $e->getMessage());
        }
    }

    private function getOwnerAndRepo(): array {
        $parts = explode('/', $this->containerImage->version_control_repository_name);
        if (count($parts) >= 2) {
            return [$parts[0], $parts[1]];
        }
        return ['', $this->containerImage->version_control_repository_name];
    }

    public function getCommitMessage(string $shortSha): string {
        try {
            [$owner, $repoName] = $this->getOwnerAndRepo();
            /** @var \Github\Api\Repo $repo */
            $repo = $this->client->api('repo');
            $commit = $repo->commits()->show(
                $owner,
                $repoName,
                $shortSha
            );

            if ($commit && isset($commit['commit']['message'])) {
                return $commit['commit']['message'];
            }
        } catch (\Exception $e) {
            Data::debug("GitHub API Error: " . $e->getMessage());
        }
        return '';
    }

    public function getCommitUrl(string $shortSha): string {
        try {
            [$owner, $repoName] = $this->getOwnerAndRepo();
            /** @var \Github\Api\Repo $repo */
            $repo = $this->client->api('repo');
            $commit = $repo->commits()->show(
                $owner,
                $repoName,
                $shortSha
            );

            if ($commit && isset($commit['sha'])) {
                return "https://github.com/{$owner}/{$repoName}/commit/{$commit['sha']}";
            }
        } catch (\Exception $e) {
            Data::debug("GitHub API Error: " . $e->getMessage());
        }
        return '';
    }
}
