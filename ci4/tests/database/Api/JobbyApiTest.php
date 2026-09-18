<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\CronJob;
use App\Tests\Fakes\HarmlessCommand;
use App\Tests\Fakes\ReflectionFailingCommand;
use CodeIgniter\CLI\Commands;

/**
 * The cron endpoint: one half lays out the plan, the other runs a single job.
 *
 * `index()` reads `cron_jobs`, turns each row into as many jobby entries as the row has
 * `duplicates`, and calls `run()`, which starts the due ones as background processes.
 * `run($id)` is what those processes call back on: it executes the row's spark command and
 * writes what happened into `last_log`.
 *
 * **Both are public** - see `api_routes` below, and SEC-10. A GET on `/jobby` from anyone
 * lays out the whole plan and starts every due job, and a GET on `/jobby/run/3` runs the
 * command row 3 points at, right away. That is not a change that belongs in a test, but it
 * is worth having written down exactly here.
 *
 * Neither the plan nor the command may start anything during a test run. The plan is kept
 * still with a schedule that never falls due (the 30th of February), so `Jobby::run()`
 * walks the list without calling `exec` once. The command is kept still with two fakes
 * written into the command registry for the duration of the test.
 */
class JobbyApiTest extends ControllerTestCase {

    /**
     * A schedule that is valid and never due. February has no 30th.
     */
    private const NEVER = '0 0 30 2 *';

    public function setUp(): void {
        parent::setUp();

        $this->registerTheFakeCommands();
    }

    public function tearDown(): void {
        $this->forgetTheFakeCommands();

        parent::tearDown();
    }

    /**
     * What the controller says about itself, held next to what is enforced.
     *
     * `requireAuth()` answers no for every method, and the table agrees: both routes were
     * created with `ApiRoute::public()`. So there is no disagreement to find here - only
     * two halves pointing the same way, and it is those two that leave the endpoint open.
     * Close either one and this test goes red, and that failure is the fix landing.
     */
    public function testBothHalvesAgreeThatCronIsOpenToAnyone(): void {
        $controller = new \App\Controllers\Jobby();

        $this->assertFalse($controller->requireAuth('index'), 'the controller now asks for a token');
        $this->assertFalse($controller->requireAuth('run'));

        $rows = $this->db->table('api_routes')
            ->select('`from`, is_public', false)
            ->where('method', 'get')
            ->get()
            ->getResultArray();
        $public = array_column($rows, 'is_public', 'from');

        $this->assertSame(1, (int) $public['jobby'], 'the route is closed now - SEC-10 is fixed and this test has done its job');
        $this->assertSame(1, (int) $public['jobby/run/([0-9]+)']);
    }

    // <editor-fold desc="The plan being laid out">

    /**
     * `duplicates` is how many times within the minute the job should run, and it becomes
     * that many jobby entries with the same schedule and a wait of its own in front. The
     * minute is split into equal pieces: three duplicates become 0, 20 and 40 seconds.
     *
     * It is the only arithmetic in the method, and it has no reading other than what it
     * writes into its own log on the way.
     */
    public function testAJobWithDuplicatesBecomesOneEntryPerDuplicateSpreadOverTheMinute(): void {
        $job = $this->onlyCronJob(['duplicates' => 3, 'schedule' => self::NEVER]);

        $added = $this->addedEntries($this->decode($this->get('jobby')));

        $this->assertCount(3, $added);
        $this->assertStringContainsString('sleep 0 &&', $added[0]);
        $this->assertStringContainsString('sleep 20 &&', $added[1]);
        $this->assertStringContainsString('sleep 40 &&', $added[2]);

        foreach ($added as $entry) {
            $this->assertStringContainsString(self::NEVER, $entry, 'the schedule did not come along');
            $this->assertStringContainsString("jobby run {$job->id} >>", $entry, 'the entry calls a different job back');
        }
    }

    /**
     * A single job does not wait: `60 / 1` times zero is zero, and that is the ordinary row.
     */
    public function testASingleJobIsAddedOnceAndStartsAtOnce(): void {
        $this->onlyCronJob(['duplicates' => 1, 'schedule' => self::NEVER]);

        $added = $this->addedEntries($this->decode($this->get('jobby')));

        $this->assertCount(1, $added);
        $this->assertStringContainsString('sleep 0 &&', $added[0]);
    }

