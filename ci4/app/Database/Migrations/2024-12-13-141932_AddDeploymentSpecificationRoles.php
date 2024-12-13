<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentSpecifications;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddDeploymentSpecificationRoles extends Migration {

    public function up() {
        ApiRoute::quick('/deployment-specifications/([0-9]+)/role-rules', DeploymentSpecifications::class, 'updateRoleRules/$1', 'put');

        Table::init('deployment_specification_role_rules')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('api_group', ColumnTypes::VARCHAR_511)
            ->column('resource', ColumnTypes::VARCHAR_511)
            ->column('verbs', ColumnTypes::VARCHAR_1023);
    }

    public function down() {

    }

}
