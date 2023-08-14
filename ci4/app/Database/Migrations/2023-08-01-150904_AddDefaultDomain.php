<?php namespace App\Database\Migrations;

use App\Controllers\Systems;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddDefaultDomain extends Migration {

    public function up() {
        Table::init('systems')
            ->column('default_domain_id', ColumnTypes::INT);

        ApiRoute::quick('/systems/default_domain_id', Systems::class, 'updateDefaultDomain', 'put');
    }

    public function down() {

    }

}
