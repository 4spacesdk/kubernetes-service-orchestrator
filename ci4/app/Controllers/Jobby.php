<?php namespace App\Controllers;

use App\Entities\CronJob;
use CodeIgniter\CLI\CommandRunner;
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
            $output = "/tmp/cronjob_".$job->id.'_'.time().'.txt';
            $command = php("jobby run $job->id >> {$output} 2>&1");

            $timePerDuplicate = 60 / $job->duplicates;
            try {
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
            } catch (\Jobby\Exception $e) {
                $this->fail($e->getMessage());
            }
        }

        try {
            $jobby->run();
        } catch (\Jobby\Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->success();
    }

    public function run(int $cronJobId) {
        $_SERVER['argc'] = 0;
        if(!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'w'));
        if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));

        $commandRunner = new CommandRunner();
        $commandRunner->initController($this->request, $this->response, $this->logger);

        $cronJob = new CronJob();
        $cronJob->find($cronJobId);
        if($cronJob->exists()) {
            $cronJob->last_run = date('Y-m-d H:i:s');

            try {
                $response = $commandRunner->index([$cronJob->command]);
                \DebugTool\Data::debug('response:');
                Data::debug($response);
                \DebugTool\Data::debug("^");
            } catch(\ReflectionException $e) {
                \DebugTool\Data::debug($e->getMessage());
            }

            $cronJob->last_log = json_encode(Data::getStore(), JSON_PRETTY_PRINT);
            $cronJob->save();
        }

        $this->success();
    }
}
