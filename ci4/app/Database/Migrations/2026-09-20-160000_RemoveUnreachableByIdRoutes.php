<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * Four by-id routes that cannot do what their shape promises.
 *
 * `environments/{id}` points at `Environments::get()`, which takes no parameters. PHP
 * accepts the extra argument without a word, so asking for one environment answered with
 * all of them - under `resources`, which is not the field a by-id caller reads. There is
 * nothing to address either: an environment is a name from an enum, not a row.
 *
 * The three `o_auth_clients/([0-9]+)` routes are duplicates. That resource is keyed by
 * `client_id`, a string, and the table carries a `(.*)` route to the same method for each
 * of the three verbs - so the numeric one matches a strict subset of what the other already
 * matches, and hands it to the same place.
 *
 * Both were written by the route generator, which makes a by-id route for everything.
 */
class RemoveUnreachableByIdRoutes extends Migration {

    public function up() {
        $db = Database::connect();

        $db->table('api_routes')
            ->where('from', 'environments/([0-9]+)')
            ->delete();

        $db->table('api_routes')
            ->where('from', 'o_auth_clients/([0-9]+)')
            ->whereIn('method', ['get', 'patch', 'delete'])
            ->delete();
    }

    public function down() {

    }

}
