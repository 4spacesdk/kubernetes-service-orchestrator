<?php namespace App\Commands;

use App\Entities\CronJob;
use App\Libraries\MigrationJobs\MigrationJobWatcher;
use CodeIgniter\CLI\BaseCommand;
use DebugTool\Data;

/**
 * Follows migration jobs in the cluster - see `MigrationJobWatcher`.
 *
 *   app:watch-migration-job         one look at every job that has not ended, once a minute
 *   app:watch-migration-job <id>    this one, until it has ended - started with each job
 */
class WatchMigrationJob extends BaseCommand {

    public $group = 'app';
    public $name = 'app:watch-migration-job';
    public $description = 'Follow migration jobs in the cluster until they have ended';
    protected $usage = 'app:watch-migration-job [migration job id]';
    protected $arguments = [
        'migration job id' => 'Only this job, until it has ended',
    ];
    protected $options = [

    ];

    public function run(array $params) {
        if (isset($params[0])) {
            $id = (int) $params[0];
            Data::debug(get_class($this), "migration job {$id}", MigrationJobWatcher::Watch($id) ? 'ended' : 'not ended yet');
            return;
        }

        $job = new CronJob();
        $job->find(\CronJobIds::WatchMigrationJobs);
        if ($job->exists()) {
            $job->last_run = date('Y-m-d H:i:s');
            $job->save();
        }

        Data::debug(get_class($this), 'looked at', MigrationJobWatcher::Sweep(), 'migration jobs');

        if ($job->exists()) {
            $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
            $job->save();
        }
    }

}
