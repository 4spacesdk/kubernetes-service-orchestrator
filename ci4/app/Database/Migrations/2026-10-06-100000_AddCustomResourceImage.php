<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentSpecifications;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

/**
 * A custom resource's image as the specification's container image - see
 * `DeploymentSpecification::linkCustomResourceImage()`.
 */
class AddCustomResourceImage extends Migration {

    public function up() {
        ApiRoute::quick('/deployment-specifications/([0-9]+)/custom-resource-image', DeploymentSpecifications::class, 'linkCustomResourceImage/$1', 'put');
    }

    public function down() {

    }

}
