<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddDomainHttpsRedirect extends Migration {

    public function up() {
        Table::init('domains')
            ->column('https_redirect', ColumnTypes::BOOL_0);
    }

    public function down() {

    }

}
