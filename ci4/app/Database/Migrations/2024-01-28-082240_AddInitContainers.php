<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentSpecifications;
use App\Controllers\InitContainers;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddInitContainers extends Migration {

    public function up() {
        Table::init('init_containers')
            ->create()
            ->column('name', ColumnTypes::VARCHAR_127)
            ->column('container_image_id', ColumnTypes::INT)->addIndex('container_image_id')
            ->column('command', ColumnTypes::TEXT)
            ->column('args', ColumnTypes::TEXT)
            ->column('include_deployment_environment_variables', ColumnTypes::BOOL_0)
            ->column('container_image_tag_policy', ColumnTypes::VARCHAR_63)
            ->column('container_image_tag_value', ColumnTypes::VARCHAR_127)
            ->column('container_image_pull_policy', ColumnTypes::VARCHAR_63);

        Table::init('deployment_specification_init_containers')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('init_container_id', ColumnTypes::INT)->addIndex('init_container_id')
            ->column('position', ColumnTypes::INT);

        Table::init('init_container_environment_variables')
            ->create()
            ->column('init_container_id', ColumnTypes::INT)->addIndex('init_container_id')
            ->column('name', ColumnTypes::VARCHAR_127)
            ->column('value', ColumnTypes::TEXT);

        ApiRoute::addResourceControllerGet(InitContainers::class);
        ApiRoute::addResourceControllerPatch(InitContainers::class);
        ApiRoute::addResourceControllerPost(InitContainers::class);
        ApiRoute::addResourceControllerDelete(InitContainers::class);
        ApiRoute::quick('/init-containers/([0-9]+)/environment-variables', InitContainers::class, 'updateEnvironmentVariables/$1', 'put');

        ApiRoute::quick('/deployment-specifications/([0-9]+)/init-containers', DeploymentSpecifications::class, 'updateInitContainers/$1', 'put');

    }

    public function down() {

    }

}
