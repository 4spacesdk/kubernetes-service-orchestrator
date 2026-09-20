<?php namespace App\Controllers;

use App\Entities\CronJob;
use Cron\CronExpression;
use DebugTool\Data;

class Jobby extends \App\Core\BaseController {

    public function requireAuth(string $method): bool {
        return false;
    }

    public function index() {
        $jobby = new \Jobby\Jobby();

        $jobs = new CronJob();
        $jobs->find();
        foreach($jobs as $job) {
            if (!$this->canBeScheduled($job)) {
                continue;
            }

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
                $jobby->add("{$job->name} x {$i}", [
                    'command' => $cmd,
                    'schedule' => $job->schedule,
                    'debug' => true,
                ]);

                Data::debug(get_class($this), "Added", $job->schedule, $output, $cmd);
            }
        }

        $jobby->run();

        $this->success();
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
        $_SERVER['argc'] = 0;
        if(!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'w'));
        if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));

        $cronJob = new CronJob();
        $cronJob->find($cronJobId);
        if($cronJob->exists()) {
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
        }

        $this->success();
    }
}
