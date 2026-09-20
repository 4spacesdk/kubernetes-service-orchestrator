<?php namespace App\Database\Migrations;

use App\Controllers\Workspaces;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * A customer on pause is not a customer who is gone, and `status` could not say so: it is
 * recomputed from the deployments, so `Inactive` fell off on its own - a workspace with no
 * deployments went back to Draft, one deployment deploying made the whole workspace
 * Deploying, and a failing one made it Error, which auto update does not skip.
 *
 * The pause is a decision a person makes, so it is stored as one. Signed in only.
 */
class AddWorkspacePause extends Migration {

    public function up() {
        Table::init('workspaces')
            ->column('is_paused', ColumnTypes::BOOL_0);

        ApiRoute::quick('workspaces/([0-9]+)/pause', Workspaces::class, 'pause/$1', 'put');
        ApiRoute::quick('workspaces/([0-9]+)/resume', Workspaces::class, 'resume/$1', 'put');
    }

    public function down() {

    }

}
