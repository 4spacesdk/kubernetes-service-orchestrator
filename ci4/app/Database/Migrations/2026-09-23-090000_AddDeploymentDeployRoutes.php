<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

/**
 * Deploy and terminate one deployment, every step of it - what a workspace does for each of
 * its own, for the deployments list and page.
 */
class AddDeploymentDeployRoutes extends Migration {

    public function up() {
        ApiRoute::quick('deployments/([0-9]+)/deploy', Deployments::class, 'deploy/$1', 'put');
        ApiRoute::quick('deployments/([0-9]+)/terminate', Deployments::class, 'terminate/$1', 'put');
    }

    public function down() {

    }

}
