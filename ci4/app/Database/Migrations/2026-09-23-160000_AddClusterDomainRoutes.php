<?php namespace App\Database\Migrations;

use App\Controllers\Domains;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

/**
 * The cluster's Certificates held up against kso's domains, and taking one over - see `ClusterDomains`.
 */
class AddClusterDomainRoutes extends Migration {

    public function up() {
        ApiRoute::quick('domains/in-cluster', Domains::class, 'getInCluster', 'get');
        ApiRoute::quick('domains/import', Domains::class, 'import', 'post');
    }

    public function down() {

    }

}
