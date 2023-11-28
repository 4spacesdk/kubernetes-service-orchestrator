<?php namespace App\Database\Migrations;

use App\Controllers\Kubernetes;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

class AddKubernetesNodeInfoRoute extends Migration {

    public function up() {
        ApiRoute::quick('/kubernetes/node-info', Kubernetes::class, 'nodeInfo', 'get');
    }

    public function down() {

    }

}
