<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\Deployment;
use App\Entities\MigrationJob;
use App\Fixtures;
use App\Libraries\Health\Workload;

/**
 * The migration job a deployment's health is judged by is the one `last_migration_job_id` points at.
 *
 * Looked up through the ORM relation it was the deployment's oldest job instead: the relation
 * goes through `migration_jobs.deployment_id`, which every job of the deployment has. On a
 * deployment that follows a moving tag such as `develop` - so every job counts as for the
 * current version - one failed migration kept it Degraded through every one that ran after it.
 */
class DeploymentLastMigrationJobTest extends DatabaseTestCase {

    public function testTheLastJobIsTheOneThatRanLast(): void {
        $deployment = Fixtures::deployment(['version' => 'develop']);
        $this->job($deployment, \MigrationJobStatusTypes::Failed_PostCommands);
        $last = $this->job($deployment, \MigrationJobStatusTypes::Completed);

        $deployment->last_migration_job_id = $last->id;
        $deployment->save();

        $reloaded = new Deployment();
        $reloaded->find($deployment->id);

        $this->assertSame((int) $last->id, (int) $reloaded->findLastMigrationJob()->id);
        $this->assertSame(\MigrationJobStatusTypes::Completed, Workload::Of($reloaded, 'deployment')->lastMigration['status']);
    }

    public function testNoLastJobIsNone(): void {
        $deployment = Fixtures::deployment();
        $this->job($deployment, \MigrationJobStatusTypes::Failed_PostCommands);

        $this->assertNull($deployment->findLastMigrationJob());
    }

    private function job(Deployment $deployment, string $status): MigrationJob {
        $job = new MigrationJob();
        $job->deployment_id = $deployment->id;
        $job->status = $status;
        $job->image = 'registry/api:develop';
        $job->save();
        return $job;
    }

}
