<?php namespace App\Database\Migrations;

use App\Entities\Workspace;
use App\Models\WorkspaceModel;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddWorkspaceStatus extends Migration {

    public function up() {
        Table::init('workspaces')
            ->column('status', ColumnTypes::VARCHAR_63)->addIndex('status');

        /** @var Workspace $allWorkspaces */
        $allWorkspaces = (new WorkspaceModel())->find();
        foreach ($allWorkspaces as $workspace) {
            $workspace->checkStatus();
        }
    }

    public function down() {

    }

}
