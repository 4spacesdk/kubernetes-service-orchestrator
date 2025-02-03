<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddPostCommandContainer extends Migration {

    public function up() {
        Table::init('deployment_specification_post_commands')
            ->column('container', ColumnTypes::VARCHAR_511);
    }

    public function down() {

    }

}
