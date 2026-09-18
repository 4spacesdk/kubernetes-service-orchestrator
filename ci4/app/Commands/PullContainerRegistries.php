<?php namespace App\Commands;

use App\Entities\AutoUpdate;
use App\Entities\ContainerRegistry;
use App\Entities\CronJob;
use App\Libraries\GoogleCloud\GcrSubscription;
use App\Libraries\ZMQ\ChangeEvent;
use App\Libraries\ZMQ\Events;
use App\Libraries\ZMQ\ZMQProxy;
use App\Models\ContainerRegistryModel;
use CodeIgniter\CLI\BaseCommand;
use DebugTool\Data;

class PullContainerRegistries extends BaseCommand {

    public $group           = 'app';
    public $name            = 'app:pull_container_registries';
    public $description     = 'Check for container registry events';
    protected $arguments    = [

    ];
    protected $options      = [

    ];

    public function run(array $params) {
        Data::debug(get_class($this), "Check for container registry events");

        $job = new CronJob();
        $job->find(\CronJobIds::PullContainerRegistries);
        $job->last_run = date('Y-m-d H:i:s');
        $job->save();

        /** @var ContainerRegistry $registries */
        $registries = (new ContainerRegistryModel())
            ->where('events_enabled', true)
            ->where('provider', \ContainerRegistries::ArtifactContainerRegistry)
            ->find();
        Data::debug('found', $registries->count(), 'artifact registries with events enabled');

        $hasCreatedAutoUpdate = false;

        try {
            // One pull per project: the topic is per project, whichever repository published.
            $acrProjects = [];
            foreach ($registries as $registry) {
                $acrProjects[$registry->gcloud_project] = (string) $registry->gcloud_credentials;
            }

            if (count($acrProjects)) {
                Data::debug('found', count($acrProjects), 'ACR projects');
                for ($i = 0; $i < 5; $i++) {
                    $hasCreatedAutoUpdate = $this->runAcrProjects($acrProjects);
                    sleep(2);
                }
            }

        } catch (\Exception $e) {
            \DebugTool\Data::debug($e->getMessage());
        }

        // Not measured: a PUSH socket with nobody listening blocks on send, and nothing
        // listens in a test container. Reaching this would hang the build rather than
        // assert anything.
        // @codeCoverageIgnoreStart
        if ($hasCreatedAutoUpdate) {
            ZMQProxy::getInstance()->send(
                Events::AutoUpdate_Created(),
                (new ChangeEvent(null, []))->toArray()
            );
        }
        // @codeCoverageIgnoreEnd

        $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
        $job->save();
    }

    /**
     * Protected rather than private so a test can reach it: `run()` around it sleeps for
     * ten seconds and writes cron job rows, and this is the part that decides anything.
     *
     * @param array<string, string> $acrProjects project => service account key
     */
    protected function runAcrProjects($acrProjects): bool {
        $hasCreatedAutoUpdate = false;
        $pubSub = service('integrations')->pubSub();

        foreach ($acrProjects as $project => $credentials) {
            $messages = $pubSub->pull(
                $project,
                $credentials,
                GcrSubscription::TOPIC,
                GcrSubscription::name()
            );
            Data::debug(count($messages), 'for', $project);

            foreach ($messages as $message) {
                Data::debug($message);
                $data = json_decode($message, true);
                switch ($data['action']) {
                    case 'DELETE':
                        Data::debug('tag deleted, ignore');
                        break;
                    case 'INSERT':
                        Data::debug('tag added, continue');
                        [$image, $tag] = explode(':', $data['tag']);
                        $this->emitNewTag($image, $tag);
                        $hasCreatedAutoUpdate = true;
                        break;
                }
            }
        }
        return $hasCreatedAutoUpdate;
    }

    private function emitNewTag(string $image, string $tag): void {
        AutoUpdate::CheckForUpdates($image, $tag);
    }

}
