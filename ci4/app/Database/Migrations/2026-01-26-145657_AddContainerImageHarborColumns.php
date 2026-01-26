<?php namespace App\Database\Migrations;

use App\Controllers\AutoUpdates;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddContainerImageHarborColumns extends Migration {

    public function up() {
        Table::init('container_images')
            ->column('registry_provider_harbor_url', ColumnTypes::VARCHAR_511)
            ->column('registry_provider_harbor_username', ColumnTypes::VARCHAR_511)
            ->column('registry_provider_harbor_password', ColumnTypes::VARCHAR_511);
        ApiRoute::public('auto-updates/webhooks/harbor', AutoUpdates::class, 'webhooksHarbor', 'post');
    }

    public function down() {

    }

}
