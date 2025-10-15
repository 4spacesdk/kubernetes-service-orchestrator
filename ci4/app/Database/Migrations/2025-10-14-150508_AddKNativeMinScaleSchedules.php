<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use App\Controllers\KNativeMinScaleSchedules;
use App\Entities\CronJob;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddKNativeMinScaleSchedules extends Migration {

    public function up() {
        Table::init('knative_min_scale_schedules')
            ->create()
            ->column('min_scale', ColumnTypes::INT)
            ->column('cron_expression', ColumnTypes::VARCHAR_27)
            ->column('timezone', ColumnTypes::VARCHAR_27)
            ->column('description', ColumnTypes::VARCHAR_27)
            ->column('priority', ColumnTypes::INT)
            ->timestamps();

        Table::init('deployments_knative_min_scale_schedules')
            ->create()
            ->column('deployment_id', ColumnTypes::INT)->addIndex('deployment_id')
            ->column('knative_min_scale_schedule_id', ColumnTypes::INT)->addIndex('knative_min_scale_schedule_id');
        Table::init('deployment_package_ds_knative_min_scale_schedules')
            ->create()
            ->column('deployment_package_deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_package_deployment_specification_id')
            ->column('knative_min_scale_schedule_id', ColumnTypes::INT)->addIndex('knative_min_scale_schedule_id');

        Table::init('deployments')
            ->column('knative_scheduled_minscale_is_enabled', ColumnTypes::BOOL_0);
        Table::init('deployment_package_deployment_specifications')
            ->column('default_knative_scheduled_minscale_is_enabled', ColumnTypes::BOOL_0);

        ApiRoute::addResourceControllerGet(KNativeMinScaleSchedules::class);
        ApiRoute::addResourceControllerPost(KNativeMinScaleSchedules::class);
        ApiRoute::addResourceControllerPatch(KNativeMinScaleSchedules::class);
        ApiRoute::addResourceControllerDelete(KNativeMinScaleSchedules::class);

        ApiRoute::quick('/deployments/([0-9]+)/knative-min-scale-schedules', Deployments::class, 'updateKNativeMinScaleSchedules/$1', 'put');

        $job = new CronJob();
        $job->find(\CronJobIds::CheckKNativeMinScaleSchedules);
        $job->schedule = '* * * * *';
        $job->command = 'app:check-knative-min-scale-schedules';
        $job->duplicates = 1;
        $job->save();
    }

    public function down() {

    }

}
