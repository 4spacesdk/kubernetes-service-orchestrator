<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

/**
 * A cron job can mount the deployment's volumes, as an init container can. On for the cron jobs
 * that already take the deployment's environment: they run the app's own commands, and those read
 * the files the app wrote - an uploaded certificate, say.
 */
class AddCronJobVolumes extends Migration {

    public function up() {
        Table::init('k8s_cron_jobs')
            ->column('include_volumes', ColumnTypes::BOOL_0);

        $this->db->query('UPDATE k8s_cron_jobs SET include_volumes = 1 WHERE include_deployment_environment_variables = 1');
    }

    public function down() {

    }

}
