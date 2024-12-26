<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentSpecifications;
use App\Controllers\K8sCronJobs;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddDeploymentSpecificationCronJobs extends Migration {

    public function up() {
        Table::init('k8s_cron_jobs')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')

            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('schedule', ColumnTypes::VARCHAR_27)
            ->column('concurrency_policy', ColumnTypes::VARCHAR_27)
            ->column('restart_policy', ColumnTypes::VARCHAR_27)
            ->column('successful_jobs_history_limit', ColumnTypes::INT)
            ->column('failed_jobs_history_limit', ColumnTypes::INT)

            ->column('cpu_limit', ColumnTypes::INT)
            ->column('cpu_request', ColumnTypes::INT)
            ->column('memory_limit', ColumnTypes::INT)
            ->column('memory_request', ColumnTypes::INT)

            ->column('container_image_id', ColumnTypes::INT)->addIndex('container_image_id')
            ->column('command', ColumnTypes::TEXT)
            ->column('args', ColumnTypes::TEXT)
            ->column('container_image_tag_policy', ColumnTypes::VARCHAR_63)
            ->column('container_image_tag_value', ColumnTypes::VARCHAR_127)
            ->column('container_image_pull_policy', ColumnTypes::VARCHAR_63)

            ->column('include_deployment_environment_variables', ColumnTypes::BOOL_0)
            ;

        Table::init('deployment_specification_cron_jobs')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('k8s_cron_job_id', ColumnTypes::INT)->addIndex('k8s_cron_job_id')
            ->column('position', ColumnTypes::INT);

        ApiRoute::addResourceControllerGet(K8sCronJobs::class);
        ApiRoute::addResourceControllerPatch(K8sCronJobs::class);
        ApiRoute::addResourceControllerPost(K8sCronJobs::class);
        ApiRoute::addResourceControllerDelete(K8sCronJobs::class);
        ApiRoute::quick('/deployment-specifications/([0-9]+)/cron-jobs', DeploymentSpecifications::class, 'updateCronJobs/$1', 'put');

        Table::init('container_images')
            ->column('default_tag', ColumnTypes::VARCHAR_511);

        Table::init('deployment_specifications')
            ->dropColumn('cronjob_url');
    }

    public function down() {

    }

}
