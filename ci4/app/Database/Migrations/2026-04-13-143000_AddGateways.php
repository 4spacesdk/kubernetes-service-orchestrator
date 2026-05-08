<?php namespace App\Database\Migrations;

use App\Controllers\Gateways;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddGateways extends Migration {

    public function up() {
        Table::init('gateways')
            ->create()
            ->column('name', ColumnTypes::VARCHAR_511)
            ->timestamps()
            ->softDelete();

        Table::init('domains')
            ->column('gateway_id', ColumnTypes::INT)->addIndex('gateway_id');

        ApiRoute::addResourceController(Gateways::class);
    }

    public function down() {
    }

}
