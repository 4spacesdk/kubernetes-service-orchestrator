<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentSpecifications;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddQuickCommands extends Migration {
    
    public function up() {
        Table::init('deployment_specification_quick_commands')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('name', ColumnTypes::VARCHAR_127)
            ->column('command', ColumnTypes::VARCHAR_1023);
        ApiRoute::quick('/deployment-specifications/([0-9]+)/quick-commands', DeploymentSpecifications::class, 'updateQuickCommands/$1', 'put');
    }

    public function down() {
        
    }
    
}
