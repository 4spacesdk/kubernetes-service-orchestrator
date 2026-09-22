<?php namespace App\Controllers;

use App\Entities\CronJob;
use App\Libraries\Audit\AuditContext;
use Config\Database;
use Cron\CronExpression;
use DebugTool\Data;

class Jobby extends \App\Core\BaseController {

    /**
     * The header the scheduler proves itself with.
     *
     * Not an OAuth token: the caller is a `curl` container in a CronJob the chart installs,
     * started once a minute from inside the cluster, and there is nobody to sign it in. A
     * value both sides are given is what stands in for that.
     */
    public const string TokenHeader = 'X-Cron-Token';

    /**
     * No OAuth on this controller, which is why the check below exists.
     *
     * The route stays `is_public` in the table for the same reason: closing it there means
     * "needs an access token", and the scheduler has none. What it has is `CRON_TOKEN`.
     */
    public function requireAuth(string $method): bool {
        return false;
    }

    /**
     * Whether this request came from the scheduler.
     *
     * `GET /api/jobby` runs **every** cron job in the installation, and it answered anyone
     * who could reach the API, as often as they asked. There is nothing behind it to abuse
     * beyond that - the jobs are the ones an operator configured - but starting all of them
     * on demand is enough: a job that deploys, sends mail or talks to a registry, run on
     * somebody else's schedule.
     *
     * `hash_equals()` rather than `==`, so a wrong token takes the same time as a right one.
     *
     * Unset `CRON_TOKEN` refuses rather than waves through. It is the chart that supplies
     * it, from a Secret it generates itself, so there is nothing for an operator to set -
     * and an installation where it is missing is one where this endpoint would otherwise be
     * open to everyone.
     */
    private function isTheScheduler(): bool {
        $expected = (string) getenv('CRON_TOKEN');
        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, (string) $this->request->getHeaderLine(self::TokenHeader));
    }

    public function index() {
        if (!$this->isTheScheduler()) {
            $this->fail('Not allowed', 401);
            return;
        }

        $now = new \DateTimeImmutable();

        $jobs = new CronJob();
        $jobs->find();
        foreach($jobs as $job) {
            if (!$this->canBeScheduled($job)) {
                continue;
            }
            $due = (new CronExpression($job->schedule))->isDue($now);

            // One file per job, overwritten each run, rather than one per run appended to
            // for ever: this used to be `cronjob_<id>_<time>.txt` and nothing ever removed
            // one, so a container collected a file a minute per job for as long as it
            // lived. What the run did is written to `last_log` on the row; the file is the
            // raw output, and only the last one is worth keeping.
            $output = "/tmp/cronjob_".$job->id.'.txt';
            $command = php("jobby run $job->id > {$output} 2>&1");

            $timePerDuplicate = 60 / $job->duplicates;
            for ($i = 0 ; $i < $job->duplicates ; $i++) {
                $sleep = round($i * $timePerDuplicate);
                $cmd = "sleep {$sleep} && {$command}";

                Data::debug(get_class($this), "Added", $job->schedule, $output, $cmd);
                if ($due) {
                    (self::$start ?? self::startInTheBackground(...))($cmd);
                }
            }
        }

        $this->success();
    }

    /**
     * How a due job is started. The test suite puts a recorder here: a real start would run the
     * job outside the test, under the development environment, against the development
     * database.
     *
     * @var null|\Closure(string): void
     */
    public static ?\Closure $start = null;

    /**
     * Started and left: the request answers at once, and the job reports on its own row.
     *
     * This is what jobby did, and all of what kso used it for - it read the table, asked the
     * cron parser whether each row was due, and started the due ones. It is gone: it had not
     * been released since 2020 and brought an abandoned mailer and an old symfony/process with it.
     */
    private static function startInTheBackground(string $command): void {
        exec('nohup sh -c ' . escapeshellarg($command) . ' > /dev/null 2>&1 &');
    }

    /**
     * Whether this row's schedule is something jobby can be handed.
     *
     * Checked here rather than left to jobby, which checks it in two places and neither is
     * survivable: `add()` refuses a missing schedule with a `\Jobby\Exception`, and `run()`
     * hands an unparseable one to the cron parser, which answers with a plain
     * `InvalidArgumentException`. Both used to come out of `index()` - the second past a
     * `catch` that only names `\Jobby\Exception` - and end the request with **no job
     * started at all**. One mistyped row stopped every scheduled job in the installation,
     * every minute, until somebody noticed.
     *
     * So a row that cannot be scheduled is one job that does not run, and it is written down
     * on itself: the cron page then says why, rather than leaving the job to look idle.
     * `last_run` is deliberately left alone, because it did not run.
     */
    private function canBeScheduled(CronJob $job): bool {
        $schedule = (string) $job->schedule;
        if (strlen($schedule) && CronExpression::isValidExpression($schedule)) {
            return true;
        }

        Data::debug(get_class($this), 'Skipped', $job->name, '- not a cron expression:', $schedule);

        $job->last_log = json_encode([
            'status' => 'ERROR',
            'error' => "'{$schedule}' is not a cron expression, so this job was not scheduled",
        ], JSON_PRETTY_PRINT);
        $job->save();

        return false;
    }

    public function run(int $cronJobId) {
        AuditContext::Restore(null, AuditContext::Cron);
        $_SERVER['argc'] = 0;
        if(!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'w'));
        if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));

        $cronJob = new CronJob();
        $cronJob->find($cronJobId);
        if($cronJob->exists()) {
            // One run of a job at a time, in the database rather than in a file: the scheduler
            // may call a different pod each minute, and a long run on one would not stop the
            // next from starting on another. Held until this process ends.
            if (!$this->takeTheLock($cronJobId)) {
                Data::debug(get_class($this), 'Still running from before - not started again:', $cronJob->name);
                $this->success();
                return;
            }

            $cronJob->last_run = date('Y-m-d H:i:s');

            // Asked before it is run, because a name that is not in the registry raises
            // nothing: `Commands::run()` writes "Command not found" to stderr - which goes
            // neither into `last_log` nor into the response - and returns an exit code no
            // caller looks at. A cron job with a typo in its command therefore recorded a
            // `last_run` and an empty log, every minute, for ever.
            $name = strtok($cronJob->command, ' ');
            if (!array_key_exists($name, service('commands')->getCommands())) {
                Data::debug(get_class($this), 'No such command:', $cronJob->command);
            } else {
                try {
                    $response = command($cronJob->command);
                    Data::debug($response);
                } catch(\Throwable $e) {
                    // Throwable rather than ReflectionException: a command is arbitrary code,
                    // and a `TypeError` in one of them is not an `Exception`. Catching the
                    // narrower one let it past, and a cron run that ends in a 500 writes no
                    // `last_log` at all.
                    Data::debug($e->getMessage());
                }
            }

            $cronJob->last_log = json_encode(Data::getStore(), JSON_PRETTY_PRINT);
            $cronJob->save();
            $this->releaseTheLock($cronJobId);
        }

        $this->success();
    }

    public static function LockName(int $cronJobId): string {
        return "kso-cron-{$cronJobId}";
    }

    private function takeTheLock(int $cronJobId): bool {
        $row = Database::connect()->query('SELECT GET_LOCK(?, 0) AS taken', [self::LockName($cronJobId)])->getRow();
        return (int) ($row->taken ?? 0) === 1;
    }

    private function releaseTheLock(int $cronJobId): void {
        Database::connect()->query('SELECT RELEASE_LOCK(?)', [self::LockName($cronJobId)]);
    }
}
