<?php namespace App\Database\Migrations;

use App\Controllers\Gateways;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

/**
 * The cluster's Gateways held up against kso's, and taking one over - see `ClusterGateways`.
 */
class AddClusterGatewayRoutes extends Migration {

    public function up() {
        ApiRoute::quick('gateways/in-cluster', Gateways::class, 'getInCluster', 'get');
        ApiRoute::quick('gateways/import', Gateways::class, 'import', 'post');
    }

    public function down() {

    }

}
