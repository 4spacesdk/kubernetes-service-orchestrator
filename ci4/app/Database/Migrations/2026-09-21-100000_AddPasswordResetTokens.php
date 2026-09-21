<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * A forgotten password used to be replaced on the spot and the new one mailed in plain text,
 * so anyone who knew an operator's address could lock them out. It is now a link carrying a
 * one-time token, and nothing changes until the link is followed.
 *
 * Only the token's SHA-256 is stored: the token is as good as a password until it expires,
 * and a database dump should not hand out working links.
 */
class AddPasswordResetTokens extends Migration {

    public function up() {
        Table::init('users')
            ->column('password_reset_token_hash', 'VARCHAR(64)')
            ->column('password_reset_expires', ColumnTypes::DATETIME);

        ApiRoute::public('/login/resetPassword', \App\Controllers\Login::class, 'resetPassword', 'get');
        ApiRoute::public('/login/resetPassword', \App\Controllers\Login::class, 'resetPassword', 'post');
    }

    public function down() {

    }

}
