<?php namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use DebugTool\Data;

/**
 * Not measured: this is a developer command that drops the newest row from `migrations` and
 * migrates again. Running it from a test would change the schema of the test database underneath
 * the suite, which the drift guard then reports. It is a hand tool, not behaviour we cover.
 *
 * @codeCoverageIgnore
 */
class RerunLastMigration extends BaseCommand {

    public $group           = 'app';
    public $name            = 'app:migrate';
    public $description     = 'Delete last migration line and run migrate';
    protected $arguments    = [

    ];
    protected $options      = [

    ];
    protected $usage = 'app:migrate';

    public function run(array $params) {
        Data::debug(get_class($this), "Add RerunLastMigration");

        Database::connect()->query('delete from migrations order by id desc limit 1');

        $this->call('migrate');
    }

}
