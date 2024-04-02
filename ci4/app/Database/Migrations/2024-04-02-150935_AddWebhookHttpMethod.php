<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddWebhookHttpMethod extends Migration {

    public function up() {
        Table::init('webhooks')
            ->column('http_method', ColumnTypes::VARCHAR_63);

        Database::connect()
            ->table('webhooks')
            ->set('http_method', 'post')
            ->update();
    }

    public function down() {

    }

}
