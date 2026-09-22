<?php namespace App\Commands;

use App\Entities\CronJob;
use CodeIgniter\CLI\BaseCommand;
use Config\Database;
use DebugTool\Data;

/**
 * Keep `sign_in_attempts` to a window.
 *
 * Every attempt at the sign-in form writes a row, failed ones included, so someone trying
 * made-up usernames can grow the table as fast as the server answers. 90 days covers
 * looking back at an incident; the refusal after too many failures only reads the last
 * quarter of an hour.
 */
class CleanupSignInAttempts extends BaseCommand {

    public $group = 'app';
    public $name = 'app:cleanup-sign-in-attempts';
    public $description = 'Remove sign-in attempts older than the retention window';
    protected $arguments = [

    ];
    protected $options = [

    ];

    /** How long a row is kept, in days. */
    public const int RetentionDays = 90;

    public function run(array $params) {
        Data::debug(get_class($this), 'CleanupSignInAttempts');

        $job = new CronJob();
        $job->find(\CronJobIds::CleanupSignInAttempts);
        $job->last_run = date('Y-m-d H:i:s');
        $job->save();

        $removed = $this->removeAttemptsOlderThanTheWindow();
        Data::debug('removed', $removed, 'sign-in attempts older than', self::RetentionDays, 'days');

        $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
        $job->save();
    }

    /**
     * Straight through the builder rather than row by row through the ORM: the first run on an
     * old installation has every attempt ever made to get through, and loading them into
     * entities to delete them one at a time is how a cleanup job becomes slow.
     *
     * @return int how many rows went
     */
    protected function removeAttemptsOlderThanTheWindow(): int {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . self::RetentionDays . ' days'));

        $db = Database::connect();
        $db->table('sign_in_attempts')->where('created <', $cutoff)->delete();

        return $db->affectedRows();
    }

}
