<?php namespace App\Libraries\VersionControlSystems;

use App\Entities\ContainerImage;
use DebugTool\Data;
use Github\Api\Repo;
use Github\AuthMethod;
use Github\Client;

class GithubVersionControl extends BaseVersionControlSystem {

    private ContainerImage $containerImage;
    private Client $client;

    public function __construct(ContainerImage $containerImage) {
        $this->containerImage = $containerImage;

        $this->client = new Client();
        $this->client->authenticate($this->containerImage->version_control_provider_github_auth_token, null, AuthMethod::ACCESS_TOKEN);
    }

    public function getCommitMessage(string $shortSha): string {
        /** @var Repo $repo */
        $repo = $this->client->api('repo');
        $commit = $repo->commits()->show(
            $this->containerImage->version_control_provider_github_auth_user,
            $this->containerImage->version_control_repository_name,
            $shortSha
        );

        if ($commit && isset($commit['commit']['message'])) {
            return $commit['commit']['message'];
        } else {
            return '';
        }
    }

    public function getCommitUrl(string $shortSha): string {
        /** @var Repo $repo */
        $repo = $this->client->api('repo');
        $commit = $repo->commits()->show(
            $this->containerImage->version_control_provider_github_auth_user,
            $this->containerImage->version_control_repository_name,
            $shortSha
        );

        if ($commit && isset($commit['sha'])) {
            return "https://github.com/{$this->containerImage->version_control_provider_github_auth_user}/{$this->containerImage->version_control_repository_name}/commit/{$commit['sha']}";
        } else {
            return '';
        }

    }
}
