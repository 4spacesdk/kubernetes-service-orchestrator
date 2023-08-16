<?php namespace App\Database\Migrations;

use App\Controllers\OAuthClients;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

class AddOAuthClients extends Migration {

    public function up() {
        ApiRoute::addResourceController(OAuthClients::class);
        ApiRoute::quick('o_auth_clients/(.*)', OAuthClients::class, 'get/$1', 'get');
        ApiRoute::quick('o_auth_clients/(.*)', OAuthClients::class, 'patch/$1', 'patch');
        ApiRoute::quick('o_auth_clients/(.*)', OAuthClients::class, 'delete/$1', 'delete');
    }

    public function down() {

    }

}
