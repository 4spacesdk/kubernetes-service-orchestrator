<?php namespace App\Database\Migrations;

use App\Controllers\OAuthAgent;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

/**
 * `POST /oauth-agent/logout`: the browser's refresh token revoked and its cookie gone - see
 * `OAuthAgent::logout()`. Public like the other two oauth-agent routes: it acts on the cookie
 * the browser sends, not on a sign-in.
 */
class AddSignOut extends Migration {

    public function up() {
        ApiRoute::public('oauth-agent/logout', OAuthAgent::class, 'logout', 'post');
    }

    public function down() {

    }

}
