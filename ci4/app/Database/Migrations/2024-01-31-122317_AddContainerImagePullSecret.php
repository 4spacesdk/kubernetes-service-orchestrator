<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddContainerImagePullSecret extends Migration {

    public function up() {
        Table::init('container_images')
            ->column('pull_secret', ColumnTypes::VARCHAR_511);
        Database::connect()
            ->table('container_images')
            ->set('pull_secret', 'gcr-service-account')
            ->update();
    }

    public function down() {

    }

}
