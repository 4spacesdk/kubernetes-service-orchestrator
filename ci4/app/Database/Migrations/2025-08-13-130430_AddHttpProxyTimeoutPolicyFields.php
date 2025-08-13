<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddHttpProxyTimeoutPolicyFields extends Migration {

    public function up() {
        Table::init('deployment_specification_http_proxy_routes')
            ->column('timeout_policy_idle', ColumnTypes::VARCHAR_27)
            ->column('timeout_policy_response', ColumnTypes::VARCHAR_27)
            ->column('timeout_policy_idle_connection', ColumnTypes::VARCHAR_27);
    }

    public function down() {

    }

}
