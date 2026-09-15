<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

class WidenWorkspaceAliases extends Migration {

    public function up() {
        // Aliases can now hold full hostnames
        Database::connect()->query('ALTER TABLE workspaces MODIFY aliases VARCHAR(511)');
    }

    public function down() {

    }

}
