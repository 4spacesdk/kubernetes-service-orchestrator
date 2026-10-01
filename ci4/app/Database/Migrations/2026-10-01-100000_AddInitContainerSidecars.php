<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentSpecifications;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * An init container that keeps running beside the app - a native sidecar, see
 * `InitContainer::toKubernetesResource()`. Off for every existing one. A specification lists its
 * sidecars apart from its other init containers, so they have a list of their own to save.
 */
class AddInitContainerSidecars extends Migration {

    public function up() {
        Table::init('init_containers')
            ->column('is_sidecar', ColumnTypes::BOOL_0);

        ApiRoute::quick('/deployment-specifications/([0-9]+)/sidecars', DeploymentSpecifications::class, 'updateSidecars/$1', 'put');
    }

    public function down() {

    }

}
