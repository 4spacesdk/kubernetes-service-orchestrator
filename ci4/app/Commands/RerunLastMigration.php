<?php namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use DebugTool\Data;
use SharedTools\ZMQ\ChangeEvent;
use SharedTools\ZMQ\Events;
use SharedTools\ZMQ\ZMQProxy;

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
