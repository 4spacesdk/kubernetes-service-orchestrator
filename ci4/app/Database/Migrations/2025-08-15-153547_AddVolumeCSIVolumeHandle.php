<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddVolumeCSIVolumeHandle extends Migration {

    public function up() {
        Table::init('deployment_volumes')
            ->column('type', ColumnTypes::VARCHAR_511)
            ->column('csi_driver', ColumnTypes::VARCHAR_511)
            ->column('csi_volume_handle', ColumnTypes::VARCHAR_511);
        Table::init('deployment_specification_volumes')
            ->column('type', ColumnTypes::VARCHAR_511)
            ->column('csi_driver', ColumnTypes::VARCHAR_511)
            ->column('csi_volume_handle', ColumnTypes::VARCHAR_511);
        Database::connect()
            ->table('deployment_volumes')
            ->set('type', 'nfs')
            ->update();
        Database::connect()
            ->table('deployment_specification_volumes')
            ->set('type', 'nfs')
            ->update();
    }

    public function down() {

    }

}
