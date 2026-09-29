<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;

/**
 * The migration job endpoints: rerunning one, and who may call them. A job's progress is read
 * from the cluster (`MigrationJobWatcherTest`), not reported to kso over http.
 */
class MigrationJobsApiTest extends ControllerTestCase {

    /**
     * Re-running asks the migration step to deploy again, which needs a cluster - so what
     * this holds is the guard in front of it: an id that is not there does nothing and
     * still answers.
     */
    public function testRerunningAnUnknownJobDoesNothing(): void {
        $body = $this->decode($this->signedIn()->put('migration-jobs/999999/rerun'));

        $this->assertSame('OK', $body['status']);
    }

    /**
     * A job whose deployment specification has no migration step is dropped before anything
     * reaches the cluster - `tryExecuteDeployCommand()` asks the specification which steps
     * it has and returns straight away when this is not one of them.
     *
     * That is the branch worth having offline: it is the one that stops a rerun on a
     * deployment that never had migrations, and it runs before the connection is opened.
     *
     * The step's own debug line is what says it was *asked* and declined, rather than never
     * reached - the endpoint answers `OK` either way, so without it this would pass just as
     * well if the rerun were never attempted at all.
     */
    public function testRerunningAJobOnASpecificationWithoutMigrationsChangesNothing(): void {
        $job = $this->migrationJob();

        $body = $this->decode($this->signedIn()->put("migration-jobs/{$job['id']}/rerun"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame((int) $job['id'], (int) $body['resource']['id']);
        $this->assertSame(\MigrationJobStatusTypes::Deploying, $this->row($job['id'])['status']);

        // Read from the log itself: the response carries it only in development.
        $debug = implode("\n", array_map(fn ($line) => is_string($line) ? $line : json_encode($line), \DebugTool\Data::getDebugger()));
        $this->assertStringContainsString('tryExecuteDeployCommand', $debug, 'the step was never asked');
        $this->assertStringContainsString('ignored cause of invalid deployment step for this spec', $debug);
    }

    /**
     * Every route needs a sign-in, in the table that decides it and in the controller that
     * says so. The two that did not were the callbacks a job's pod reported itself on, before
     * kso read the job from the cluster.
     */
    public function testEveryRouteNeedsASignIn(): void {
        $this->assertTrue((new \App\Controllers\MigrationJobs())->requireAuth('rerun'));

        // `from` is a reserved word, so the column is quoted by hand and the rows are
        // picked out here rather than in a where clause.
        $rows = $this->db->table('api_routes')
            ->select('`from`, is_public', false)
            ->get()
            ->getResultArray();

        $public = array_column($rows, 'is_public', 'from');

        $this->assertArrayNotHasKey('migration-jobs/([0-9]+)/started', $public);
        $this->assertArrayNotHasKey('migration-jobs/([0-9]+)/ended', $public);
        $this->assertSame(0, (int) $public['migration-jobs/([0-9]+)/rerun'], 'anyone could rerun a migration');
        $this->assertSame(0, (int) $public['migration_jobs'], 'anyone could read every migration log');
    }

    // <editor-fold desc="Fixtures">

    /**
     * @return array<string, mixed>
     */
    private function migrationJob(): array {
        $deployment = Fixtures::deployableDeployment();
        $this->db->table('migration_jobs')->insert([
            'deployment_id' => $deployment->id,
            'status' => \MigrationJobStatusTypes::Deploying,
            'log' => '',
            'command' => 'php spark migrate',
            'image' => 'registry.example.org/app:1.0',
            'created' => date('Y-m-d H:i:s'),
        ]);

        return $this->row((int) $this->db->insertID());
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id): array {
        return $this->db->table('migration_jobs')->where('id', $id)->get()->getRowArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
