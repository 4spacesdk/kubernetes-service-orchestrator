<?php namespace App\Database\Migrations;

use App\Controllers\Gateways;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * Annotations on a gateway, applied to the Gateway resource (#65). Signed in only - `quick()`
 * leaves `is_public` off.
 */
class AddGatewayAnnotations extends Migration {

    public function up() {
        Table::init('gateway_annotations')
            ->create()
            ->column('gateway_id', ColumnTypes::INT)->addIndex('gateway_id')
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('value', ColumnTypes::TEXT)
            ->timestamps()
            ->softDelete();

        ApiRoute::quick('/gateways/([0-9]+)/gateway-annotations', Gateways::class, 'updateGatewayAnnotations/$1', 'put');
    }

    public function down() {
        Table::init('gateway_annotations')->dropTable();
    }

}
