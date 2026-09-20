<?php namespace App\Commands;

use App\Entities\AutoUpdate;
use App\Entities\ContainerRegistry;
use App\Entities\CronJob;
use App\Libraries\ContainerRegistries\ImageReference;
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
                    // Collected across the five pulls, not overwritten: a tag found by the
                    // first pull and nothing by the four after it is still a tag found.
                    // The call goes first so it is made on every pass - the other order
                    // would stop pulling once something had been found.
                    $hasCreatedAutoUpdate = $this->runAcrProjects($acrProjects) || $hasCreatedAutoUpdate;
                    sleep(2);
                }
            }

        } catch (\Throwable $e) {
            // Throwable rather than Exception: a TypeError inside the pull loop is not an
            // Exception, and letting it past here skips last_log and the closing save() -
            // leaving the job page showing the previous run's log as if nothing happened.
            \DebugTool\Data::debug($e->getMessage());
        }

        if ($hasCreatedAutoUpdate) {
            $this->announceAutoUpdates();
        }

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
                        [$image, $tag] = ImageReference::split($data['tag'] ?? null);
                        if ($tag === null) {
                            Data::debug('no tag in', $data['tag'] ?? 'nothing', ', ignored');
                            break;
                        }
                        $this->emitNewTag($image, $tag);
                        $hasCreatedAutoUpdate = true;
                        break;
                }
            }
        }
        return $hasCreatedAutoUpdate;
    }

    /**
     * Tells any open UI that an update is waiting. Without it the update only appears when
     * somebody reloads the page.
     *
     * Protected rather than inline so a test can see that it was reached: the run's own
     * log says nothing about it, and the socket records nothing either.
     */
    protected function announceAutoUpdates(): void {
        ZMQProxy::getInstance()->send(
            Events::AutoUpdate_Created(),
            (new ChangeEvent(null, []))->toArray()
        );
    }

    private function emitNewTag(string $image, string $tag): void {
        AutoUpdate::CheckForUpdates($image, $tag);
    }

}
