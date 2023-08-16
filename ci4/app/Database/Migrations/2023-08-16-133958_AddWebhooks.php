<?php namespace App\Database\Migrations;

use App\Controllers\Webhooks;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddWebhooks extends Migration {

    public function up() {
        Table::init('webhooks')
            ->create()
            ->column('type', ColumnTypes::VARCHAR_255)
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('url', ColumnTypes::VARCHAR_2047)
            ->column('content_type', ColumnTypes::VARCHAR_127)
            ->column('auth_bearer_token', ColumnTypes::VARCHAR_2047)
            ->timestamps();

        Table::init('webhook_deliveries')
            ->create()
            ->column('webhook_id', ColumnTypes::INT)
            ->column('url', ColumnTypes::VARCHAR_2047)
            ->column('method', ColumnTypes::VARCHAR_127)
            ->column('content_type', ColumnTypes::VARCHAR_127)
            ->column('auth_bearer_token', ColumnTypes::VARCHAR_2047)
            ->column('payload', ColumnTypes::TEXT)
            ->column('response_code', ColumnTypes::INT)
            ->column('payload', ColumnTypes::TEXT)
            ->column('response_headers', ColumnTypes::TEXT)
            ->column('response_body', ColumnTypes::TEXT)
            ->column('response_time', ColumnTypes::INT)
            ->timestamps();

        ApiRoute::addResourceController(Webhooks::class);
        ApiRoute::quick('/webhooks/types', Webhooks::class, 'typesGet', 'get');
        ApiRoute::quick('/webhooks/([0-9]+)/deliveries', Webhooks::class, 'deliveriesGet/$1', 'get');
        ApiRoute::quick('/webhooks/([0-9]+)/deliveries/([0-9]+)/retry', Webhooks::class, 'deliveriesRetry/$1/$2', 'put');
    }

    public function down() {

    }

}
