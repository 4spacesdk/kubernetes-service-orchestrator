<?php namespace App\Database\Migrations;

use App\Controllers\Gateways;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class UpdateGateways extends Migration {

    public function up() {
        Table::init('gateways')
            ->column('gateway_class_name', ColumnTypes::VARCHAR_255)
            ->column('namespace', ColumnTypes::VARCHAR_255);

        ApiRoute::quick('/gateways/([0-9]+)/preview', Gateways::class, 'getPreview/$1', 'get');
        ApiRoute::quick('/gateways/([0-9]+)/deploy', Gateways::class, 'deploy/$1', 'put');
        ApiRoute::quick('/gateways/([0-9]+)/terminate', Gateways::class, 'terminate/$1', 'put');
        ApiRoute::quick('/gateways/([0-9]+)/status', Gateways::class, 'getStatus/$1', 'get');
        ApiRoute::quick('/gateways/([0-9]+)/kubernetes-events', Gateways::class, 'getKubernetesEvents/$1', 'get');
        ApiRoute::quick('/gateways/([0-9]+)/kubernetes-status', Gateways::class, 'getKubernetesStatus/$1', 'get');
    }

    public function down() {
    }

}
