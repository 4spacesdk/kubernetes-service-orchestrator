<?php namespace App\Commands;

use App\Entities\CronJob;
use AuthExtension\AuthExtension;
use CodeIgniter\CLI\BaseCommand;
use DebugTool\Data;

/**
 * Remove the access tokens, refresh tokens, id tokens and authorization codes that have expired.
 * Every sign-in and every renewal writes rows, and nothing removed one before.
 */
class CleanupOAuthTokens extends BaseCommand {

    public $group = 'app';
    public $name = 'app:cleanup-oauth-tokens';
    public $description = 'Remove expired OAuth tokens and authorization codes';
    protected $arguments = [

    ];
    protected $options = [

    ];

    public function run(array $params) {
        Data::debug(get_class($this), 'CleanupOAuthTokens');

        $job = new CronJob();
        $job->find(\CronJobIds::CleanupOAuthTokens);
        $job->last_run = date('Y-m-d H:i:s');
        $job->save();

        foreach (AuthExtension::deleteExpiredTokens() as $table => $removed) {
            Data::debug('removed', $removed, 'expired rows from', $table);
        }

        $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
        $job->save();
    }

}
