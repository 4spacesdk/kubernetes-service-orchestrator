<?php namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use DebugTool\Data;

class ExampleCommand extends BaseCommand {

    public $group           = 'app';
    public $name            = 'app:example';
    public $description     = '';
    protected $arguments    = [

    ];
    protected $options      = [

    ];

    public function run(array $params) {
        Data::debug(get_class($this), "Example Command");
    }

}
