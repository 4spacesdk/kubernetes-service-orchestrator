<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;
use RestExtension\Models\ApiRouteModel;

class DeleteOldKubernetesRoutes extends Migration {

    public function up() {
        /** @var ApiRoute $apiRoutes */
        $apiRoutes = (new ApiRouteModel())
            ->whereIn('from', [
                'kubernetes/namespaces/(.*)/pods/(.*)/exec',
                'kubernetes/namespaces/(.*)/pods/(.*)/logs/watch',
                'kubernetes/namespaces/(.*)/pods/(.*)/logs',
            ])
            ->find();
        if ($apiRoutes->exists()) {
            $apiRoutes->deleteAll();
        }
    }

    public function down() {

    }

}
