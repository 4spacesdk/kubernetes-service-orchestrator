<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;
use DeploymentAnnotationLevels;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddDeploymentAnnotationLevels extends Migration {

    public function up() {
        Table::init('deployment_specification_deployment_annotations')
            ->column('level', ColumnTypes::VARCHAR_63);
        Database::connect()
            ->table('deployment_specification_deployment_annotations')
            ->set('level', DeploymentAnnotationLevels::Deployment)
            ->update();
    }

    public function down() {

    }

}
