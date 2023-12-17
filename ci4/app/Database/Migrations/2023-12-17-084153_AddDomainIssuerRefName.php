<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddDomainIssuerRefName extends Migration {

    public function up() {
        Table::init('domains')
            ->column('issuer_ref_name', ColumnTypes::VARCHAR_511);
    }

    public function down() {

    }

}
