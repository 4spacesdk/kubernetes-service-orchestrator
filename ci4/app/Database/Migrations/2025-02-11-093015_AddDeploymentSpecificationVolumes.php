<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentSpecifications;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddDeploymentSpecificationVolumes extends Migration {

    public function up() {
        Table::init('deployment_specification_volumes')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('mount_path', ColumnTypes::VARCHAR_511)
            ->column('sub_path', ColumnTypes::VARCHAR_511)
            ->column('capacity', ColumnTypes::INT)
            ->column('volume_mode', ColumnTypes::VARCHAR_63)
            ->column('reclaim_policy', ColumnTypes::VARCHAR_63)
            ->column('nfs_server', ColumnTypes::VARCHAR_511)
            ->column('nfs_path', ColumnTypes::VARCHAR_511)
            ->timestamps()
            ->softDelete();

        ApiRoute::quick('/deployment-specifications/([0-9]+)/volumes', DeploymentSpecifications::class, 'updateVolumes/$1', 'put');

        Table::init('init_containers')
            ->column('include_volumes', ColumnTypes::BOOL_0);
    }

    public function down() {

    }

}
