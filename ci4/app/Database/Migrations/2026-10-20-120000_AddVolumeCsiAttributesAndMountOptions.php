<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

/**
 * A static CSI volume could only say which driver and which handle. Most drivers want more to
 * mount it - csi-driver-nfs the server and the share - and a volume without them was taken by its
 * claim all the same and never mounted. `csi_volume_attributes` is one `key=value` a line, and
 * `mount_options` the options as fstab has them, separated by commas.
 */
class AddVolumeCsiAttributesAndMountOptions extends Migration {

    public function up() {
        foreach (['deployment_volumes', 'deployment_specification_volumes'] as $table) {
            Table::init($table)
                ->column('csi_volume_attributes', ColumnTypes::VARCHAR_2047)
                ->column('mount_options', ColumnTypes::VARCHAR_511);
        }
    }

    public function down() {

    }

}
