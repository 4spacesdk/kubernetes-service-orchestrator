<?php namespace App\Commands;

use App\Entities\CronJob;
use CodeIgniter\CLI\BaseCommand;
use Config\Database;
use DebugTool\Data;

/**
 * Keep the audit trail to 90 days - long enough to look back at an incident.
 *
 * The only thing that removes rows from `audit_events`; the tests hold every other part of kso
 * to adding them.
 */
class CleanupAuditEvents extends BaseCommand {

    public $group = 'app';
    public $name = 'app:cleanup-audit-events';
    public $description = 'Remove audit events older than the retention window';
    protected $arguments = [

    ];
    protected $options = [

    ];

    /** How long a row is kept, in days. */
    public const int RetentionDays = 90;

    public function run(array $params) {
        $job = new CronJob();
        $job->find(\CronJobIds::CleanupAuditEvents);
        $job->last_run = date('Y-m-d H:i:s');
        $job->save();

        $removed = self::RemoveOlderThanTheWindow();
        Data::debug('removed', $removed, 'audit events older than', self::RetentionDays, 'days');

        $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
        $job->save();
    }

    /**
     * In portions, so the first run does not hold a lock on the table for one long delete.
     *
     * @return int how many rows went
     */
    public static function RemoveOlderThanTheWindow(): int {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . self::RetentionDays . ' days'));
        $db = Database::connect();

        $removed = 0;
        do {
            $db->table('audit_events')->where('created <', $cutoff)->limit(10000)->delete();
            $affected = $db->affectedRows();
            $removed += $affected;
        } while ($affected === 10000);

        return $removed;
    }

}
