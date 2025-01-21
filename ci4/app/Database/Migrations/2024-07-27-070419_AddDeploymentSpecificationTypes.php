<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddDeploymentSpecificationTypes extends Migration {

    public function up() {
        Table::init('deployment_specifications')
            ->column('type', ColumnTypes::VARCHAR_63)
            ->column('custom_resource', ColumnTypes::TEXT);
        Table::init('deployments')
            ->column('custom_resource', ColumnTypes::TEXT);
    }

    public function down() {

    }

}
