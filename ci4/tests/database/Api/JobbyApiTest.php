<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\CronJob;
use App\Tests\Fakes\HarmlessCommand;
use App\Tests\Fakes\ReflectionFailingCommand;
use CodeIgniter\CLI\Commands;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The cron endpoint: one half lays out the plan, the other runs a single job.
 *
 * `index()` reads `cron_jobs`, turns each row into as many jobby entries as the row has
 * `duplicates`, and calls `run()`, which starts the due ones as background processes.
 * `run($id)` is what those processes call back on: it executes the row's spark command and
 * writes what happened into `last_log`.
 *
 * **Both used to be reachable from outside.** A GET on `/jobby` from anyone laid out the
 * whole plan and started every due job, and a GET on `/jobby/run/3` ran the command row 3
 * points at, right away. The first now asks for the scheduler's token; the second is gone,
 * because nothing called it over HTTP - the background processes reach `run()` through the
 * CLI route in `Config/Routes.php`.
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

    private string|false $cronTokenAsFound;

    /**
     * A token of the test's own, so the suite does not depend on the environment it runs in:
     * the chart sets CRON_TOKEN, a build machine does not.
     */
    public function setUp(): void {
        parent::setUp();

        $this->cronTokenAsFound = getenv('CRON_TOKEN');
        putenv('CRON_TOKEN=token-of-the-test');

        $this->registerTheFakeCommands();
    }

    public function tearDown(): void {
        $this->forgetTheFakeCommands();

        $this->cronTokenAsFound === false ? putenv('CRON_TOKEN') : putenv('CRON_TOKEN=' . $this->cronTokenAsFound);

        parent::tearDown();
    }

    /**
     * Cron is not open to anyone any more, and this is the shape of the answer.
     *
     * `GET /api/jobby` runs **every** cron job in the installation and used to answer
     * whoever asked. It is still `is_public` in the route table, and that is deliberate:
     * closing it there means "needs an access token", and the caller is a curl container in
     * the chart's CronJob with nobody to sign it in. What it carries instead is `CRON_TOKEN`,
     * from a Secret the chart generates and gives to both sides.
     *
     * `GET /api/jobby/run/{id}` - one named job, chosen by the caller - is gone rather than
     * guarded. Nothing reached it over HTTP: jobby runs a job through the **CLI** route in
     * `Config/Routes.php`, which is what `Jobby::index()` builds a command for.
     */
    public function testRunningEveryCronJobNeedsTheSchedulersToken(): void {
        $response = $this->get('jobby');

        $this->assertSame(401, $response->response()->getStatusCode());
        $this->assertSame('ERROR', $this->decode($response)['status']);
    }

    public function testAWrongTokenIsRefusedToo(): void {
        $response = $this->withHeaders([\App\Controllers\Jobby::TokenHeader => 'not-the-token'])->get('jobby');

        $this->assertSame(401, $response->response()->getStatusCode());
    }

    /**
     * And with no `CRON_TOKEN` configured at all it refuses rather than waves through: an
     * installation that has not been given one is not an installation where this should be
     * open to everybody.
     */
    public function testWithNoTokenConfiguredNothingIsAccepted(): void {
        $this->withCronToken('', function (): void {
            $response = $this->withHeaders([\App\Controllers\Jobby::TokenHeader => ''])->get('jobby');

            $this->assertSame(401, $response->response()->getStatusCode());
        });
    }

    public function testTheByIdRouteIsGoneFromTheTable(): void {
        $this->assertSame(
            0,
            $this->db->table('api_routes')->where('from', 'jobby/run/([0-9]+)')->countAllResults()
        );
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

        $added = $this->addedEntries($this->decode($this->asTheScheduler()->get('jobby')));

        $this->assertCount(3, $added);
        $this->assertStringContainsString('sleep 0 &&', $added[0]);
        $this->assertStringContainsString('sleep 20 &&', $added[1]);
        $this->assertStringContainsString('sleep 40 &&', $added[2]);

        foreach ($added as $entry) {
            $this->assertStringContainsString(self::NEVER, $entry, 'the schedule did not come along');
            $this->assertStringContainsString("jobby run {$job->id} >", $entry, 'the entry calls a different job back');
        }
    }

    /**
     * The raw output goes to one file per job, overwritten each run.
     *
     * It used to be `cronjob_<id>_<time>.txt`, appended to and never removed, so a container
     * collected a file a minute per job for as long as it lived. What the run decided is on
     * the row in `last_log`; this is the raw output, and only the last one is worth keeping.
     */
    public function testTheRawOutputGoesToOneFilePerJobRatherThanOnePerRun(): void {
        $job = $this->onlyCronJob(['duplicates' => 1, 'schedule' => self::NEVER]);

        $added = $this->addedEntries($this->decode($this->asTheScheduler()->get('jobby')));

        $this->assertStringContainsString("> /tmp/cronjob_{$job->id}.txt 2>&1", $added[0]);
        $this->assertStringNotContainsString('>> /tmp/', $added[0], 'the file is appended to rather than replaced');
    }

    /**
     * A single job does not wait: `60 / 1` times zero is zero, and that is the ordinary row.
     */
    public function testASingleJobIsAddedOnceAndStartsAtOnce(): void {
        $this->onlyCronJob(['duplicates' => 1, 'schedule' => self::NEVER]);

        $added = $this->addedEntries($this->decode($this->asTheScheduler()->get('jobby')));

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

        $added = $this->addedEntries($this->decode($this->asTheScheduler()->get('jobby')));

        $this->assertCount(3, $added);
        $this->assertStringContainsString("jobby run {$first->id} >", $added[0]);
        $this->assertStringContainsString("jobby run {$second->id} >", $added[1]);
        $this->assertStringContainsString("jobby run {$second->id} >", $added[2]);
    }

    /**
     * The answer is a success once the plan is laid out - and that is where `$jobby->run()`
     * sits. None of the jobs is due, so the list is walked without starting anything.
     */
    public function testAPlanThatNothingIsDueInIsStillASuccess(): void {
        $this->onlyCronJob(['duplicates' => 1, 'schedule' => self::NEVER]);

        $body = $this->decode($this->asTheScheduler()->get('jobby'));

        $this->assertSame('OK', $body['status']);
    }

    /**
     * A row that cannot be scheduled is one job that does not run - not a cron run that does
     * not happen.
     *
     * This was the sharp edge of the endpoint. A schedule that is not a cron expression went
     * all the way into the plan, and `$jobby->run()` handed it to the cron parser, which
     * answers with a plain `InvalidArgumentException` - past a `catch` that names only
     * `\Jobby\Exception`. The request ended in a five-hundred with **no job at all
     * started**, so one mistyped row stopped every other job in the table, every minute,
     * until somebody noticed.
     *
     * The second job is here to prove the run carried on rather than merely survived.
     */
    #[DataProvider('theSchedulesThatCannotBeUsed')]
    public function testARowThatCannotBeScheduledIsSkippedAndTheRestStillRun(string $schedule): void {
        $this->db->table('cron_jobs')->emptyTable();
        $broken = $this->aCronJob(['name' => 'broken', 'schedule' => $schedule]);
        $healthy = $this->aCronJob(['name' => 'healthy', 'schedule' => self::NEVER]);

        $body = $this->decode($this->asTheScheduler()->get('jobby'));

        $this->assertSame('OK', $body['status']);
        $added = $this->addedEntries($body);
        $this->assertCount(1, $added);
        $this->assertStringContainsString("jobby run {$healthy->id} >", $added[0]);
        $this->assertStringNotContainsString("jobby run {$broken->id} >", $added[0]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function theSchedulesThatCannotBeUsed(): array {
        return [
            // Refused by jobby itself, with a `\Jobby\Exception`.
            'no schedule at all' => [''],

            // Accepted by jobby and refused by the cron parser underneath it, with a plain
            // `InvalidArgumentException`. This is the one that used to take the run down.
            'not a cron expression' => ['not a cron'],
            'too few fields' => ['* * *'],
            'a field out of range' => ['0 0 32 * *'],
        ];
    }

    /**
     * And the row says why, on itself. The cron page is where an operator looks, and a job
     * that is skipped every minute would otherwise look merely idle - indistinguishable from
     * one that simply has nothing to do.
     *
     * `last_run` is left alone on purpose: it did not run.
     */
    public function testASkippedRowRecordsWhyItWasNotScheduled(): void {
        $job = $this->onlyCronJob(['schedule' => 'not a cron', 'last_run' => null]);

        $this->asTheScheduler()->get('jobby');

        $row = $this->cronJobRow($job->id);
        $this->assertStringContainsString('not a cron expression', $row['last_log']);
        $this->assertStringContainsString("'not a cron'", $row['last_log'], 'the log does not say what was wrong with it');
        $this->assertNull($row['last_run'], 'the row claims to have run');
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

        $body = $this->runTheJob(424242);

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

        $body = $this->runTheJob($job->id);

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

        $this->runTheJob($job->id);

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

        $body = $this->runTheJob($job->id);

        $this->assertSame('OK', $body['status']);
        $this->assertStringContainsString(ReflectionFailingCommand::MESSAGE, $this->cronJobRow($job->id)['last_log']);
    }

    /**
     * A command name that is not in the registry is written down as such.
     *
     * It used to fail silently, every minute, for ever: an unknown name throws nothing -
     * `Commands::run()` writes "Command not found" to stderr, which reaches neither
     * `last_log` nor the response, and returns an exit code no caller looks at - so the row
     * was saved with a fresh `last_run` and an empty log, exactly like a job that had run
     * and found nothing to do.
     */
    public function testAMisspelledCommandIsRecordedRatherThanPassedOver(): void {
        $job = $this->onlyCronJob(['command' => 'kso:no-such-command']);

        $body = $this->runTheJob($job->id);

        $this->assertSame('OK', $body['status']);
        $this->assertStringContainsString('No such command', $this->cronJobRow($job->id)['last_log']);
        $this->assertStringContainsString('kso:no-such-command', $this->cronJobRow($job->id)['last_log']);
    }

    /**
     * A command that carries arguments is looked up by its name alone, or every cron job
     * with a parameter on it would be reported as missing.
     */
    public function testACommandWithArgumentsIsStillFound(): void {
        $job = $this->onlyCronJob(['command' => HarmlessCommand::NAME . ' --loud']);

        $this->runTheJob($job->id);

        $log = $this->cronJobRow($job->id)['last_log'];
        $this->assertStringNotContainsString('No such command', $log);
        $this->assertStringContainsString(HarmlessCommand::OUTPUT, $log);
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    /**
     * @param array<string, mixed> $overrides
     */
    /**
     * The next request carries the token the chart gives the scheduler.
     */
    private function asTheScheduler(): static {
        return $this->withHeaders([\App\Controllers\Jobby::TokenHeader => (string) getenv('CRON_TOKEN')]);
    }

    /**
     * One cron job run, through the controller rather than a route.
     *
     * There is no HTTP route to `Jobby::run()` any more - jobby reaches it with
     * `php public/index.php jobby run <id>`, through the CLI route in `Config/Routes.php` -
     * so the method is called the way CodeIgniter's own controller tests call one. What is
     * under test is what the method does to the row, which is the same either way.
     *
     * @return array<string, mixed> the envelope the method wrote
     */
    private function runTheJob(int $id): array {
        $controller = new \App\Controllers\Jobby();
        $controller->initController(
            \CodeIgniter\Config\Services::request(),
            \CodeIgniter\Config\Services::response(),
            \CodeIgniter\Config\Services::logger()
        );

        $controller->run($id);

        return \DebugTool\Data::getStore();
    }

    /**
     * @param callable(): void $body
     */
    private function withCronToken(string $value, callable $body): void {
        $original = getenv('CRON_TOKEN');

        putenv('CRON_TOKEN=' . $value);

        try {
            $body();
        } finally {
            if ($original === false) {
                putenv('CRON_TOKEN');
            } else {
                putenv('CRON_TOKEN=' . $original);
            }
        }
    }

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
            // Read from the log itself: the response carries it only in development.
            array_map(fn ($line) => is_string($line) ? $line : json_encode($line), \DebugTool\Data::getDebugger()),
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
