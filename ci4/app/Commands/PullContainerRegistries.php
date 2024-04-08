<?php namespace App\Commands;

use App\Entities\AutoUpdate;
use App\Entities\ContainerImage;
use App\Entities\CronJob;
use App\Entities\Deployment;
use App\Libraries\GoogleCloud\GoogleCloudPubSub;
use App\Libraries\Kubernetes\KubeHelper;
use App\Models\ContainerImageModel;
use App\Models\DeploymentModel;
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

        try {
            $acrProjects = [];
            foreach ($containerImages as $image) {
                switch ($image->registry_provider) {
                    case \ContainerRegistries::ArtifactContainerRegistry:
                        $acrProjects[$image->registry_provider_project] = $image->registry_provider_credentials;
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
                        [$image, $tag] = explode(':', $data['tag']);

                        $this->emitNewTag($image, $tag);
                    }
                }
            }

        } catch (\Exception $e) {
            \DebugTool\Data::debug($e->getMessage());
        }

        $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
        $job->save();
    }

    private function emitNewTag(string $image, string $tag): void {
        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('auto_update_enabled', true)
            ->where('image', $image)
            ->find();

        foreach ($deployments as $deployment) {
            if (preg_match("/{$deployment->auto_update_tag_regex}$/", $tag)) {
                Data::debug('update', $deployment->name, $deployment->namespace, 'with', $image, $tag);

                $autoUpdate = new AutoUpdate();
                $autoUpdate->deployment_id = $deployment->id;
                $autoUpdate->image = $image;
                $autoUpdate->previous_tag = $deployment->version;
                $autoUpdate->next_tag = $tag;
                $autoUpdate->save();

                if (!$deployment->auto_update_require_approval) {
                    $autoUpdate->approve();
                }
            }
        }
    }

}
