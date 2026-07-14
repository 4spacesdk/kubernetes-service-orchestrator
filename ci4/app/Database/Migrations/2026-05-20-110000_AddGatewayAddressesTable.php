<?php namespace App\Database\Migrations;

use App\Controllers\Gateways;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddGatewayAddressesTable extends Migration {

    public function up() {
        Table::init('gateway_addresses')
            ->create()
            ->column('gateway_id', ColumnTypes::INT)->addIndex('gateway_id')
            ->column('type', ColumnTypes::VARCHAR_255)
            ->column('value', ColumnTypes::VARCHAR_255)
            ->timestamps()
            ->softDelete();

        ApiRoute::quick('/gateways/([0-9]+)/gateway-addresses', Gateways::class, 'updateGatewayAddresses/$1', 'put');
    }

    public function down() {
        Table::init('gateway_addresses')->dropTable();
    }

}
