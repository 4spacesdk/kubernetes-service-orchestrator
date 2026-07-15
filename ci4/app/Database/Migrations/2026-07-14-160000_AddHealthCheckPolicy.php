<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddHealthCheckPolicy extends Migration {

    public function up() {
        // Free text rather than an enum, so an installation on a provider we have not heard of can
        // still name it. Only a few values carry behaviour today, see \HostingProviders.
        Table::init('systems')
            ->column('hosting_provider', ColumnTypes::VARCHAR_127);

        // Empty health_check_type means "no opinion", which keeps existing deployments on the
        // provider's default health check instead of silently changing their behaviour.
        Table::init('deployment_specification_service_ports')
            ->column('health_check_type', ColumnTypes::VARCHAR_27)
            ->column('health_check_path', ColumnTypes::VARCHAR_255);
    }

    public function down() {
        Table::init('systems')
            ->dropColumn('hosting_provider');

        Table::init('deployment_specification_service_ports')
            ->dropColumn('health_check_type')
            ->dropColumn('health_check_path');
    }

}