    /**
     * The plan is laid out for every row, not only for the first one.
     */
    public function testEveryCronJobInTheTableGetsItsOwnEntries(): void {
        $this->db->table('cron_jobs')->emptyTable();
        $first = $this->aCronJob(['name' => 'first', 'duplicates' => 1, 'schedule' => self::NEVER]);
        $second = $this->aCronJob(['name' => 'second', 'duplicates' => 2, 'schedule' => self::NEVER]);

        $added = $this->addedEntries($this->decode($this->get('jobby')));

        $this->assertCount(3, $added);
        $this->assertStringContainsString("jobby run {$first->id} >>", $added[0]);
        $this->assertStringContainsString("jobby run {$second->id} >>", $added[1]);
        $this->assertStringContainsString("jobby run {$second->id} >>", $added[2]);
    }

    /**
     * The answer is a success once the plan is laid out - and that is where `$jobby->run()`
     * sits. None of the jobs is due, so the list is walked without starting anything.
     */
    public function testAPlanThatNothingIsDueInIsStillASuccess(): void {
        $this->onlyCronJob(['duplicates' => 1, 'schedule' => self::NEVER]);

        $body = $this->decode($this->get('jobby'));

        $this->assertSame('OK', $body['status']);
    }

    /**
     * An entry is named `"{job name} x {number}"`, and that is the name jobby writes its
     * lock files and its failure report under - two entries called the same thing would
     * block each other.
     *
     * The name is otherwise out of reach from the outside, but jobby refuses an entry
     * without a schedule and names it while doing so. That refusal is at the same time one
     * of the controller's two `\Jobby\Exception` branches: it answers ERROR and **stops** -
     * without the `return` it would carry on and lay out half a plan.
     */
    public function testAJobWithoutAScheduleIsRefusedByNameAndStopsThere(): void {
        $this->onlyCronJob(['name' => 'nightly cleanup', 'duplicates' => 2, 'schedule' => '']);

        $body = $this->decode($this->get('jobby'));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame("'schedule' is required for 'nightly cleanup x 0' job", $body['error']);
        $this->assertSame([], $this->addedEntries($body), 'it carried on adding entries after the refusal');
    }

    /**
     * **Today's behaviour, and it is the sharp edge of the endpoint.** A schedule that is
     * not a cron expression is accepted all the way into the plan, and `$jobby->run()` then
     * throws a plain `InvalidArgumentException` from the cron parser.
     *
     * The controller catches `\Jobby\Exception` and nothing else, so this one goes all the
     * way out: the request ends in a five-hundred, and **no job at all is started** - one
     * bad row stops every other job in the table, every minute, until somebody notices.
     */
    public function testOneUnparseableScheduleStopsTheWholeCronRun(): void {
        $this->onlyCronJob(['schedule' => 'not a cron']);

        $this->expectException(\InvalidArgumentException::class);

        $this->get('jobby');
    }

    // </editor-fold>

    // <editor-fold desc="The job being run">

    /**
     * A job that does not exist is not an error - the answer is still `success()`.
     *
     * That is worth pinning down on purpose: the endpoint is public, and an id that does
     * not exist gets exactly the same answer as one that does. There is nothing in the
     * answer to read off about which jobs exist.
     */
    public function testAJobThatDoesNotExistIsAnsweredWithSuccessAndNothingHappens(): void {
        $this->db->table('cron_jobs')->emptyTable();

        $body = $this->decode($this->get('jobby/run/424242'));

        $this->assertSame('OK', $body['status']);
        $this->assertSame(0, (int) $this->db->table('cron_jobs')->countAllResults());
    }

    /**
     * The ordinary run: the command is executed, its output ends up in the log, and the row
     * remembers when it last ran.
     *
     * `last_log` is the whole debug store as pretty-printed json, which is the same thing
     * the response is built from. It is where you look when a cron job did not do what it
     * should.
     */
    public function testARealCommandIsRunAndItsOutputIsKeptOnTheRow(): void {
        $job = $this->onlyCronJob(['command' => HarmlessCommand::NAME, 'last_run' => null]);

        $body = $this->decode($this->get("jobby/run/{$job->id}"));

        $this->assertSame('OK', $body['status']);

        $row = $this->cronJobRow($job->id);
        $this->assertNotNull($row['last_run'], 'the row does not remember that it ran');
        $this->assertStringContainsString(HarmlessCommand::OUTPUT, $row['last_log']);
        $this->assertStringContainsString("\n", $row['last_log'], 'the log was written on one line');
    }

    /**
     * The command that is run is the row's own field, not something the controller makes
     * up. Run a different one and every cron job runs the same thing.
     */
    public function testTheCommandThatIsRunIsTheOneOnTheRow(): void {
        $job = $this->onlyCronJob(['command' => ReflectionFailingCommand::NAME]);

        $this->get("jobby/run/{$job->id}");

        $log = $this->cronJobRow($job->id)['last_log'];
        $this->assertStringContainsString(ReflectionFailingCommand::MESSAGE, $log);
        $this->assertStringNotContainsString(HarmlessCommand::OUTPUT, $log);
    }

