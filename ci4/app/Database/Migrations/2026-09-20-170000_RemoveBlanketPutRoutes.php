<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * Six PUT routes that land on the generic `put()`.
 *
 * It writes every column of the row and sets the ones the request left out to null, so
 * `PUT /o_auth_clients/{id}` with a single field in it is a request to erase the client's
 * secret, and `PUT /users/{id}` one to erase a password. Nothing in kso calls them - every
 * update goes through PATCH - but they were generated into the API client and published in
 * the OpenAPI document, where they read as something a caller may use.
 *
 * They exist because the route generator makes CRUD routes for every resource controller
 * and these three declare no `put()` of their own to stop it, the way `Webhooks` does.
 * They do now, which is what keeps the rows from coming back; this removes the ones already
 * written.
 *
 * Neither of them does what its shape promises anyway. Measured before removing them:
 * `PUT /o_auth_clients/12345` answers `200 OK` and writes a new row with an empty client id
 * and an empty secret rather than touching the one it names, and `PUT /users/{id}` ends as
 * `Column 'last_name' cannot be null` with the row it was asked about gone from the table.
 */
class RemoveBlanketPutRoutes extends Migration {

    public function up() {
        Database::connect()
            ->table('api_routes')
            ->where('method', 'put')
            ->whereIn('to', [
                'App\Controllers\OAuthClients::put',
                'App\Controllers\OAuthClients::put/$1',
                'App\Controllers\Users::put',
                'App\Controllers\Users::put/$1',
                'App\Controllers\Gateways::put',
                'App\Controllers\Gateways::put/$1',
            ])
            ->delete();
    }

    public function down() {

    }

}
