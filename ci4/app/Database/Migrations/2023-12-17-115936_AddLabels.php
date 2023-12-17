<?php namespace App\Database\Migrations;

use App\Controllers\Workspaces;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddLabels extends Migration {

    public function up() {
        Table::init('labels')
            ->create()
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('value', ColumnTypes::VARCHAR_4095);
        Table::init('labels_workspaces')
            ->create()
            ->column('label_id', ColumnTypes::INT)
            ->column('workspace_id', ColumnTypes::INT);

        ApiRoute::quick('/workspaces/([0-9]+)/labels', Workspaces::class, 'updateLabels/$1', 'put');
    }

    public function down() {

    }

}
