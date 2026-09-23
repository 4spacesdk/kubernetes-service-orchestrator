<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

/**
 * A deployment's pods, including those a custom resource's operator made - see `WorkloadPods`.
 */
class AddDeploymentPodsRoute extends Migration {

    public function up() {
        ApiRoute::quick('deployments/([0-9]+)/pods', Deployments::class, 'getPods/$1', 'get');
    }

    public function down() {

    }

}
