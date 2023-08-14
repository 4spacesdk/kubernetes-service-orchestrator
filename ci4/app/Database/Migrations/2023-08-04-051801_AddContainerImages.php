<?php namespace App\Database\Migrations;

use App\Controllers\ContainerImages;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddContainerImages extends Migration {

    public function up() {
        Table::init('container_images')
            ->create()
            ->column('name', ColumnTypes::VARCHAR_1023)
            ->column('url', ColumnTypes::VARCHAR_1023)
            ->timestamps();

        ApiRoute::addResourceController(ContainerImages::class);
    }

    public function down() {

    }

}
