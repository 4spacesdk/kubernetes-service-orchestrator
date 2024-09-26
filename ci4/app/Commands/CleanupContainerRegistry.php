<?php namespace App\Commands;

use App\Entities\ContainerImage;
use App\Entities\CronJob;
use App\Libraries\ContainerRegistries\GoogleCloudArtifactRegistry;
use App\Models\ContainerImageModel;
use CodeIgniter\CLI\BaseCommand;
use DebugTool\Data;

class CleanupContainerRegistry extends BaseCommand {

    public $group = 'app';
    public $name = 'app:cleanup_gcr';
    public $description = 'Delete old untagged images from Google Artifact Registry';
    protected $arguments = [

    ];
    protected $options = [

    ];

    public function run(array $params) {
        Data::debug(get_class($this), "Cleanup old untagged images");

        $job = new CronJob();
        $job->find(\CronJobIds::CleanupGoogleContainerRegistry);
        $job->last_run = date('Y-m-d H:i:s');
        $job->save();

        /** @var ContainerImage $containerImages */
        $containerImages = (new ContainerImageModel())
            ->find();

        try {
            foreach ($containerImages as $image) {
                switch ($image->registry_provider) {
                    case \ContainerRegistries::ArtifactContainerRegistry:
                        $registry = new GoogleCloudArtifactRegistry($image);
                        $versions = $registry->getVersions();
                        foreach ($versions as $version) {
                            $registry->deleteVersions($version);
                        }
                        break;
                    case \ContainerRegistries::AzureContainerRegistry:
                        // Azure will clean up automatically
                        break;
                }
            }
        } catch (\Exception $e) {
            \DebugTool\Data::debug($e->getMessage());
        }

        $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
        $job->save();
    }

}
