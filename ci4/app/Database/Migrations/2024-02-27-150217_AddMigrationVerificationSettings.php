<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddMigrationVerificationSettings extends Migration {

    public function up() {
        Table::init('deployment_specifications')
            ->column('database_migration_verification_type', ColumnTypes::VARCHAR_63)
            ->column('database_migration_verification_value', ColumnTypes::VARCHAR_1023);
        Database::connect()
            ->table('deployment_specifications')
            ->set('database_migration_verification_type', \MigrationVerificationTypes::EndsWith)
            ->set('database_migration_verification_value', 'Done migrations.')
            ->update();
    }

    public function down() {

    }

}
