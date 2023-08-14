<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

class AddDeploymentPackages extends Migration {

    public function up() {
        ApiRoute::quick('/deployments/([0-9]+)/deployment-specification', Deployments::class, 'getDeploymentSpecification/$1', 'get');
    }

    public function down() {

    }
}
