<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddGcpBackendPolicy extends Migration {

    public function up() {
        // A GCP backend service defaults to a 30s response timeout, which cuts long requests short once
        // traffic goes through a GKE Gateway. This column lets a specification raise that timeout via a
        // GCPBackendPolicy. Null means "no opinion", so existing services keep GKE's default untouched.
        Table::init('deployment_specifications')
            ->column('gateway_backend_timeout', ColumnTypes::INT_NULL);
    }

    public function down() {
        Table::init('deployment_specifications')
            ->dropColumn('gateway_backend_timeout');
    }

}
