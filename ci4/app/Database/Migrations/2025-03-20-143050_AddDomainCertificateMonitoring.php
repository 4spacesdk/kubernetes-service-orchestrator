<?php namespace App\Database\Migrations;

use App\Entities\CronJob;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddDomainCertificateMonitoring extends Migration {

    public function up() {
        Table::init('domains')
            ->column('has_certificate_monitoring', ColumnTypes::BOOL_0)
            ->column('certificate_monitoring_days_before_expiry', ColumnTypes::INT);

        $job = new CronJob();
        $job->find(\CronJobIds::CleanupGoogleContainerRegistry);
        if ($job->exists()) {
            $job->delete();
        }

        $job = new CronJob();
        $job->find(\CronJobIds::CheckCertificateExpiry);
        $job->schedule = '00 07 * * *';
        $job->command = 'app:check-certificate-expiry';
        $job->duplicates = 1;
        $job->save();
    }

    public function down() {

    }

}
