<?php namespace App\Database\Migrations;

use App\Controllers\AutoUpdates;
use App\Entities\CronJob;
use App\Entities\Deployment;
use App\Entities\DeploymentPackageDeploymentSpecification;
use App\Models\DeploymentModel;
use App\Models\DeploymentPackageDeploymentSpecificationModel;
use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddContainerRegistries extends Migration {

    public function up() {
        Table::init('container_images')
            ->column('registry_subscribe', ColumnTypes::BOOL_0)
            ->column('registry_provider', ColumnTypes::VARCHAR_127)

            ->column('registry_provider_gcloud_registry_name', ColumnTypes::VARCHAR_511)
            ->column('registry_provider_gcloud_project', ColumnTypes::VARCHAR_511)
            ->column('registry_provider_gcloud_location', ColumnTypes::VARCHAR_511)
            ->column('registry_provider_gcloud_credentials', ColumnTypes::TEXT)

            ->column('registry_provider_azure_registry_name', ColumnTypes::VARCHAR_511)
            ->column('registry_provider_azure_tenant', ColumnTypes::VARCHAR_511)
            ->column('registry_provider_azure_client_id', ColumnTypes::VARCHAR_511)
            ->column('registry_provider_azure_client_secret', ColumnTypes::VARCHAR_511)
        ;

        $job = new CronJob();
        $job->find(\CronJobIds::PullContainerRegistries);
        $job->name = 'app:pull_container_registries';
        $job->schedule = '* * * * *';
        $job->command = 'app:pull_container_registries';
        $job->duplicates = 1;
        $job->save();

        Table::init('deployments')
            ->column('auto_update_enabled', ColumnTypes::BOOL_0)
            ->column('auto_update_tag_regex', ColumnTypes::VARCHAR_511)
            ->column('auto_update_require_approval', ColumnTypes::BOOL_0);
        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->find();
        foreach ($deployments as $deployment) {
            $deployment->auto_update_enabled = true;
            if (strlen($deployment->keel_policy)) { // Can be removed after release
                $deployment->auto_update_tag_regex = str_replace('glob:', '', $deployment->keel_policy);
                $deployment->auto_update_require_approval = !$deployment->keel_auto_update;
            }
            $deployment->save();
        }
        Table::init('deployments')
            ->dropColumn('keel_policy')
            ->dropColumn('keel_auto_update');

        Table::init('deployment_package_deployment_specifications')
            ->column('default_auto_update_enabled', ColumnTypes::BOOL_0)
            ->column('default_auto_update_tag_regex', ColumnTypes::VARCHAR_511)
            ->column('default_auto_update_require_approval', ColumnTypes::BOOL_0);
        /** @var DeploymentPackageDeploymentSpecification $deploymentPackageDeploymentSpecifications */
        $deploymentPackageDeploymentSpecifications = (new DeploymentPackageDeploymentSpecificationModel())
            ->find();
        foreach ($deploymentPackageDeploymentSpecifications as $deploymentPackageDeploymentSpecification) {
            $deploymentPackageDeploymentSpecification->default_auto_update_enabled = true;
            if ($deploymentPackageDeploymentSpecification->default_keel_policy) { // Can be removed after release
                $deploymentPackageDeploymentSpecification->default_auto_update_tag_regex = str_replace('glob:', '', $deploymentPackageDeploymentSpecification->default_keel_policy);
                $deploymentPackageDeploymentSpecification->default_auto_update_require_approval = !$deploymentPackageDeploymentSpecification->default_keel_auto_update;
            }
            $deploymentPackageDeploymentSpecification->save();
        }

        Table::init('deployment_package_deployment_specifications')
            ->dropColumn('default_keel_policy')
            ->dropColumn('default_keel_auto_update');

        Table::init('auto_updates')
            ->create()
            ->column('deployment_id', ColumnTypes::INT)->addIndex('deployment_id')
            ->column('image', ColumnTypes::VARCHAR_4095)
            ->column('previous_tag', ColumnTypes::VARCHAR_511)
            ->column('next_tag', ColumnTypes::VARCHAR_511)
            ->column('is_approved', ColumnTypes::BOOL_0)
            ->column('approved_date', ColumnTypes::VARCHAR_511)
            ->column('log', ColumnTypes::TEXT)
            ->timestamps();

        ApiRoute::addResourceControllerGet(AutoUpdates::class);
        ApiRoute::addResourceControllerDelete(AutoUpdates::class);
        ApiRoute::quick('auto-updates/([0-9]+)/approve', AutoUpdates::class, 'approve/$1', 'put');
        ApiRoute::public('auto-updates/webhooks/azure-container-registry', AutoUpdates::class, 'webhooksAzureContainerRegistry', 'post');

        $job = new CronJob();
        $job->find(\CronJobIds::RunKeelHooks);
        if ($job->exists()) {
            $job->delete();
        }

        Table::init('keel_hook_queue_items')->dropTable();
    }

    public function down() {

    }

}
