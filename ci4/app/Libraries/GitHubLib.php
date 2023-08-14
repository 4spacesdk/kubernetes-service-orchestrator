<?php namespace App\Libraries;

use Github\Api\Repo;
use Github\AuthMethod;
use Github\Client;

class GitHubLib {

    public static function getCommitMessage(string $repository, string $shortSha): ?array {
        $client = new Client();
        $client->authenticate(getenv('GITHUB_AUTH_TOKEN'), null, AuthMethod::ACCESS_TOKEN);

        /** @var Repo $repo */
        $repo = $client->api('repo');
        $commit = $repo->commits()->show(getenv('GITHUB_AUTH_USER'), $repository, $shortSha);

        if ($commit && isset($commit['sha'])) {
            return [$commit['sha'], $commit['commit']['message']];
        } else {
            return null;
        }
    }

    public static function getCommitUrl(string $repo, string $commitSha): string {
        return "https://github.com/".getenv('GITHUB_AUTH_USER')."/{$repo}/commit/{$commitSha}";
    }

}
