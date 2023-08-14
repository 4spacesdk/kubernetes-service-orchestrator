<?php namespace App\Database\Migrations;

use App\Controllers\Kubernetes;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

class AddKubernetesExecRoute extends Migration {

    public function up() {
        ApiRoute::quick('kubernetes/namespaces/(.*)/pods/(.*)/exec', Kubernetes::class, 'exec/$1/$2', 'put');
    }

    public function down() {

    }

}
