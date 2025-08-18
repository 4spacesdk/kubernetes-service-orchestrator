<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddImagePullPolicy extends Migration {

    public function up() {
        Table::init('container_images')
            ->column('default_image_pull_policy', ColumnTypes::VARCHAR_63);
        Table::init('deployment_package_deployment_specifications')
            ->column('default_image_pull_policy', ColumnTypes::VARCHAR_63);
        Table::init('deployments')
            ->column('image_pull_policy', ColumnTypes::VARCHAR_63);

        Database::connect()
            ->table('container_images')
            ->set('default_image_pull_policy', 'Always')
            ->update();
        Database::connect()
            ->table('deployment_package_deployment_specifications')
            ->set('default_image_pull_policy', 'Always')
            ->update();
        Database::connect()
            ->table('deployments')
            ->set('image_pull_policy', 'Always')
            ->update();

        ApiRoute::quick('/deployments/([0-9]+)/image-pull-policy', Deployments::class, 'updateImagePullPolicy/$1', 'put');
    }

    public function down() {

    }

}
