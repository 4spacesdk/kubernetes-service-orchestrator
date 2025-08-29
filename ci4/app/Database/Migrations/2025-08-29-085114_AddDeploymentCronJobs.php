<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddDeploymentCronJobs extends Migration {

    public function up() {
        Table::init('deployment_cron_jobs')
            ->create()
            ->column('deployment_id', ColumnTypes::INT)->addIndex('deployment_id')
            ->column('k8s_cron_job_id', ColumnTypes::INT)->addIndex('k8s_cron_job_id')
            ->column('position', ColumnTypes::INT);

        ApiRoute::quick('/deployment/([0-9]+)/cron-jobs', Deployments::class, 'updateCronJobs/$1', 'put');
    }

    public function down() {

    }

}
