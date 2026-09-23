<?php namespace App\Database\Migrations;

use App\Controllers\Kubernetes;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

/**
 * The window behind the status bar's dot - see `ClusterHealth`.
 *
 * kso needs `get` and `list` on `nodes` in `metrics.k8s.io` for the nodes' usage. An installation
 * without it, or without metrics-server, gets the window without the usage.
 */
class AddClusterHealthRoute extends Migration {

    public function up() {
        ApiRoute::quick('kubernetes/cluster-health', Kubernetes::class, 'clusterHealth', 'get');
    }

    public function down() {

    }

}
