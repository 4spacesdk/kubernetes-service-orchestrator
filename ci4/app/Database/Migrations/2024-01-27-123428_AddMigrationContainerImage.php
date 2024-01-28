<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddMigrationContainerImage extends Migration {

    public function up() {
        Table::init('deployment_specifications')
            ->column('database_migration_container_image_id', ColumnTypes::INT)->addIndex('database_migration_container_image_id')
            ->column('database_migration_container_image_tag_policy', ColumnTypes::VARCHAR_63)
            ->column('database_migration_container_image_tag_value', ColumnTypes::VARCHAR_127);

        Database::connect()
            ->table('deployment_specifications')
            ->set('database_migration_container_image_tag_policy', \ContainerImageTagPolicies::MatchDeployment)
            ->update();

        Table::init('migration_jobs')
            ->column('image', ColumnTypes::VARCHAR_1023)
            ->column('command', ColumnTypes::TEXT);
    }

    public function down() {

    }

}
