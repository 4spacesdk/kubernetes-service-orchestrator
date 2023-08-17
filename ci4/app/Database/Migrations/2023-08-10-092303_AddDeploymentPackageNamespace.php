<?php namespace App\Database\Migrations;

use App\Controllers\Workspaces;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddDeploymentPackageNamespace extends Migration {

    public function up() {
        Table::init('deployment_packages')
            ->column('namespace', ColumnTypes::VARCHAR_255);

        ApiRoute::quick('/workspaces/([0-9]+)/deployments', Workspaces::class, 'createDeployment/$1', 'post');
    }

    public function down() {

    }

}
