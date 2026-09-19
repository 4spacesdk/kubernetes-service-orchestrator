<?php namespace App\Libraries\Github;

use App\Entities\GithubIntegration;

/**
 * What kso asks GitHub for when connecting an App, in kso's own terms.
 *
 * Both used to be a client built in place inside `GithubApp`, so the callback that stores an
 * App's credentials and the repository listing could not be tested past their first line.
 * Commit lookups stay in `GithubVersionControl`, which has its own seam.
 */
abstract class BaseGithub {

    /**
     * Swap the code GitHub hands back after an App is created from a manifest for the App
     * itself.
     *
     * @return array{id: int, client_id: string, client_secret: string, pem: string, webhook_secret: string, slug: string}
     */
    abstract public function convertManifest(string $code): array;

    /**
     * The account the integration's installation lives on: an organisation's login, or a
     * user's.
     */
    abstract public function installationAccount(GithubIntegration $integration): string;

    /**
     * Every repository the integration's installation can see, archived ones included.
     *
     * @return array<array{id: int, full_name: string, name: string, archived: bool}>
     */
    abstract public function listRepositories(GithubIntegration $integration): array;

}
