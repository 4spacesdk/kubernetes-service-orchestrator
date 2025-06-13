<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddContainerImageSecurityContext extends Migration {

    public function up() {
        Table::init('container_images')
            ->column('security_context_run_as_user', ColumnTypes::VARCHAR_127)
            ->column('security_context_run_as_group', ColumnTypes::VARCHAR_127)
            ->column('security_context_allow_privilege_escalation', ColumnTypes::BOOL_0)
            ->column('security_context_read_only_root_filesystem', ColumnTypes::BOOL_0);
    }

    public function down() {

    }

}
