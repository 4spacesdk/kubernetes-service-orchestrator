<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentSpecifications;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddAnnotations extends Migration {

    public function up() {
        Table::init('deployment_specification_deployment_annotations')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('name', ColumnTypes::VARCHAR_127)
            ->column('value', ColumnTypes::TEXT);
        Table::init('deployment_specification_ingress_annotations')
            ->create()
            ->column('deployment_specification_ingress_id', ColumnTypes::INT)->addIndex('deployment_specification_ingress_id')
            ->column('name', ColumnTypes::VARCHAR_127)
            ->column('value', ColumnTypes::TEXT);

        ApiRoute::quick('/deployment-specifications/([0-9]+)/deployment-annotations', DeploymentSpecifications::class, 'updateDeploymentAnnotations/$1', 'put');
    }

    public function down() {

    }

}
