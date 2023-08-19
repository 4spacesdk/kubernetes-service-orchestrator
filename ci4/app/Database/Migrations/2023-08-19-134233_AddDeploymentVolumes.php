<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddDeploymentVolumes extends Migration {

    public function up() {
        Table::init('deployment_specifications')
            ->column('enable_volumes', ColumnTypes::BOOL_0);

        Table::init('deployment_volumes')
            ->create()
            ->column('deployment_id', ColumnTypes::INT)->addIndex('deployment_id')
            ->column('mount_path', ColumnTypes::VARCHAR_511)
            ->column('sub_path', ColumnTypes::VARCHAR_511)
            ->column('capacity', ColumnTypes::INT)
            ->column('volume_mode', ColumnTypes::VARCHAR_63)
            ->column('reclaim_policy', ColumnTypes::VARCHAR_63)
            ->column('nfs_server', ColumnTypes::VARCHAR_511)
            ->column('nfs_path', ColumnTypes::VARCHAR_511)
            ->timestamps()
            ->softDelete();

        ApiRoute::quick('/deployments/([0-9]+)/volumes', Deployments::class, 'updateDeploymentVolumes/$1', 'put');
    }

    public function down() {

    }

}
