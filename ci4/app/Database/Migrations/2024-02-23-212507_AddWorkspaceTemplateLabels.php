<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentPackages;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddWorkspaceTemplateLabels extends Migration {

    public function up() {
        Table::init('labels_workspaces')
            ->column('label_id', ColumnTypes::INT)->addIndex('label_id')
            ->column('workspace_id', ColumnTypes::INT)->addIndex('workspace_id');
        Table::init('deployment_packages_labels')
            ->create()
            ->column('label_id', ColumnTypes::INT)->addIndex('label_id')
            ->column('deployment_package_id', ColumnTypes::INT)->addIndex('deployment_package_id');

        ApiRoute::quick('/deployment-packages/([0-9]+)/labels', DeploymentPackages::class, 'updateLabels/$1', 'put');
    }

    public function down() {

    }

}
