<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddSecurityContextFSGroup extends Migration {

    public function up() {
        Table::init('container_images')
            ->column('security_context_fs_group', ColumnTypes::VARCHAR_127);
    }

    public function down() {

    }

}
