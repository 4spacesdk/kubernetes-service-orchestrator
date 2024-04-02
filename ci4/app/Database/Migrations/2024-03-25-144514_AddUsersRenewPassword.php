<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddUsersRenewPassword extends Migration {

    public function up() {
        Table::init('users')
            ->column('renew_password', ColumnTypes::BOOL_0);

        ApiRoute::public('/login/renewPassword', \App\Controllers\Login::class, 'renewPassword', 'get');
        ApiRoute::public('/login/renewPassword', \App\Controllers\Login::class, 'renewPassword', 'post');
        ApiRoute::public('/login/forgotPassword', \App\Controllers\Login::class, 'forgotPassword', 'get');
        ApiRoute::public('/login/forgotPassword', \App\Controllers\Login::class, 'forgotPassword', 'post');
    }

    public function down() {

    }

}
