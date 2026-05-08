<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddGatewayApiSupport extends Migration {

    public function up() {
        Table::init('systems')
            ->column('is_network_gateway_api_supported', ColumnTypes::BOOL_0);
    }

    public function down() {
        Table::init('systems')
            ->dropColumn('is_network_gateway_api_supported');
    }

}
