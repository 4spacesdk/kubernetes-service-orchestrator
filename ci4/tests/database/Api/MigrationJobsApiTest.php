<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;

/**
 * How a migration job reports back.
 *
 * The job runs inside the customer's cluster and calls home when it starts and when it
 * finishes, posting its log as the request body. The two endpoints are public - the pod has no
 * sign-in - so the pod sends a token of its own, issued for that one job, and a call without it
 * writes nothing. The ids are sequential: without the token anyone could end a job with a log
 * that makes the post-update commands run before the migration has.
 */
class MigrationJobsApiTest extends ControllerTestCase {

    public function testStartingMarksTheJobStarted(): void {
        $job = $this->migrationJob();

        $body = $this->decode($this->withJobToken($job)->put("migration-jobs/{$job['id']}/started"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame(\MigrationJobStatusTypes::Started, $this->row($job['id'])['status']);
    }

    /**
     * Both endpoints build their timestamp with `date('Y-m-d H:i-s')` - a hyphen where the
     * second colon belongs, so the string handed to the column is `16:04-06`. MySQL forgives
     * it and stores the right moment anyway, which is the only reason nobody has noticed.
     * Pinned because the next person to read that format string will assume it is broken,
     * and because a stricter mode would make it so.
     */
    public function testTheTimestampSurvivesTheTypoInItsFormat(): void {
        $job = $this->migrationJob();

        $this->withJobToken($job)->put("migration-jobs/{$job['id']}/started");

        $started = $this->row($job['id'])['started'];
        $this->assertNotSame('0000-00-00 00:00:00', $started);
        $this->assertSame($started, date('Y-m-d H:i:s', strtotime($started)), 'a real datetime');
    }

    public function testEndingMarksTheJobEnded(): void {
        $job = $this->migrationJob();

        $body = $this->decode($this->withJobToken($job)->put("migration-jobs/{$job['id']}/ended"));

        $this->assertSame('OK', $body['status']);
        $this->assertNotSame('0000-00-00 00:00:00', $this->row($job['id'])['ended']);
    }

    /**
     * The log is the request body, read through the request object - it used to be read from
     * `php://input`, which the test harness never writes to, so it could not be tested at all.
     */
    public function testTheLogIsWhatTheJobSent(): void {
        $job = $this->migrationJob();

        $this->withJobToken($job)->withBody("Migrated 4 files\nDone.\n")->put("migration-jobs/{$job['id']}/ended");

        $this->assertSame("Migrated 4 files\nDone.", $this->row($job['id'])['log']);
    }

    /**
     * Neither endpoint needs a sign-in; each needs the job's own token.
     */
    public function testBothEndpointsAnswerTheJobsOwnToken(): void {
        $job = $this->migrationJob();

        $this->assertSame('OK', $this->decode($this->withJobToken($job)->put("migration-jobs/{$job['id']}/started"))['status']);
        $this->assertSame('OK', $this->decode($this->withJobToken($job)->withBody('log')->put("migration-jobs/{$job['id']}/ended"))['status']);
    }

    /**
     * Without the token nothing is written - no start, no end, no log.
     */
    public function testACallWithoutTheTokenIsRefusedAndWritesNothing(): void {
        $job = $this->migrationJob();

        $started = $this->put("migration-jobs/{$job['id']}/started");
        $ended = $this->withBody('Done.')->put("migration-jobs/{$job['id']}/ended");

        $this->assertSame(401, $started->response()->getStatusCode());
        $this->assertSame(401, $ended->response()->getStatusCode());
        $row = $this->row($job['id']);
        $this->assertSame(\MigrationJobStatusTypes::Deploying, $row['status']);
        $this->assertSame('', $row['log']);
        $this->assertNull($row['ended']);
    }

    /**
     * A token is for its own job only.
     */
    public function testAnotherJobsTokenIsRefused(): void {
        $job = $this->migrationJob();
        $other = $this->migrationJob();

        $response = $this->withJobToken($other)->withBody('Done.')->put("migration-jobs/{$job['id']}/ended");

        $this->assertSame(401, $response->response()->getStatusCode());
        $this->assertSame('', $this->row($job['id'])['log']);
    }

    /**
     * A job started before there were tokens has none, and accepts nothing - rather than
     * accepting anything, which is what an empty hash compared to an empty header would do.
     */
    public function testAJobWithoutATokenAcceptsNothing(): void {
        $job = $this->migrationJob();
        $this->db->table('migration_jobs')->where('id', $job['id'])->update(['callback_token_hash' => null]);

        $response = $this->withHeaders([\App\Controllers\MigrationJobs::TokenHeader => ''])->put("migration-jobs/{$job['id']}/started");

        $this->assertSame(401, $response->response()->getStatusCode());
    }

    /**
     * The hash is never sent anywhere: the job is pushed to every open browser and listed by
     * the API.
     */
    public function testTheTokensHashStaysInTheDatabase(): void {
        $job = new \App\Entities\MigrationJob();
        $job->find($this->migrationJob()['id']);

        $this->assertArrayNotHasKey('callback_token_hash', $job->toArray());
    }

    /**
     * An id that is not there is a 404. It used to be saved as a new row.
     */
    public function testAnUnknownJobIsNotFoundAndNotCreated(): void {
        $before = $this->db->table('migration_jobs')->countAllResults();

        $response = $this->withBody('Done.')->put('migration-jobs/424242/ended');

        $this->assertSame(404, $response->response()->getStatusCode());
        $this->assertSame($before, $this->db->table('migration_jobs')->countAllResults());
    }

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
     * **The controller and the table disagree, and the table is what runs.**
     *
     * `requireAuth()` returns false for every method name, so read as code this controller
     * is entirely public - including `GET /migration_jobs`, which lists every migration and
     * its log. It is not: the column says otherwise for everything but the two callbacks,
     * and the column is the only thing the authorization hook reads. The method has
     * no call sites anywhere in the application.
     *
     * Both halves are asserted so that closing the gap from either side is visible. The two
     * public rows are a deliberate exception: the job runs inside the customer's
     * cluster and has no token to call home with.
     */
    public function testTheControllerCallsItselfPublicAndOnlyTwoRoutesActuallyAre(): void {
        $controller = new \App\Controllers\MigrationJobs();

        $this->assertFalse($controller->requireAuth('setStarted'));
        $this->assertFalse($controller->requireAuth('rerun'), 'the declaration says rerun is public too');
        $this->assertFalse($controller->requireAuth('get'));

        // `from` is a reserved word, so the column is quoted by hand and the rows are
        // picked out here rather than in a where clause.
        $rows = $this->db->table('api_routes')
            ->select('`from`, is_public', false)
            ->get()
            ->getResultArray();

        $public = array_column($rows, 'is_public', 'from');

        $this->assertSame(1, (int) $public['migration-jobs/([0-9]+)/started'], 'the job could no longer call home');
        $this->assertSame(1, (int) $public['migration-jobs/([0-9]+)/ended'], 'the job could no longer call home');
        $this->assertSame(0, (int) $public['migration-jobs/([0-9]+)/rerun'], 'anyone could rerun a migration');
        $this->assertSame(0, (int) $public['migration_jobs'], 'anyone could read every migration log');
    }

    // <editor-fold desc="Fixtures">

    /**
     * @return array<string, mixed>
     */
    private function migrationJob(): array {
        $deployment = Fixtures::deployableDeployment();
        $token = bin2hex(random_bytes(8));
        $this->db->table('migration_jobs')->insert([
            'deployment_id' => $deployment->id,
            'status' => \MigrationJobStatusTypes::Deploying,
            'log' => '',
            'command' => 'php spark migrate',
            'image' => 'registry.example.org/app:1.0',
            'callback_token_hash' => hash('sha256', $token),
            'created' => date('Y-m-d H:i:s'),
        ]);

        return $this->row((int) $this->db->insertID()) + ['token' => $token];
    }

    /**
     * @param array<string, mixed> $job
     */
    private function withJobToken(array $job): static {
        return $this->withHeaders([\App\Controllers\MigrationJobs::TokenHeader => $job['token']]);
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
