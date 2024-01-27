<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddDatabaseServiceDriver extends Migration {

    public function up() {
        Table::init('database_services')
            ->column('driver', ColumnTypes::VARCHAR_63);
        Database::connect()
            ->table('database_services')
            ->set('driver', \DatabaseDrivers::MySQL)
            ->update();
    }

    public function down() {
        //
    }

}
