<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddPostCommandsAllPods extends Migration {

    public function up() {
        Table::init('deployment_specification_post_commands')
            ->column('all_pods', ColumnTypes::BOOL_0);
    }

    public function down() {

    }

}
