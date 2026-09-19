<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentPackages;
use App\Controllers\DeploymentSpecifications;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

/**
 * Routes for duplicating a deployment specification and a deployment package.
 * Signed in only - `quick()` leaves `is_public` off.
 */
class AddDuplicateRoutes extends Migration {

    public function up() {
        ApiRoute::quick('deployment-specifications/([0-9]+)/duplicate', DeploymentSpecifications::class, 'duplicate/$1', 'post');
        ApiRoute::quick('deployment-packages/([0-9]+)/duplicate', DeploymentPackages::class, 'duplicate/$1', 'post');
    }

    public function down() {

    }

}
