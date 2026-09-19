<?php namespace App\Database\Migrations;

use App\Controllers\ContainerImages;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

/**
 * FEAT-1. Signed in only - `quick()` leaves `is_public` off.
 */
class AddContainerImageTagsRoute extends Migration {

    public function up() {
        ApiRoute::quick('container-images/([0-9]+)/tags', ContainerImages::class, 'getTags/$1', 'get');
    }

    public function down() {

    }

}
