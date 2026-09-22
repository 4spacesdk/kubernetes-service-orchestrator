<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

/**
 * What a deployment's pods are using right now, from metrics.k8s.io - see `DeploymentMetrics`.
 *
 * kso needs `get` and `list` on `pods` in `metrics.k8s.io` for it. The chart's ClusterRole has
 * them; an installation that grants kso's rights itself has to add them, or the dialog says the
 * metrics could not be read.
 */
class AddDeploymentMetricsRoute extends Migration {

    public function up() {
        ApiRoute::quick('deployments/([0-9]+)/metrics', Deployments::class, 'getMetrics/$1', 'get');
    }

    public function down() {

    }

}
