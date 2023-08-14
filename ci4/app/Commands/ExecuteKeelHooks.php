<?php namespace App\Commands;

use App\Entities\CronJob;
use App\Entities\KeelHookQueueItem;
use App\Models\KeelHookQueueItemModel;
use CodeIgniter\CLI\BaseCommand;
use DebugTool\Data;

class ExecuteKeelHooks extends BaseCommand {

    public $group           = 'app';
    public $name            = 'app:keel_hooks';
    public $description     = 'Execute Keel Hooks';
    protected $arguments    = [

    ];
    protected $options      = [

    ];

    public function run(array $params) {
        Data::debug(get_class($this), "Keel Hooks");

        $job = new CronJob();
        $job->find(\CronJobIds::RunKeelHooks);
        $job->last_run = date('Y-m-d H:i:s');
        $job->save();

        $limit = 1;

        /** @var KeelHookQueueItem $items */
        $items = (new KeelHookQueueItemModel())
            ->where('status', \KeelHookStatusTypes::New)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->find();
        foreach ($items as $item) {
            $item->run();
        }
    }

}
