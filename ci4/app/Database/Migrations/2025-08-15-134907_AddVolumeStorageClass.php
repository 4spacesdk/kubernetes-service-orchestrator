<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddVolumeStorageClass extends Migration {

    public function up() {
        Table::init('deployment_volumes')
            ->column('storage_class', ColumnTypes::VARCHAR_511);
        Table::init('deployment_specification_volumes')
            ->column('storage_class', ColumnTypes::VARCHAR_511);
    }

    public function down() {

    }

}
