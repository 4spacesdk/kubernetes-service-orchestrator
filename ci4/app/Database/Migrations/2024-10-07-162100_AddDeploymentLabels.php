<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use App\Controllers\DeploymentSpecifications;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddDeploymentLabels extends Migration {

    public function up() {
        Table::init('deployments_labels')
            ->create()
            ->column('label_id', ColumnTypes::INT)->addIndex('label_id')
            ->column('deployment_id', ColumnTypes::INT)->addIndex('deployment_id');
        Table::init('deployment_specifications_labels')
            ->create()
            ->column('label_id', ColumnTypes::INT)->addIndex('label_id')
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id');

        ApiRoute::quick('/deployments/([0-9]+)/labels', Deployments::class, 'updateLabels/$1', 'put');
        ApiRoute::quick('/deployment-specifications/([0-9]+)/labels', DeploymentSpecifications::class, 'updateLabels/$1', 'put');
    }

    public function down() {

    }

}
