<?php namespace App\Database\Migrations;

use App\Controllers\DatabaseServices;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

class AddDatabaseServicesTestConnectionRoute extends Migration {

    public function up() {
        ApiRoute::quick('database-services/([0-9]+)/test-connection', DatabaseServices::class, 'testConnection/$1', 'get');
    }

    public function down() {

    }

}
