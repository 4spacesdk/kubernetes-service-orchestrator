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
        Database::connect()
            ->table('deployment_specifications')
            ->where('type', '')
            ->set('type', \DeploymentSpecificationTypes::Deployment)
            ->update();

        ApiRoute::quick('/deployments/([0-9]+)/custom-resource', Deployments::class, 'updateCustomResource/$1', 'put');
    }

    public function down() {

    }

}