    /**
     * A command that falls over does not bring cron down with it. The message goes into the
     * log, the answer is still a success, and the row is saved with whatever happened.
     *
     * Note what the branch does *not* cover: it catches `\ReflectionException`, which the
     * shape of the controller suggests is raised by an unknown command name. It is not -
     * see the test below.
     */
    public function testACommandThatThrowsIsLoggedAndTheRequestStillSucceeds(): void {
        $job = $this->onlyCronJob(['command' => ReflectionFailingCommand::NAME]);

        $body = $this->decode($this->get("jobby/run/{$job->id}"));

        $this->assertSame('OK', $body['status']);
        $this->assertStringContainsString(ReflectionFailingCommand::MESSAGE, $this->cronJobRow($job->id)['last_log']);
    }

    /**
     * An unknown command name throws nothing. `Commands::run()` looks the name up in its
     * own registry, writes "Command not found" to stderr and returns an error code nobody
     * looks at - so the row is saved as though all was well, and the log is empty.
     *
     * That is today's behaviour, and it is worth knowing: a cron job with a typo in its
     * command fails silently, every minute, for ever.
     *
     * The line this test writes to stderr during the run is the whole of the reporting that
     * exists - it goes to `STDERR` and therefore neither into `last_log` nor into the
     * response.
     */
    public function testAMisspelledCommandFailsSilentlyAndLooksLikeASuccess(): void {
        $job = $this->onlyCronJob(['command' => 'kso:no-such-command']);

        $body = $this->decode($this->get("jobby/run/{$job->id}"));

        $this->assertSame('OK', $body['status']);
        $this->assertNotNull($this->cronJobRow($job->id)['last_run']);
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * @param array<string, mixed> $overrides
     */
    private function onlyCronJob(array $overrides = []): CronJob {
        // The rows are seeded by the migrations, and they point at real commands with real
        // schedules. Away with them inside the transaction, so the plan is the test's alone.
        $this->db->table('cron_jobs')->emptyTable();

        return $this->aCronJob($overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function aCronJob(array $overrides = []): CronJob {
        $job = new CronJob();
        foreach (array_merge([
            'name' => 'a job',
            'schedule' => self::NEVER,
            'command' => HarmlessCommand::NAME,
            'duplicates' => 1,
        ], $overrides) as $field => $value) {
            $job->{$field} = $value;
        }
        $job->save();

        $saved = new CronJob();
        $saved->find($job->id);

        return $saved;
    }

    /**
     * @return array<string, mixed>
     */
    private function cronJobRow(int $id): array {
        return $this->db->table('cron_jobs')->where('id', $id)->get()->getRowArray();
    }

    /**
     * The lines `index()` wrote while laying out the plan - one per entry, in the order
     * they were added.
     *
     * @param array<string, mixed> $body
     * @return array<int, string>
     */
    private function addedEntries(array $body): array {
        return array_values(array_filter(
            array_map(fn ($line) => is_string($line) ? $line : json_encode($line), $body['debug'] ?? []),
            fn (string $line) => str_contains($line, 'Jobby Added')
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    /**
     * Write the fakes into the command registry.
     *
     * Command discovery looks in the `Commands/` folders of the namespaces the autoloader
     * knows about, and `tests/_fakes` is not one of them. The registry is a protected list
     * on the shared `commands` service, and the entry put in here is exactly the one a
     * discovered command would get.
     */
    private function registerTheFakeCommands(): void {
        $this->writeTheCommandRegistry(array_merge($this->theCommandRegistry(), [
            HarmlessCommand::NAME => [
                'class' => HarmlessCommand::class,
                'file' => __FILE__,
                'group' => 'Testing',
                'description' => '',
            ],
            ReflectionFailingCommand::NAME => [
                'class' => ReflectionFailingCommand::class,
                'file' => __FILE__,
                'group' => 'Testing',
                'description' => '',
            ],
        ]));
    }

    private function forgetTheFakeCommands(): void {
        $registry = $this->theCommandRegistry();
        unset($registry[HarmlessCommand::NAME], $registry[ReflectionFailingCommand::NAME]);

        $this->writeTheCommandRegistry($registry);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function theCommandRegistry(): array {
        $property = (new \ReflectionClass(Commands::class))->getProperty('commands');

        return $property->getValue(service('commands'));
    }

    /**
     * @param array<string, array<string, string>> $registry
     */
    private function writeTheCommandRegistry(array $registry): void {
        $property = (new \ReflectionClass(Commands::class))->getProperty('commands');
        $property->setValue(service('commands'), $registry);
    }

    // </editor-fold>

}
