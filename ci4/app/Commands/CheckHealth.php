<?php namespace App\Commands;

use App\Entities\CronJob;
use App\Libraries\Health\HealthCheck;
use App\Libraries\Kubernetes\KubeHelper;
use CodeIgniter\CLI\BaseCommand;
use DebugTool\Data;

/**
 * Every deployment's runtime health, and its status, once a minute - see `HealthCheck`.
 *
 *   app:check-health         every deployment
 *   app:check-health <id>    one, e.g. to see what it makes of a deployment right now
 *
 * A cluster that cannot be read is written to the job's log and changes nothing else.
 */
class CheckHealth extends BaseCommand {

    public $group = 'app';
    public $name = 'app:check-health';
    public $description = "Work out the deployments' runtime health from the cluster";
    protected $usage = 'app:check-health [deployment id]';
    protected $arguments = [
        'deployment id' => 'Only this deployment',
    ];
    protected $options = [

    ];

    public function run(array $params) {
        $onlyDeploymentId = isset($params[0]) ? (int) $params[0] : null;
        Data::debug(get_class($this), $onlyDeploymentId === null ? 'all' : "deployment {$onlyDeploymentId}");

        $job = new CronJob();
        $job->find(\CronJobIds::CheckHealth);
        if ($onlyDeploymentId === null && $job->exists()) {
            $job->last_run = date('Y-m-d H:i:s');
            $job->save();
        }

        try {
            $summary = HealthCheck::Run($onlyDeploymentId);
            Data::debug('checked', $summary['checked'], 'changed', $summary['changed'], 'notified', $summary['notified'], 'status changed', $summary['status']);
        } catch (\Throwable $e) {
            Data::debug('The cluster could not be read, nothing was changed:', KubeHelper::PrintException($e));
        }

        if ($onlyDeploymentId === null && $job->exists()) {
            $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
            $job->save();
        }
    }

}
