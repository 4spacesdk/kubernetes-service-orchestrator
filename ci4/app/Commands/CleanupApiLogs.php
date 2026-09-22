<?php namespace App\Commands;

use App\Entities\CronJob;
use CodeIgniter\CLI\BaseCommand;
use DebugTool\Data;
use RestExtension\Logs;

/**
 * Keep the API's log tables - every request, every error, every refusal - to a month. Nothing
 * removed a row before: a development database had a hundred thousand errors since April.
 */
class CleanupApiLogs extends BaseCommand {

    public $group = 'app';
    public $name = 'app:cleanup-api-logs';
    public $description = 'Remove API log rows older than the retention window';
    protected $arguments = [

    ];
    protected $options = [

    ];

    /** How long a row is kept, in days. */
    public const int RetentionDays = 30;

    public function run(array $params) {
        Data::debug(get_class($this), 'CleanupApiLogs');

        $job = new CronJob();
        $job->find(\CronJobIds::CleanupApiLogs);
        $job->last_run = date('Y-m-d H:i:s');
        $job->save();

        foreach (Logs::PruneOlderThan(self::RetentionDays) as $table => $removed) {
            Data::debug('removed', $removed, 'rows from', $table, 'older than', self::RetentionDays, 'days');
        }

        $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
        $job->save();
    }

}
