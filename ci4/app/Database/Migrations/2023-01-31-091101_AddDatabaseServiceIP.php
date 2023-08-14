<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddDatabaseServiceIP extends Migration {

    public function up() {
        Table::init('database_services')
            ->column('azure_host', ColumnTypes::VARCHAR_127);
    }

    public function down() {

    }

}
