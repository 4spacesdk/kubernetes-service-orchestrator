<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

class FixMigrationJobLogField extends Migration {

    public function up() {
        Database::connect()->query('ALTER TABLE migration_jobs MODIFY log LONGTEXT');
    }

    public function down() {

    }

}
