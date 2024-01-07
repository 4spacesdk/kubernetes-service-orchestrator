<?php namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use DebugTool\Data;
use OrmExtension\ModelParser\ModelParser;
use RestExtension\ApiParser\ApiParser;

class SyncModelsAndApi extends BaseCommand {

    public $group           = 'dev';
    public $name            = 'dev:sync_models_and_api';
    public $description     = '';
    protected $arguments    = [

    ];
    protected $options      = [

    ];

    public function run(array $params) {
        Data::debug(get_class($this), "SyncModelsAndApi");
        $this->syncModels();
        $this->syncApi();
    }

    private function syncModels() {
        $parser = ModelParser::run();
        $parser->generateTypeScript();

        $from = WRITEPATH . 'tmp/models';
        $from = str_replace(' ', '\ ', $from);
        $to = ROOTPATH . '../vue/src/core/services/Deploy/models';
        $to = str_replace(' ', '\ ', $to);

        // Clear Definition folder
        shell_exec("rm -rf {$to}/definitions");
        shell_exec("mv {$from}/definitions {$to}");

        // Overwrite index file
        shell_exec("rm -rf {$to}/index.ts");
        shell_exec("mv {$from}/index.ts {$to}");

        // Add new models to model list
        shell_exec("cp -n {$from}/*.ts {$to}/");

        // Cleanup
        shell_exec("rm -rf {$from}");
    }

    private function syncApi() {
        $parser = ApiParser::run();
        $parser->generateVue(false);

        $from = WRITEPATH . 'tmp/Api.ts';
        $from = str_replace(' ', '\ ', $from);
        $to = ROOTPATH . '../vue/src/core/services/Deploy';
        $to = str_replace(' ', '\ ', $to);

        // Overwrite Api.ts
        shell_exec("rm -rf {$to}/Api.ts");
        shell_exec("mv {$from} {$to}/Api.ts");
    }

}
