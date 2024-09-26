<?php namespace App\Commands;

use App\Entities\AutoUpdate;
use App\Entities\ContainerImage;
use App\Entities\CronJob;
use App\Libraries\GoogleCloud\GoogleCloudPubSub;
use App\Libraries\Kubernetes\KubeHelper;
use App\Libraries\ZMQ\ChangeEvent;
use App\Libraries\ZMQ\Events;
use App\Libraries\ZMQ\ZMQProxy;
use App\Models\ContainerImageModel;
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

        /** @var ContainerImage $containerImages */
        $containerImages = (new ContainerImageModel())
            ->where('registry_subscribe', true)
            ->find();
        Data::debug('found', $containerImages->count(), 'container images with registry subscribe enabled');

        $hasCreatedAutoUpdate = false;

        try {
            $acrProjects = [];
            foreach ($containerImages as $image) {
                switch ($image->registry_provider) {
                    case \ContainerRegistries::ArtifactContainerRegistry:
                        $acrProjects[$image->registry_provider_gcloud_project] = $image->registry_provider_gcloud_credentials;
                        break;
                }
            }

            if (count($acrProjects)) {
                Data::debug('found', count($acrProjects), 'ACR projects');

                foreach ($acrProjects as $project => $credentials) {
                    $googleCloudPubSub = new GoogleCloudPubSub($project, $credentials);
                    $messages = $googleCloudPubSub->pull(
                        'gcr',
                        'kso-' . KubeHelper::GetMyHostname() . '.' . KubeHelper::GetMyNamespace()
                    );
                    Data::debug(count($messages), 'for', $project);

                    foreach ($messages as $message) {
                        Data::debug($message->data());
                        $data = json_decode($message->data(), true);
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
            }

        } catch (\Exception $e) {
            \DebugTool\Data::debug($e->getMessage());
        }

        if ($hasCreatedAutoUpdate) {
            ZMQProxy::getInstance()->send(
                Events::AutoUpdate_Created(),
                (new ChangeEvent(null, []))->toArray()
            );
        }

        $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
        $job->save();
    }

    private function emitNewTag(string $image, string $tag): void {
        AutoUpdate::CheckForUpdates($image, $tag);
    }

}
