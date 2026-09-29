<?php namespace App\Libraries\MigrationJobs;

use App\Entities\Deployment;

/**
 * What `MigrationJobWatcher` asks the cluster about a deployment's migration. Answers are the
 * resources as the api server has them, so the tests can hand it the same shapes.
 */
interface MigrationJobCluster {

    /**
     * The deployment's migration Job, or null when there is none.
     */
    public function job(Deployment $deployment): ?array;

    /**
     * @return list<array> The pods of its migration Job
     */
    public function pods(Deployment $deployment): array;

    /**
     * What one container of one pod wrote.
     */
    public function log(Deployment $deployment, string $pod, string $container): string;

    public function deleteJob(Deployment $deployment): void;

}
