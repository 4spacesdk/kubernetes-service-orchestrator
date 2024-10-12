<?php namespace App\Database\Migrations;

use App\Controllers\Users;
use AuthExtension\Migration\Upgrade_1_2_0;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

class AddMFA extends Migration {

    public function up() {
        Upgrade_1_2_0::migrateUp();

        ApiRoute::quick('users/mfa/setup/prepare', Users::class, 'mfaSetupPrepare', 'get');
        ApiRoute::quick('users/mfa/setup/verify', Users::class, 'mfaSetupVerify', 'put');
        ApiRoute::quick('users/mfa/setup/remove', Users::class, 'mfaSetupRemove', 'put');

        ApiRoute::public('login/twoFactor', \App\Controllers\Login::class, 'twoFactor', 'get');
        ApiRoute::public('login/twoFactor', \App\Controllers\Login::class, 'twoFactor', 'post');
    }

    public function down() {

    }

}
