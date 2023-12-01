<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentSpecifications;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddServiceAnnotations extends Migration {

    public function up() {
        Table::init('deployment_specification_service_annotations')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('name', ColumnTypes::VARCHAR_127)
            ->column('value', ColumnTypes::TEXT);

        ApiRoute::quick('/deployment-specifications/([0-9]+)/service-annotations', DeploymentSpecifications::class, 'updateServiceAnnotations/$1', 'put');
    }

    public function down() {

    }

}
