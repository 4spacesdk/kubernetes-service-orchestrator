<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

/**
 * TLS to a database service, with its certificates in the row: the CA and the client
 * certificate as PEM, and the client key encrypted like the password - see
 * `DatabaseService::prepareConnection()`.
 */
class AddDatabaseServiceTls extends Migration {

    public function up() {
        Table::init('database_services')
            ->column('tls', ColumnTypes::BOOL_0)
            ->column('tls_verify', ColumnTypes::BOOL_1)
            ->column('tls_ca', 'TEXT')
            ->column('tls_client_cert', 'TEXT')
            ->column('tls_client_key', 'TEXT');
    }

    public function down() {

    }

}
