<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddDomainNamespace extends Migration {

    public function up() {
        Table::init('domains')
            ->column('certificate_namespace', ColumnTypes::VARCHAR_127);
    }

    public function down() {

    }

}
