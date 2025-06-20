<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddMigrationJobInitContainers extends Migration {

    public function up() {
        Table::init('deployment_specification_init_containers')
            ->column('include_in_migration_job', ColumnTypes::BOOL_0)->addIndex('include_in_migration_job');
    }

    public function down() {

    }

}
