<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentPackages;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddDeploymentPackageEnvironmentVariables extends Migration {

    public function up() {
        Table::init('deployment_package_environment_variables')
            ->create()
            ->column('deployment_package_id', ColumnTypes::INT)->addIndex('deployment_package_id')
            ->column('name', ColumnTypes::VARCHAR_127)
            ->column('value', ColumnTypes::TEXT);
        ApiRoute::quick('/deployment-packages/([0-9]+)/environment-variables', DeploymentPackages::class, 'updateEnvironmentVariables/$1', 'put');
        ApiRoute::quick('/deployment-packages/([0-9]+)/environment-variables/copy-to-deployments', DeploymentPackages::class, 'copyEnvironmentVariableToDeployments/$1', 'put');
    }

    public function down() {

    }

}
