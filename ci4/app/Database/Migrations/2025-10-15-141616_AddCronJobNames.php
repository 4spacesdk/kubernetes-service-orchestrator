<?php namespace App\Database\Migrations;

use App\Entities\CronJob;
use CodeIgniter\Database\Migration;

class AddCronJobNames extends Migration {

    public function up() {
        $items = [
            \CronJobIds::PullContainerRegistries => 'app:pull_container_registries',
            \CronJobIds::CheckCertificateExpiry => 'app:check-certificate-expiry',
            \CronJobIds::CheckKNativeMinScaleSchedules => 'app:check-knative-min-scale-schedules',
        ];
        foreach($items as $id => $name) {
            $job = new CronJob();
            $job->find($id);
            $job->name = $name;
            $job->save();
        }
    }

    public function down() {

    }

}
