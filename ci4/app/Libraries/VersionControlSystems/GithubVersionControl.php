<?php namespace App\Libraries\VersionControlSystems;

use App\Entities\ContainerImage;
use App\Libraries\Github\GithubApi;
use DebugTool\Data;
use Github\Client;

class GithubVersionControl extends BaseVersionControlSystem {

    private ContainerImage $containerImage;
    private Client $client;

    public function __construct(ContainerImage $containerImage) {
        $this->containerImage = $containerImage;

        $this->client = new Client();
        $this->authenticateAsInstallation();
    }

    /**
     * Not measured: this is the network call itself. What kso decides before and after
     * it is tested through the fake behind `BaseVersionControlSystem` - see the strategy note in the
     * test setup. Marking it keeps the coverage number about code we chose to test.
     *
     * @codeCoverageIgnore
     */
    private function authenticateAsInstallation(): void {
        if (!$this->containerImage->github_integration_id) {
            return;
        }
        $integration = $this->containerImage->github_integration;
        $integration->find();
        if (!$integration->exists() || !$integration->isInstalled()) {
            return;
        }

        try {
            $this->client = GithubApi::installationClient($integration);
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

    /**
     * Not measured: this is the network call itself. What kso decides before and after
     * it is tested through the fake behind `BaseVersionControlSystem` - see the strategy note in the
     * test setup. Marking it keeps the coverage number about code we chose to test.
     *
     * @codeCoverageIgnore
     */
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

    /**
     * Not measured: this is the network call itself. What kso decides before and after
     * it is tested through the fake behind `BaseVersionControlSystem` - see the strategy note in the
     * test setup. Marking it keeps the coverage number about code we chose to test.
     *
     * @codeCoverageIgnore
     */
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
